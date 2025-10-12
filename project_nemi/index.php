<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'MemoryManager.php';
require_once 'GeminiClient.php';

// Handle User Feedback
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['feedback'], $_GET['id'])) {
    $memory = new MemoryManager();
    $isGood = $_GET['feedback'] === 'good';
    $memory->applyFeedback($_GET['id'], $isGood);
    $memory->saveMemory();
    header("Location: index.php");
    exit();
}

function runCoreLogic(string $userInput): array
{
    $memory = new MemoryManager();
    $gemini = new GeminiClient();

    // Dynamic Tool Selection Logic
    $toolsToUse = ['googleSearch']; // Enable search by default
    $urlPattern = '/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|]/i';
    if (preg_match($urlPattern, $userInput)) {
        // The prompt guides the AI to use its search tool to analyze the URL
        $userInput .= "\n\n[SYSTEM NOTE: The user has provided a URL. Prioritize analyzing its content.]";
    }

    $recalled = $memory->getRelevantContext($userInput);
    $context = $recalled['context'];

    $systemPrompt = $memory->getTimeAwareSystemPrompt();
    $currentTime = "CURRENT_TIME: " . date('Y-m-d H:i:s T');
    $finalPrompt = "{$systemPrompt}\n\n---RECALLED CONTEXT---\n{$context}---END CONTEXT---\n\n{$currentTime}\n\nUser query: \"{$userInput}\"";

    $aiResponse = $gemini->generateResponse($finalPrompt, $toolsToUse);

    $newInteractionId = $memory->updateMemory($userInput, $aiResponse, $recalled['used_interaction_ids']);
    $memory->saveMemory();

    return [
        'userInput' => $userInput,
        'aiResponse' => $aiResponse,
        'context' => $context,
        'interactionId' => $newInteractionId
    ];
}

// --- Main Web Mode Execution ---
$userInput = '';
$aiResponse = '';
$context = '';
$interactionId = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['prompt'])) {
    $userInput = trim($_POST['prompt']);
    $result = runCoreLogic($userInput);
    $aiResponse = $result['aiResponse'];
    $context = $result['context'];
    $interactionId = $result['interactionId'];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project NEMI v4.2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #212529; color: #e9ecef; }
        .container { max-width: 800px; }
        .card { border-color: #495057; }
        .card-header { background-color: #343a40; }
        .feedback-buttons a { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Project NEMI <span class="badge bg-primary">v4.2</span></h1>
            <p class="lead text-muted">AI with Semantic Memory & URL Fix</p>
        </div>
        <div class="card bg-dark mb-3">
            <div class="card-body">
                <form action="index.php" method="post">
                    <textarea class="form-control bg-dark text-white mb-3" name="prompt" rows="3" placeholder="Ask anything or provide a URL to analyze..."><?= htmlspecialchars($userInput) ?></textarea>
                    <button type="submit" class="btn btn-primary w-100">Send</button>
                </form>
            </div>
        </div>
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <div class="card bg-dark mb-3">
                <div class="card-header">Your Prompt</div>
                <div class="card-body"><p class="card-text"><?= nl2br(htmlspecialchars($userInput)) ?></p></div>
            </div>
            <div class="card bg-dark mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    AI Response
                    <div class="feedback-buttons">
                        <a href="?feedback=good&id=<?= urlencode($interactionId) ?>" class="btn btn-sm btn-outline-success">👍 Good</a>
                        <a href="?feedback=bad&id=<?= urlencode($interactionId) ?>" class="btn btn-sm btn-outline-danger">👎 Bad</a>
                    </div>
                </div>
                <div class="card-body"><p class="card-text"><?= nl2br(htmlspecialchars($aiResponse)) ?></p></div>
            </div>
            <div class="card bg-dark">
                <div class="card-header"><a class="text-decoration-none" data-bs-toggle="collapse" href="#debugCollapse">Debug: Recalled Context</a></div>
                <div class="collapse" id="debugCollapse">
                    <div class="card-body"><pre class="text-white-50 small"><code><?= htmlspecialchars($context) ?></code></pre></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>