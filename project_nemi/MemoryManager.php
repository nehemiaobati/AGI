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
        file_put_contents(INTERACTIONS_FILE, json_encode($this->interactions, JSON_PRETTY_PRINT));
        file_put_contents(ENTITIES_FILE, json_encode($this->entities, JSON_PRETTY_PRINT));
    }

    private function extractEntities(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/[\s,\.\?\!]+/', $text);
        $stopWords = ['a', 'an', 'the', 'is', 'in', 'it', 'of', 'for', 'on', 'what', 'were', 'my', 'that', 'we', 'to'];
        return array_filter(array_unique($words), fn($word) => !in_array($word, $stopWords) && strlen($word) > 3);
    }

    /**
     * Creates a direct, rule-based system prompt to make the AI aware of time.
     * This structured format is more effective for LLM instruction.
     * @return string
     */
    public function getTimeAwareSystemPrompt(): string
    {
        return
            "**PRIMARY DIRECTIVE: YOU ARE A TIME-AWARE .**\n" .
            "Your core function is to analyze and use temporal information to answer user queries accurately.\n\n" .
            "**RULES OF OPERATION:**\n" .
            "1.  **ANALYZE TIMESTAMPS:** You will be given a `CURRENT_TIME` and `RECALLED_CONTEXT`. Every piece of context is prefixed with a timestamp `[On YYYY-MM-DD HH:MM:SS]`.\n" .
            "2.  **CALCULATE RELATIVE TIME:** Interpret all relative time expressions (e.g., 'yesterday', 'last week', 'an hour ago') by calculating them against the `CURRENT_TIME` you are provided.\n" .
            "3.  **GROUND YOUR ANSWERS:** Base all time-related statements on the timestamps from the recalled context. If there is no relevant temporal information, state that you do not have a memory of events at that time.\n\n" .
            "**TOOL USAGE:**\n" .
            "4.  **UTILIZE AVAILABLE TOOLS:** You have access to tools that can perform specific actions, process URL contexts, and conduct searches. When a user's request can be fulfilled by one of these tools, you should indicate your ability to use the relevant tool.\n" .
            "5.  **PROCESS URL CONTEXT:** If URL context is provided, analyze its content to answer questions. Prioritize information from URLs when relevant.\n" .
            "6.  **PERFORM SEARCHES:** If a query requires external information not present in your memory or provided context, you should indicate that you can perform a search to find the necessary information.";
    }
    /**
     * Finds the most relevant memories and formats them with timestamps.
     * @return array ['context' => string, 'used_interaction_ids' => array]
     */
    public function getRelevantContext(string $userInput): array
    {
        $inputEntities = $this->extractEntities($userInput);
        $relevantInteractionIds = [];

        foreach ($inputEntities as $entityKey) {
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

        uasort($relevantMemories, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        $context = '';
        $tokenCount = 0;
        $usedInteractionIds = [];
        
        if (empty($relevantMemories)) {
            $context = "No relevant memories found.\n";
        } else {
            foreach ($relevantMemories as $id => $memory) {
                // --- IMPROVEMENT: Add timestamp to the context ---
                $timestamp = date('Y-m-d H:i:s', strtotime($memory['timestamp']));
                $memoryText = "[On {$timestamp}] User said: '{$memory['user_input_raw']}'. You responded: '{$memory['ai_output']}'.\n";
                // --- END IMPROVEMENT ---
                
                $memoryTokenCount = str_word_count($memoryText);

                if ($tokenCount + $memoryTokenCount <= CONTEXT_TOKEN_BUDGET) {
                    $context .= $memoryText;
                    $tokenCount += $memoryTokenCount;
                    $usedInteractionIds[] = $id;
                } else {
                    break;
                }
            }
        }

        return [
            'context' => $context,
            'used_interaction_ids' => $usedInteractionIds
        ];
    }
    
    public function updateMemory(string $userInput, string $aiOutput, array $usedInteractionIds): void
    {
        foreach ($this->interactions as &$interaction) {
            $interaction['relevance_score'] -= DECAY_SCORE;
        }

        foreach ($usedInteractionIds as $id) {
            if (isset($this->interactions[$id])) {
                $this->interactions[$id]['relevance_score'] += DECAY_SCORE + REWARD_SCORE;
                $this->interactions[$id]['last_accessed'] = date('c');
            }
        }

        $newId = uniqid('int_', true);
        $keywords = $this->extractEntities($userInput);
        
        $this->interactions[$newId] = [
            'timestamp' => date('c'),
            'user_input_raw' => $userInput,
            'processed_input' => ['keywords' => $keywords],
            'ai_output' => $aiOutput,
            'relevance_score' => INITIAL_SCORE,
            'last_accessed' => date('c')
        ];
        
        $this->updateEntitiesFromInteraction($keywords, $newId);
        $this->pruneMemory();
    }

    private function updateEntitiesFromInteraction(array $keywords, string $interactionId): void
    {
        foreach ($keywords as $keyword) {
            $entityKey = strtolower($keyword);
            if (isset($this->entities[$entityKey])) {
                $this->entities[$entityKey]['access_count']++;
                $this->entities[$entityKey]['relevance_score'] += REWARD_SCORE;
                if (!in_array($interactionId, $this->entities[$entityKey]['mentioned_in'])) {
                    $this->entities[$entityKey]['mentioned_in'][] = $interactionId;
                }
            } else {
                $this->entities[$entityKey] = [
                    'name' => $keyword,
                    'type' => 'Concept',
                    'description' => '',
                    'access_count' => 1,
                    'relevance_score' => INITIAL_SCORE,
                    'mentioned_in' => [$interactionId]
                ];
            }
        }
    }
    
    private function pruneMemory(): void
    {
        if (count($this->interactions) > PRUNING_THRESHOLD) {
            uasort($this->interactions, fn($a, $b) => $a['relevance_score'] <=> $b['relevance_score']);
            $this->interactions = array_slice($this->interactions, count($this->interactions) - PRUNING_THRESHOLD, null, true);
        }
    }
}
