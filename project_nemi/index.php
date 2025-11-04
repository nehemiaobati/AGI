<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'train.php'; // Needed for preprocessText function
require_once 'MemoryManager.php';
require_once 'GeminiClient.php';
require_once __DIR__ . '/../vendor/autoload.php'; // If not already loaded

use NlpTools\Documents\TokensDocument;
use NlpTools\Tokenizers\WhitespaceTokenizer;
use NlpTools\Stemmers\PorterStemmer;

$featureFactory = null;
$classifier = null;

try {
    $tfIdfModelPath = DATA_DIR . '/tf_idf.model'; // Adjust path if models are elsewhere
    $classifierModelPath = DATA_DIR . '/classifier.model'; // Adjust path

    if (!file_exists($tfIdfModelPath) || !file_exists($classifierModelPath)) {
        // Handle error: models not found. You might want to log this or throw an exception.
        error_log("Classification models not found. Run train.php first.");
    } else {
        $featureFactory = unserialize(file_get_contents($tfIdfModelPath));
        $classifier = unserialize(file_get_contents($classifierModelPath));

        if ($featureFactory === false || $classifier === false) {
            error_log("Failed to unserialize classification models. Models might be corrupted.");
        }
    }
} catch (Exception $e) {
    error_log("Error loading classification models: " . $e->getMessage());
}

// Handle User Feedback
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['feedback'], $_GET['id'])) {
    $memory = new MemoryManager();
    $isGood = $_GET['feedback'] === 'good';
    $memory->applyFeedback($_GET['id'], $isGood);
    $memory->saveMemory();
    header("Location: index.php");
    exit();
}

// Handle Optional Text Feedback
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['text_feedback'], $_POST['interaction_id_for_feedback'])) {
    $memory = new MemoryManager();
    $interactionIdForFeedback = $_POST['interaction_id_for_feedback'];
    $textFeedback = trim($_POST['text_feedback']);

    if (!empty($textFeedback)) {
        $memory->addTextFeedback($interactionIdForFeedback, $textFeedback);
        $memory->saveMemory();
    }
    // Redirect to prevent form resubmission on refresh
    header("Location: index.php");
    exit();
}

function runCoreLogic(string $userInput, $featureFactory, $classifier): array
{
    $memory = new MemoryManager();
    $gemini = new GeminiClient();

    $predictedIntent = "unknown";
    if ($classifier && $featureFactory) {
        $processedTokens = preprocessText($userInput); // Use the same preprocessing as during training
        $document = new TokensDocument($processedTokens);

        $predictedIntent = $classifier->classify(['feedback_positive', 'feedback_negative'], $document);
    }

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
        'interactionId' => $newInteractionId,
        'predictedIntent' => $predictedIntent
    ];
}

// --- Main Web Mode Execution ---
$userInput = '';
$aiResponse = '';
$context = '';
$interactionId = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['prompt'])) {
    $userInput = trim($_POST['prompt']);
    $result = runCoreLogic($userInput, $featureFactory, $classifier);
    $aiResponse = $result['aiResponse'];
    $context = $result['context'];
    $interactionId = $result['interactionId'];
    $predictedIntent = $result['predictedIntent'];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project NEMI v5.1 (Hybrid Search)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #212529;
            color: #e9ecef;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border-color: #495057;
        }

        .card-header {
            background-color: #343a40;
        }

        .feedback-buttons a {
            margin-right: 10px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Project NEMI <span class="badge bg-success">v5.1</span></h1>
            <p class="lead text-muted">AI with Hybrid Vector Search</p>
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
                <div class="card-body">
                    <p class="card-text"><?= nl2br(htmlspecialchars($userInput)) ?></p>
                </div>
            </div>
            <?php if ($predictedIntent !== 'feedback_positive' && $predictedIntent !== 'feedback_negative'): ?>
                <div class="card bg-dark mb-3">
                    <div class="card-header">Predicted Intent</div>
                    <div class="card-body">
                        <p class="card-text"><?= htmlspecialchars($predictedIntent) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card bg-dark mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    AI Response
                    <div class="feedback-buttons">
                        <a href="?feedback=good&id=<?= urlencode($interactionId) ?>" class="btn btn-sm btn-outline-success">👍 Good</a>
                        <a href="?feedback=bad&id=<?= urlencode($interactionId) ?>" class="btn btn-sm btn-outline-danger">👎 Bad</a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text"><?= nl2br(htmlspecialchars($aiResponse)) ?></p>
                </div>
            </div>
            <?php if (!empty($interactionId)): ?>
            <div class="card bg-dark mb-3">
                <div class="card-header">Optional Text Feedback</div>
                <div class="card-body">
                    <form action="index.php" method="post">
                        <input type="hidden" name="interaction_id_for_feedback" value="<?= htmlspecialchars($interactionId) ?>">
                        <textarea class="form-control bg-dark text-white mb-3" name="text_feedback" rows="2" placeholder="Provide additional feedback..."></textarea>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Submit Text Feedback</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="card bg-dark">
                <div class="card-header"><a class="text-decoration-none" data-bs-toggle="collapse" href="#debugCollapse">Debug: Recalled Context</a></div>
                <div class="collapse" id="debugCollapse">
                    <div class="card-body">
                        <pre class="text-white-50 small"><code><?= htmlspecialchars($context) ?></code></pre>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>