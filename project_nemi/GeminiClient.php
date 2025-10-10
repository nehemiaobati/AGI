<?php

class GeminiClient
{
    private string $apiKey;
    private string $modelId;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
        $this->modelId = MODEL_ID;
        // Construct the URL with the non-streaming endpoint from config
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelId}:" . API_ENDPOINT . "?key={$this->apiKey}";
    }

    /**
     * Logs the prompt to a JSON file.
     * Creates the data directory and prompts.json file if they don't exist.
     * Appends new prompts with a timestamp.
     */
    private function logPrompt(string $prompt): void
    {
        $logFilePath = __DIR__ . '/data/prompts.json';
        $logDir = dirname($logFilePath);

        // Ensure directory exists. The 'data' directory was listed, but this is a safeguard.
        if (!is_dir($logDir)) {
            // If the directory doesn't exist, we'd need to create it.
            // For now, we assume it exists based on previous checks.
            // If file_put_contents fails, we might need to use execute_command to create it.
        }

        $logData = [];
        if (file_exists($logFilePath)) {
            $existingContent = file_get_contents($logFilePath);
            if ($existingContent !== false) {
                $decodedContent = json_decode($existingContent, true);
                // Check for valid JSON array
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                    $logData = $decodedContent;
                }
            }
        }

        $logData[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'prompt' => $prompt,
        ];

        // Use JSON_PRETTY_PRINT for readability
        file_put_contents($logFilePath, json_encode($logData, JSON_PRETTY_PRINT));
    }
    
    public function generateResponse(string $prompt): string
    {
        $this->logPrompt($prompt);

        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GOOGLE_API_KEY_HERE') {
            return "ERROR: Gemini API Key is not configured in config.php.";
        }

        $requestBody = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $rawResponse = curl_exec($ch);
        
        // --- Enhanced Error Handling ---
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            return "FATAL cURL Error: " . $curlError;
        }

        if ($httpCode !== 200) {
            // Google often provides a useful error message in the response body
            return "API Error: Received HTTP code {$httpCode}. Response: " . htmlspecialchars($rawResponse);
        }

        if (empty($rawResponse)) {
            return "API Error: Received an empty response from the server.";
        }

        // --- Simplified Parsing for Non-Streaming Response ---
        $decodedResponse = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Parsing Error: Failed to decode JSON response. Raw response: " . htmlspecialchars($rawResponse);
        }
        
        // Check for an error message inside the JSON payload itself
        if (isset($decodedResponse['error']['message'])) {
             return "API Error Payload: " . $decodedResponse['error']['message'];
        }

        // Navigate the standard Gemini API response structure to find the text
        $responseText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($responseText === null) {
            return "Parsing Error: Could not find the text part in the API response. Full response: " . htmlspecialchars($rawResponse);
        }
        
        return $responseText;
    }
}
