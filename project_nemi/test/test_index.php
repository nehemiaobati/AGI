<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MemoryManager.php';
require_once __DIR__ . '/train.php'; // Include train.php for preprocessing function

use NlpTools\Documents\TokensDocument;

$statusMessage = '';
$featureFactory = null;
$classifier = null;

// Load the trained models
try {
    $tfIdfModelPath = __DIR__ . '/tf_idf.model';
    $classifierModelPath = __DIR__ . '/classifier.model';

    if (!file_exists($tfIdfModelPath)) {
        throw new Exception("Error: TF-IDF model file not found at {$tfIdfModelPath}");
    }
    if (!file_exists($classifierModelPath)) {
        throw new Exception("Error: Classifier model file not found at {$classifierModelPath}");
    }

    $featureFactory = unserialize(file_get_contents($tfIdfModelPath));
    $classifier = unserialize(file_get_contents($classifierModelPath));

    if ($featureFactory === false || $classifier === false) {
        throw new Exception("Error: Failed to unserialize one or both models. Models might be corrupted.");
    }

    $statusMessage = "Models loaded successfully from {$tfIdfModelPath} and {$classifierModelPath}.";

} catch (Exception $e) {
    $statusMessage = "<div class=\"alert alert-danger\" role=\"alert\">" . htmlspecialchars($e->getMessage()) . "</div>";
}

// --- Main Web Mode Execution for testing classification ---
$userInput = '';
$predictedIntent = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['prompt'])) {
    $userInput = trim($_POST['prompt']);

    if ($featureFactory && $classifier) {
        // Preprocess the user input using the same function as during training
        $processedTokens = preprocessText($userInput);
        $document = new TokensDocument($processedTokens);

        // Predict the intent
        $predictedIntent = $classifier->classify(['question', 'command', 'feedback_positive', 'feedback_negative'], $document);
    } else {
        $predictedIntent = "Classification not available due to model loading errors.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project NEMI Classification Test</title>
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
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Project NEMI <span class="badge bg-info">Classification Test</span></h1>
            <p class="lead text-muted">Test Text Classification MVP</p>
        </div>
        <?php if (!empty($statusMessage)): ?>
            <div class="alert alert-info" role="alert">
                <?= $statusMessage ?>
            </div>
        <?php endif; ?>
        <div class="card bg-dark mb-3">
            <div class="card-body">
                <form action="test_index.php" method="post">
                    <textarea class="form-control bg-dark text-white mb-3" name="prompt" rows="3" placeholder="Enter text to classify..."><?= htmlspecialchars($userInput) ?></textarea>
                    <button type="submit" class="btn btn-primary w-100">Classify</button>
                </form>
            </div>
        </div>
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <div class="card bg-dark mb-3">
                <div class="card-header">Your Input</div>
                <div class="card-body">
                    <p class="card-text"><?= nl2br(htmlspecialchars($userInput)) ?></p>
                </div>
            </div>
            <div class="card bg-dark mb-3">
                <div class="card-header">Predicted Intent</div>
                <div class="card-body">
                    <p class="card-text"><strong><?= htmlspecialchars($predictedIntent) ?></strong></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>