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
    
    // --- FUNCTION HAS BEEN UPGRADED ---
    /**
     * Finds the most relevant memories using the ENTITY INDEX for high efficiency.
     * @return array ['context' => string, 'used_interaction_ids' => array]
     */
    public function getRelevantContext(string $userInput): array
    {
        $inputEntities = $this->extractEntities($userInput);
        $relevantInteractionIds = [];

        // Step 1: Use the entity index to find all relevant interaction IDs
        foreach ($inputEntities as $entityKey) {
            if (isset($this->entities[$entityKey])) {
                $relevantInteractionIds = array_merge($relevantInteractionIds, $this->entities[$entityKey]['mentioned_in']);
            }
        }
        
        // Remove duplicate IDs
        $uniqueInteractionIds = array_unique($relevantInteractionIds);
        
        // Step 2: Retrieve only the interaction data we need
        $relevantMemories = [];
        foreach ($uniqueInteractionIds as $id) {
            if (isset($this->interactions[$id])) {
                $relevantMemories[$id] = $this->interactions[$id];
            }
        }

        // Step 3: Sort the selected memories by relevance score
        uasort($relevantMemories, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        // Step 4: Build the context string (same as before)
        $context = '';
        $tokenCount = 0;
        $usedInteractionIds = [];
        
        foreach ($relevantMemories as $id => $memory) {
            $memoryText = "Previously, user said: '{$memory['user_input_raw']}'. The AI responded: '{$memory['ai_output']}'. ";
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
            'context' => $context,
            'used_interaction_ids' => $usedInteractionIds
        ];
    }
    // --- END UPGRADED FUNCTION ---

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