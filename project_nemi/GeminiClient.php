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
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelId}:" . API_ENDPOINT . "?key={$this->apiKey}";
    }
    
    private function logPrompt(string $prompt, int $maxOutputTokens, ?int $tokenUsage = null): void
    {
        $logFilePath = __DIR__ . '/data/prompts.json';
        $logDir = dirname($logFilePath);

        if (!is_dir($logDir)) {
            // Error handling or directory creation would be needed in a production scenario
        }

        $logData = [];
        if (file_exists($logFilePath)) {
            $existingContent = file_get_contents($logFilePath);
            if ($existingContent !== false) {
                $decodedContent = json_decode($existingContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                    $logData = $decodedContent;
                }
            }
        }

        $logData[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'prompt' => $prompt,
            'maxOutputTokens' => $maxOutputTokens,
            'responseTokenCount' => $tokenUsage,
        ];

        file_put_contents($logFilePath, json_encode($logData, JSON_PRETTY_PRINT));
    }
    
    public function generateResponse(string $prompt, array $enabledTools = ['googleSearch']): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GOOGLE_API_KEY_HERE') {
            return "ERROR: Gemini API Key is not configured in config.php.";
        }
        
        $maxOutputTokens = 4096; // Increased token limit for potentially larger tool outputs
        $tokenUsage = null;
        $responseMessage = "";

        try {
            // --- Start of the corrected request body ---
            $requestBody = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $prompt]]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $maxOutputTokens,
                ],
            ];

            // Dynamically build the tools array
            $requestTools = [];
            if (in_array('googleSearch', $enabledTools)) {
                $requestTools[] = ['googleSearch' => new stdClass()];
            }
            if (in_array('urlContext', $enabledTools)) {
                $requestTools[] = ['urlContext' => new stdClass()];
            }

            // Add the tools array to the request body if any tools are enabled.
            // NO 'tool_config' is needed for built-in tools.
            if (!empty($requestTools)) {
                $requestBody['tools'] = $requestTools;
            }
            // --- End of the corrected request body ---
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!empty($curlError)) {
                throw new Exception("FATAL cURL Error: " . $curlError);
            }
            if ($httpCode !== 200) {
                $formattedError = $rawResponse;
                $decodedError = json_decode($rawResponse, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formattedError = json_encode($decodedError, JSON_PRETTY_PRINT);
                }
                throw new Exception("API Error: Received HTTP code {$httpCode}. Response: <pre>" . htmlspecialchars($formattedError) . "</pre>");
            }
            if (empty($rawResponse)) {
                throw new Exception("API Error: Received an empty response from the server.");
            }

            $decodedResponse = json_decode($rawResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Parsing Error: Failed to decode JSON response. Raw response: " . htmlspecialchars($rawResponse));
            }
            if (isset($decodedResponse['error']['message'])) {
                 throw new Exception("API Error Payload: " . $decodedResponse['error']['message']);
            }

            // The correct response text is now in this location after a successful tool call.
            $responseText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($responseText === null) {
                throw new Exception("Parsing Error: Could not find the text part in the API response. Full response: " . htmlspecialchars(json_encode($decodedResponse, JSON_PRETTY_PRINT)));
            }
            
            $tokenUsage = $decodedResponse['usageMetadata']['totalTokenCount'] ?? null;
            $responseMessage = $responseText;

        } catch (Exception $e) {
            $responseMessage = "Error: " . $e->getMessage();
        }

        $this->logPrompt($prompt, $maxOutputTokens, $tokenUsage);

        return $responseMessage;
    }
}
