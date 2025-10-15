<?php
// Now requires the new EmbeddingClient
require_once 'EmbeddingClient.php';

class MemoryManager
{
    private array $interactions = [];
    private array $entities = [];
    private ?EmbeddingClient $embeddingClient;

    public function __construct()
    {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0775, true);
        }
        $this->loadMemory();

        // Instantiate the client only if embeddings are enabled.
        // This is where you would swap in a different client (e.g., new OllamaEmbeddingClient()).
        $this->embeddingClient = ENABLE_EMBEDDINGS ? new EmbeddingClient() : null;
    }

    private function loadMemory(): void
    {
        $this->interactions = file_exists(INTERACTIONS_FILE) ? json_decode(file_get_contents(INTERACTIONS_FILE), true) : [];
        $this->entities = file_exists(ENTITIES_FILE) ? json_decode(file_get_contents(ENTITIES_FILE), true) : [];
    }

    public function saveMemory(): void
    {
        ksort($this->interactions);
        ksort($this->entities);
        $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        file_put_contents(INTERACTIONS_FILE, json_encode($this->interactions, $options));
        file_put_contents(ENTITIES_FILE, json_encode($this->entities, $options));
    }

    // --- Core Memory Retrieval (HYBRID SEARCH) ---

    /**
     * Calculates the cosine similarity between two vectors.
     * @return float A value between -1 and 1. Higher is more similar.
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $count = count($vecA);

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magA += $vecA[$i] * $vecA[$i];
            $magB += $vecB[$i] * $vecB[$i];
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        if ($magA == 0 || $magB == 0) {
            return 0;
        }

        return $dotProduct / ($magA * $magB);
    }

    public function getRelevantContext(string $userInput): array
    {
        // --- Vector Search (Semantic) ---
        $semanticResults = [];
        if ($this->embeddingClient !== null) {
            $inputVector = $this->embeddingClient->getEmbedding($userInput);
            if ($inputVector !== null) {
                $similarities = [];
                foreach ($this->interactions as $id => $interaction) {
                    if (isset($interaction['embedding']) && is_array($interaction['embedding'])) {
                        $similarity = $this->cosineSimilarity($inputVector, $interaction['embedding']);
                        $similarities[$id] = $similarity;
                    }
                }
                // Sort by similarity score, descending
                arsort($similarities);
                $semanticResults = array_slice($similarities, 0, VECTOR_SEARCH_TOP_K, true);
            }
        }

        // --- Keyword Search (Lexical) ---
        $inputEntities = $this->extractEntities($userInput);
        $searchEntities = $this->normalizeAndExpandEntities($inputEntities);
        $keywordResults = [];
        foreach ($searchEntities as $entityKey) {
            if (isset($this->entities[$entityKey])) {
                foreach ($this->entities[$entityKey]['mentioned_in'] as $id) {
                    // Store the relevance score for ranking
                    if (isset($this->interactions[$id])) {
                        $keywordResults[$id] = $this->interactions[$id]['relevance_score'];
                    }
                }
            }
        }
        arsort($keywordResults);

        // --- Hybrid Fusion ---
        $fusedScores = [];
        $allIds = array_unique(array_merge(array_keys($semanticResults), array_keys($keywordResults)));

        foreach ($allIds as $id) {
            $semanticScore = $semanticResults[$id] ?? 0.0;
            // Normalize relevance score to be roughly between 0 and 1 for better blending
            $relevanceScore = isset($keywordResults[$id]) ? tanh($keywordResults[$id] / 10) : 0.0;

            // Weighted average of both scores
            $fusedScores[$id] = (HYBRID_SEARCH_ALPHA * $semanticScore) + ((1 - HYBRID_SEARCH_ALPHA) * $relevanceScore);
        }
        arsort($fusedScores);

        // --- Build Context from Fused Results ---
        $context = '';
        $tokenCount = 0;
        $usedInteractionIds = [];
        foreach ($fusedScores as $id => $score) {
            if (!isset($this->interactions[$id])) continue;
            $memory = $this->interactions[$id];
            $timestamp = date('Y-m-d H:i:s', strtotime($memory['timestamp']));
            $memoryText = "[On {$timestamp}] User: '{$memory['user_input_raw']}'. You: '{$memory['ai_output']}'.\n";
            $memoryTokenCount = str_word_count($memoryText);

            if ($tokenCount + $memoryTokenCount <= CONTEXT_TOKEN_BUDGET) {
                $context .= $memoryText;
                $tokenCount += $memoryTokenCount;
                $usedInteractionIds[] = $id;
            } else {
                break;
            }
        }

        return [
            'context' => empty($context) ? "No relevant memories found.\n" : $context,
            'used_interaction_ids' => $usedInteractionIds
        ];
    }

    public function updateMemory(string $userInput, string $aiOutput, array $usedInteractionIds): string
    {
        $recentEntities = [];
        foreach ($usedInteractionIds as $id) {
            if (isset($this->interactions[$id])) {
                $this->interactions[$id]['relevance_score'] += REWARD_SCORE;
                $this->interactions[$id]['last_accessed'] = date('c');
                if (isset($this->interactions[$id]['processed_input']['keywords'])) {
                    $recentEntities = array_merge($recentEntities, $this->interactions[$id]['processed_input']['keywords']);
                }
            }
        }
        $recentEntities = array_unique($recentEntities);

        foreach ($this->interactions as &$interaction) {
            $keywords = $interaction['processed_input']['keywords'] ?? [];
            $isRelatedToRecentTopic = !empty(array_intersect($keywords, $recentEntities));
            $decay = $isRelatedToRecentTopic ? DECAY_SCORE * RECENT_TOPIC_DECAY_MODIFIER : DECAY_SCORE;
            $interaction['relevance_score'] -= $decay;
        }

        $newId = uniqid('int_', true);
        $keywords = $this->extractEntities($userInput);

        // --- NEW: Generate and store embedding for the new interaction ---
        $embedding = null;
        if ($this->embeddingClient !== null) {
            $fullText = "User: {$userInput} | AI: {$aiOutput}";
            $embedding = $this->embeddingClient->getEmbedding($fullText);
        }

        $this->interactions[$newId] = [
            'timestamp' => date('c'),
            'user_input_raw' => $userInput,
            'processed_input' => ['keywords' => $keywords],
            'ai_output' => $aiOutput,
            'relevance_score' => INITIAL_SCORE,
            'last_accessed' => date('c'),
            'context_used_ids' => $usedInteractionIds,
            'embedding' => $embedding // Add the new embedding to the record
        ];

        $this->updateEntitiesFromInteraction($keywords, $newId);
        $this->pruneMemory();

        return $newId;
    }

    private function updateEntitiesFromInteraction(array $keywords, string $interactionId): void
    {
        $isNovel = false;
        foreach ($keywords as $keyword) {
            $entityKey = strtolower($keyword);
            if (!isset($this->entities[$entityKey])) {
                $isNovel = true;
                $this->entities[$entityKey] = [
                    'name' => $keyword,
                    'type' => 'Concept',
                    'access_count' => 0,
                    'relevance_score' => INITIAL_SCORE,
                    'mentioned_in' => [],
                    'relationships' => []
                ];
            }
            $this->entities[$entityKey]['access_count']++;
            $this->entities[$entityKey]['relevance_score'] += REWARD_SCORE;
            if (!in_array($interactionId, $this->entities[$entityKey]['mentioned_in'])) {
                $this->entities[$entityKey]['mentioned_in'][] = $interactionId;
            }
        }

        if ($isNovel) {
            $this->interactions[$interactionId]['relevance_score'] += NOVELTY_BONUS;
        }

        if (count($keywords) > 1) {
            foreach ($keywords as $k1) {
                foreach ($keywords as $k2) {
                    if ($k1 !== $k2) {
                        $this->entities[$k1]['relationships'][$k2] = ($this->entities[$k1]['relationships'][$k2] ?? 0) + RELATIONSHIP_STRENGTH_INCREMENT;
                    }
                }
            }
        }
    }

    public function applyFeedback(string $interactionId, bool $isGood): void
    {
        if (!isset($this->interactions[$interactionId])) return;

        $adjustment = $isGood ? USER_FEEDBACK_REWARD : USER_FEEDBACK_PENALTY;

        $this->interactions[$interactionId]['relevance_score'] += $adjustment;

        $contextIds = $this->interactions[$interactionId]['context_used_ids'] ?? [];
        foreach ($contextIds as $id) {
            if (isset($this->interactions[$id])) {
                $this->interactions[$id]['relevance_score'] += $adjustment / 2;
            }
        }
    }

    private function pruneMemory(): void
    {
        if (count($this->interactions) > PRUNING_THRESHOLD) {
            uasort($this->interactions, fn($a, $b) => ($a['relevance_score'] ?? 1.0) <=> ($b['relevance_score'] ?? 1.0));
            $this->interactions = array_slice($this->interactions, count($this->interactions) - PRUNING_THRESHOLD, null, true);
        }
    }

    public function getTimeAwareSystemPrompt(): string
    {
        return "**PRIMARY DIRECTIVE: YOU ARE A HELPFUL, TIME-AWARE ASSISTANT.**\n\n" .
            "**RULES OF OPERATION:**\n" .
            "1.  **ANALYZE TIMESTAMPS:** You will be given a `CURRENT_TIME` and `RECALLED_CONTEXT`. Use this to understand the history of events.\n" .
            "2.  **CALCULATE RELATIVE TIME:** Interpret expressions like 'yesterday' against the provided `CURRENT_TIME`.\n\n" .
            "**TOOL EXECUTION MANDATE:**\n" .
            "3.  **DIRECTLY USE TOOLS:** You have a `googleSearch` tool. Your primary goal is to use this tool to directly answer the user's question. **DO NOT describe that you are going to use a tool.** Execute it and provide the final answer based on its output.\n" .
            "4.  **FULFILL THE REQUEST:** If the user provides a URL, use your search ability to access its content and provide a summary. If they ask a general question, use search to find the answer.";
    }

    private function extractEntities(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/https?:\/\/[^\s]+/', ' ', $text);
        $words = preg_split('/[\s,\.\?\!\[\]:]+/', $text);
        $stopWords = ['a', 'an', 'the', 'is', 'in', 'it', 'of', 'for', 'on', 'what', 'were', 'my', 'that', 'we', 'to', 'user', 'note', 'system', 'please'];
        return array_filter(array_unique($words), fn($word) => !in_array($word, $stopWords) && strlen($word) > 3);
    }

    private function normalizeAndExpandEntities(array $baseEntities): array
    {
        $expanded = $baseEntities;
        foreach ($baseEntities as $entityKey) {
            if (isset($this->entities[$entityKey]['relationships'])) {
                $expanded = array_merge($expanded, array_keys($this->entities[$entityKey]['relationships']));
            }
        }
        return array_unique($expanded);
    }
}
