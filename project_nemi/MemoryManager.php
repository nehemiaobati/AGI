<?php

class MemoryManager
{
    private array $interactions = [];
    private array $entities = [];

    public function __construct()
    {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0775, true);
        }
        $this->loadMemory();
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

        // Ensure forward slashes are not escaped by default.
        $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        file_put_contents(INTERACTIONS_FILE, json_encode($this->interactions, $options));
        file_put_contents(ENTITIES_FILE, json_encode($this->entities, $options));
    }

    public function getTimeAwareSystemPrompt(): string
    {
        return "**PRIMARY DIRECTIVE: YOU ARE A HELPFUL, TIME-AWARE ASSISTANT.**\n\n" .
            "**RULES OF OPERATION:**\n" .
            "1.  **ANALYZE TIMESTAMPS:** You will be given a `CURRENT_TIME` and `RECALLED_CONTEXT`. Use this to understand the history of events.\n" .
            "2.  **CALCULATE RELATIVE TIME:** Interpret expressions like 'yesterday' against the provided `CURRENT_TIME`.\n\n" .
            "**TOOL EXECUTION MANDATE:**\n" .
            "3.  **DIRECTLY USE TOOLS:** You have `googleSearch` and `urlContext` tools. Your primary goal is to use these tools to directly answer the user's question. **DO NOT describe that you are going to use a tool.** Execute it and provide the final answer based on its output.\n" .
            "4.  **FULFILL THE REQUEST:** Use the correct tool to answer the user's query and prioritize providing the final, complete answer.";
    }

    private function extractEntities(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/[\s,\.\?\!]+/', $text);
        $stopWords = ['a', 'an', 'the', 'is', 'in', 'it', 'of', 'for', 'on', 'what', 'were', 'my', 'that', 'we', 'to'];
        return array_filter(array_unique($words), fn($word) => !in_array($word, $stopWords) && strlen($word) > 3);
    }
    
    // --- NEW: Semantic Expansion of Entities ---
    private function normalizeAndExpandEntities(array $baseEntities): array
    {
        $expanded = $baseEntities;
        foreach ($baseEntities as $entityKey) {
            if (isset($this->entities[$entityKey]['relationships'])) {
                // Add related entities to the search pool
                $expanded = array_merge($expanded, array_keys($this->entities[$entityKey]['relationships']));
            }
        }
        return array_unique($expanded);
    }

    // --- MODIFIED: Uses Semantic Expansion ---
    public function getRelevantContext(string $userInput): array
    {
        $inputEntities = $this->extractEntities($userInput);
        $searchEntities = $this->normalizeAndExpandEntities($inputEntities); // Use expanded list
        $relevantInteractionIds = [];

        foreach ($searchEntities as $entityKey) {
            if (isset($this->entities[$entityKey])) {
                $relevantInteractionIds = array_merge($relevantInteractionIds, $this->entities[$entityKey]['mentioned_in']);
            }
        }
        
        $uniqueInteractionIds = array_unique($relevantInteractionIds);
        $relevantMemories = [];
        foreach ($uniqueInteractionIds as $id) {
            if (isset($this->interactions[$id])) {
                $relevantMemories[$id] = $this->interactions[$id];
            }
        }

        uasort($relevantMemories, fn($a, $b) => ($b['relevance_score'] ?? 1.0) <=> ($a['relevance_score'] ?? 1.0));

        $context = '';
        $tokenCount = 0;
        $usedInteractionIds = [];
        
        foreach ($relevantMemories as $id => $memory) {
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
    
    // --- MODIFIED: Implements Contextual Decay ---
    public function updateMemory(string $userInput, string $aiOutput, array $usedInteractionIds): string
    {
        $recentEntities = [];
        // Apply rewards to used interactions and identify recent entities
        foreach ($usedInteractionIds as $id) {
            if (isset($this->interactions[$id])) {
                $this->interactions[$id]['relevance_score'] += REWARD_SCORE;
                $this->interactions[$id]['last_accessed'] = date('c');
                $recentEntities = array_merge($recentEntities, $this->interactions[$id]['processed_input']['keywords']);
            }
        }
        $recentEntities = array_unique($recentEntities);

        // Apply decay to all interactions (contextual decay)
        foreach ($this->interactions as $id => &$interaction) {
            $isRelatedToRecentTopic = !empty(array_intersect($interaction['processed_input']['keywords'], $recentEntities));
            $decay = $isRelatedToRecentTopic ? DECAY_SCORE * RECENT_TOPIC_DECAY_MODIFIER : DECAY_SCORE;
            $interaction['relevance_score'] -= $decay;
        }

        $newId = uniqid('int_', true);
        $keywords = $this->extractEntities($userInput);
        
        $this->interactions[$newId] = [
            'timestamp' => date('c'),
            'user_input_raw' => $userInput,
            'processed_input' => ['keywords' => $keywords],
            'ai_output' => $aiOutput,
            'relevance_score' => INITIAL_SCORE,
            'last_accessed' => date('c'),
            'context_used_ids' => $usedInteractionIds // For feedback
        ];
        
        $this->updateEntitiesFromInteraction($keywords, $newId);
        $this->pruneMemory();
        
        return $newId; // Return the ID for feedback purposes
    }

    // --- MODIFIED: Builds Relationships ---
    private function updateEntitiesFromInteraction(array $keywords, string $interactionId): void
    {
        $isNovel = false;
        foreach ($keywords as $keyword) {
            $entityKey = strtolower($keyword);
            if (!isset($this->entities[$entityKey])) {
                $isNovel = true;
                $this->entities[$entityKey] = [
                    'name' => $keyword, 'type' => 'Concept', 'access_count' => 0, 'relevance_score' => INITIAL_SCORE,
                    'mentioned_in' => [], 'relationships' => []
                ];
            }
            $this->entities[$entityKey]['access_count']++;
            $this->entities[$entityKey]['relevance_score'] += REWARD_SCORE;
            if (!in_array($interactionId, $this->entities[$entityKey]['mentioned_in'])) {
                $this->entities[$entityKey]['mentioned_in'][] = $interactionId;
            }
        }
        
        // If the interaction introduced a new entity, give it a novelty bonus
        if ($isNovel) {
            $this->interactions[$interactionId]['relevance_score'] += NOVELTY_BONUS;
        }

        // --- NEW: Update Relationships ---
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

    // --- NEW: User Feedback Handling ---
    public function applyFeedback(string $interactionId, bool $isGood): void
    {
        if (!isset($this->interactions[$interactionId])) return;

        $adjustment = $isGood ? USER_FEEDBACK_REWARD : USER_FEEDBACK_PENALTY;
        
        // Adjust the score of the answer itself
        $this->interactions[$interactionId]['relevance_score'] += $adjustment;

        // Adjust the scores of the memories used to generate the answer
        $contextIds = $this->interactions[$interactionId]['context_used_ids'] ?? [];
        foreach ($contextIds as $id) {
            if (isset($this->interactions[$id])) {
                // Apply a smaller adjustment to the context memories
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
}
