### 1. Ensure Composer Dependencies are Installed

The project uses `NlpTools` and potentially other libraries via Composer. Make sure you have run `composer install` in your project's root directory (the one containing `vendor/autoload.php`).

```bash
composer install
```

### 2. Configure `config.php` for Production

*   **`GEMINI_API_KEY`**: **Crucially, replace `'YOUR_GEMINI_API_KEY_HERE'` with your actual Gemini API key.** In a production environment, it's highly recommended to manage this key securely, for example, by loading it from environment variables rather than hardcoding it directly in `config.php`.
*   Review other constants like `MODEL_ID`, `EMBEDDING_MODEL_ID`, `DATA_DIR`, `PRUNING_THRESHOLD`, `CONTEXT_TOKEN_BUDGET`, etc., and adjust them as needed for your production scale and performance requirements.

### 3. Integrate Model Loading and Classification

You'll need to load the trained `tf_idf.model` and `classifier.model` into your application. This is similar to how `test_index.php` does it.

```php
// In your main application file (e.g., index.php, a controller, or a service)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/train.php'; // Needed for preprocessText function
require_once __DIR__ . '/vendor/autoload.php'; // If not already loaded

use NlpTools\Documents\TokensDocument;

$featureFactory = null;
$classifier = null;

try {
    $tfIdfModelPath = __DIR__ . '/tf_idf.model'; // Adjust path if models are elsewhere
    $classifierModelPath = __DIR__ . '/classifier.model'; // Adjust path

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

// When you receive user input:
if ($classifier && $featureFactory) {
    $userInput = "User's actual input string"; // Get this from your application's input
    $processedTokens = preprocessText($userInput); // Use the same preprocessing as during training
    $document = new TokensDocument($processedTokens);

    $predictedIntent = $classifier->classify(['feedback_positive', 'feedback_negative'], $document);
    // Now $predictedIntent will be either 'feedback_positive' or 'feedback_negative'
    // You can use this to drive your application's logic.
} else {
    // Classification is not available, handle accordingly
    $predictedIntent = "unknown";
}
```

### 4. Integrate `MemoryManager`

The `MemoryManager` is central to handling conversational context and embeddings.

```php
// In your main application file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EmbeddingClient.php'; // MemoryManager depends on this
require_once __DIR__ . '/MemoryManager.php';
require_once __DIR__ . '/vendor/autoload.php'; // If not already loaded

$memoryManager = new MemoryManager();

// Example usage within your application's request/response cycle:

// 1. Get relevant context for the current user input
$userInput = "The user's current message.";
$contextData = $memoryManager->getRelevantContext($userInput);
$recalledContext = $contextData['context'];
$usedInteractionIds = $contextData['used_interaction_ids'];

// 2. (Optional) Combine recalled context with a system prompt for your main AI model
$systemPrompt = $memoryManager->getTimeAwareSystemPrompt();
$fullPromptForGemini = $systemPrompt . "\n\n" .
                       "CURRENT_TIME: " . date('Y-m-d H:i:s') . "\n" .
                       "RECALLED_CONTEXT:\n" . $recalledContext . "\n\n" .
                       "User: " . $userInput;

// 3. Send $fullPromptForGemini to your main Gemini content generation client (not provided in this project, but where you'd use MODEL_ID and API_ENDPOINT from config.php)
// $aiResponse = $yourGeminiContentClient->generate($fullPromptForGemini);

// For demonstration, let's assume you get an AI response:
$aiResponse = "This is a simulated AI response based on the input.";

// 4. Update memory with the new interaction
$newInteractionId = $memoryManager->updateMemory($userInput, $aiResponse, $usedInteractionIds);
$memoryManager->saveMemory(); // Don't forget to save changes to disk

// 5. (Optional) Apply feedback if your application allows it.
// In a web environment (e.g., index.php), you would typically capture user feedback
// via a form submission (e.g., "Good" / "Bad" buttons, or a text input that gets classified).
//
// Example: Assuming you have a form that submits 'feedback_type' and 'interaction_id'
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['feedback_type']) && isset($_POST['interaction_id'])) {
    $feedbackType = $_POST['feedback_type']; // e.g., 'positive', 'negative'
    $feedbackInteractionId = $_POST['interaction_id']; // The ID of the interaction being rated

    if ($feedbackType === 'positive') {
        $memoryManager->applyFeedback($feedbackInteractionId, true);
    } elseif ($feedbackType === 'negative') {
        $memoryManager->applyFeedback($feedbackInteractionId, false);
    }
    $memoryManager->saveMemory(); // Save changes after applying feedback
}
```

### 5. File Paths and Data Directory

*   Ensure that the `DATA_DIR` constant in `config.php` points to a writable directory where `interactions.json`, `entities.json`, and `prompts.json` can be stored. This directory should be outside your web root if possible for security.
*   The `tf_idf.model` and `classifier.model` files should be accessible by your PHP application. Place them in a secure, non-web-accessible directory if possible, and adjust the `$tfIdfModelPath` and `$classifierModelPath` variables accordingly.

### 6. Error Handling and Logging

*   Implement robust error handling around file operations (`file_get_contents`, `file_put_contents`, `unserialize`) and API calls (cURL errors in `EmbeddingClient`).
*   Use PHP's logging mechanisms (`error_log`) to capture issues in production.

By following these steps, you can integrate the memory management and classification capabilities into your main PHP application, allowing it to leverage the trained models and maintain conversational context.
