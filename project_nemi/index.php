<?php

// Turn on error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'MemoryManager.php';
require_once 'GeminiClient.php';

/**
 * Encapsulates the core AI logic to be shared between web and CLI modes.
 * @param string $userInput The prompt from the user.
 * @return array An associative array with the results.
 */
function runterminalLogic(string $userInput): array
{
    // Initialize managers
    $memory = new MemoryManager();
    $gemini = new GeminiClient();

    // Recall relevant memories to build context
    $recalled = $memory->getRelevantContext($userInput);
    $context = $recalled['context'];

    // Construct the time-aware final prompt
    $systemPrompt = $memory->getTimeAwareSystemPrompt();
    $currentTime = "CURRENT_TIME: " . date('Y-m-d H:i:s T');

    $finalPrompt = $systemPrompt . "\n\n" .
                   "---RECALLED CONTEXT---\n" . $context . "\n---END CONTEXT---\n\n" .
                   $currentTime . "\n\n" .
                   "User query: \"" . $userInput . "\"";

    // Generate a response from the AI
    $aiResponse = $gemini->generateResponse($finalPrompt);

    // Update and save the memory
    $memory->updateMemory($userInput, $aiResponse, $recalled['used_interaction_ids']);
    $memory->saveMemory();

    return [
        'userInput' => $userInput,
        'aiResponse' => $aiResponse,
        'context' => $context
    ];
}

// ===================================================================
// SCRIPT EXECUTION STARTS HERE
// ===================================================================

// Check if running from the command line
if (php_sapi_name() === 'cli') {
    // --- CLI MODE ---
    $userInput = $argv[1] ?? null;

    if (!$userInput) {
        echo "Usage: php index.php \"Your question here\"\n";
        exit(1);
    }

    echo "=================================================\n";
    echo "PROJECT NEMI - DYNAMIC MEMORY AI (CLI MODE)\n";
    echo "=================================================\n\n";
    
    $result = runterminalLogic($userInput);

    echo "User Input: " . $result['userInput'] . "\n\n";
    
    echo "-------------------------------------------------\n";
    echo "Recalled Context (for AI):\n";
    echo empty($result['context']) ? "No relevant memories found.\n" : $result['context'];
    echo "-------------------------------------------------\n\n";
    
    echo "AI is thinking...\n\n";
    echo "AI Response: " . $result['aiResponse'] . "\n\n";
    
    echo "-------------------------------------------------\n";
    echo "Memory has been updated and saved.\n";
    echo "=================================================\n";

} else {
    // --- WEB MODE ---
    $userInput = '';
    $aiResponse = '';
    $context = '';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['prompt'])) {
        $userInput = $_POST['prompt'];
        $result = runterminalLogic($userInput);
        
        // Populate variables for the HTML view
        $aiResponse = $result['aiResponse'];
        $context = $result['context'];
    }

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project NEMI - AGI Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #212529; color: #e9ecef; }
        .container { max-width: 800px; }
        .card { margin-bottom: 1.5rem; border-color: #495057; }
        .card-header { background-color: #343a40; border-bottom: 1px solid #495057; font-weight: bold; }
        .form-control { background-color: #343a40; border-color: #6c757d; color: #fff; }
        .form-control:focus { background-color: #343a40; border-color: #86b7fe; color: #fff; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, .25); }
        .btn-primary { width: 100%; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Project NEMI</h1>
            <p class="lead text-muted">A Time-Aware AI with Dynamic Memory</p>
        </div>
        <div class="card bg-dark">
            <div class="card-body">
                <form action="index.php" method="post">
                    <div class="mb-3">
                        <textarea class="form-control" name="prompt" rows="3" placeholder="Ask the AI anything..."><?= htmlspecialchars($userInput) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            </div>
        </div>
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($userInput)): ?>
            <div class="card bg-dark">
                <div class="card-header">Your Prompt</div>
                <div class="card-body"><p class="card-text"><?= htmlspecialchars($userInput) ?></p></div>
            </div>
            <div class="card bg-dark">
                <div class="card-header">AI Response</div>
                <div class="card-body"><p class="card-text"><?= nl2br(htmlspecialchars($aiResponse)) ?></p></div>
            </div>
            <div class="card bg-dark">
                <div class="card-header">
                    <a class="text-decoration-none" data-bs-toggle="collapse" href="#debugCollapse">Debug View: Recalled Memory Context</a>
                </div>
                <div class="collapse" id="debugCollapse">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">The following context was sent to the AI:</h6>
                        <pre class="text-white-50" style="white-space: pre-wrap; word-wrap: break-word;"><code><?= htmlspecialchars($context) ?></code></pre>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
} // End of the 'else' block for web mode
?>