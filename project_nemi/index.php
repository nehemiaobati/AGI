<?php

// Turn on error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'MemoryManager.php';
require_once 'GeminiClient.php';

// --- Main Application Logic ---

// 1. Get user input
// To run from command line: $userInput = $argv[1] ?? 'Hello?';
$userInput = $argv[1] ?? 'Hello?';

echo "=================================================\n";
echo "PROJECT NEMI - DYNAMIC MEMORY AI\n";
echo "=================================================\n\n";
echo "User Input: " . $userInput . "\n\n";

// 2. Initialize managers
$memory = new MemoryManager();
$gemini = new GeminiClient();

// 3. Recall relevant memories to build context
$recalled = $memory->getRelevantContext($userInput);
$context = $recalled['context'];

echo "-------------------------------------------------\n";
echo "Recalled Context (for AI):\n";
echo empty($context) ? "No relevant memories found.\n" : $context . "\n";
echo "-------------------------------------------------\n\n";

// 4. Construct the final prompt for the AI
$finalPrompt = "Based on the following past conversations as context:\n---CONTEXT---\n{$context}\n---END CONTEXT---\n\nNow, answer this new question from the user:\n\"{$userInput}\"";

// 5. Generate a response from the AI
echo "AI is thinking...\n\n";
$aiResponse = $gemini->generateResponse($finalPrompt);

echo "AI Response: " . $aiResponse . "\n\n";

// 6. Update and save the memory with the new interaction
$memory->updateMemory($userInput, $aiResponse, $recalled['used_interaction_ids']);
$memory->saveMemory();

echo "-------------------------------------------------\n";
echo "Memory has been updated and saved.\n";
echo "=================================================\n";

?>
