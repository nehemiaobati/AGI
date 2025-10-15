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

    private function logPrompt(string $prompt, array $requestBody, string $rawResponse, ?string $error = null): void
    {
        if (!file_exists(PROMPTS_LOG_FILE)) {
            file_put_contents(PROMPTS_LOG_FILE, '[]');
        }
        $logData = json_decode(file_get_contents(PROMPTS_LOG_FILE), true);
        $decodedResponse = json_decode($rawResponse, true);

        $logEntry = [
            'timestamp' => date('c'),
            'request' => [
                'model' => $this->modelId,
                'tools' => array_keys(array_column($requestBody['tools'] ?? [], null, 0)),
                'prompt_text' => $prompt
            ],
            'response' => [
                'token_usage' => $decodedResponse['usageMetadata'] ?? null,
                'finish_reason' => $decodedResponse['candidates'][0]['finishReason'] ?? null,
                'response_text' => $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? null,
            ],
            'error' => $error
        ];

        $logData[] = $logEntry;
        file_put_contents(PROMPTS_LOG_FILE, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function generateResponse(string $prompt, array $enabledTools = []): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            return "ERROR: Gemini API Key is not configured in config.php. Please set a valid key to proceed.";
        }

        $rawResponse = '';
        $requestBody = [];

        try {
            $requestBody = [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => 8192],
            ];

            $requestTools = [];
            if (in_array('googleSearch', $enabledTools)) {
                $requestTools[] = ['googleSearch' => new stdClass()];
            }

            if (!empty($requestTools)) {
                $requestBody['tools'] = $requestTools;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $rawResponse = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new Exception("API Error: HTTP {$httpCode}. Response: " . $rawResponse);
            }
            curl_close($ch);

            $decodedResponse = json_decode($rawResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON Decode Error for API response.");
            }

            $responseText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($responseText === null) {
                throw new Exception("Could not find text part in the API response. The response may contain a tool call or be empty.");
            }

            $this->logPrompt($prompt, $requestBody, $rawResponse);
            return $responseText;
        } catch (Exception $e) {
            $detailedError = $e->getMessage();
            $userFriendlyError = "An unexpected error occurred while communicating with the AI. Please check the system logs for more details.";

            // Attempt to parse a more specific error message from the API's raw response
            if (!empty($rawResponse)) {
                $decodedError = json_decode($rawResponse, true);
                if (isset($decodedError['error']['message'])) {
                    $apiErrorMsg = $decodedError['error']['message'];
                    $detailedError = "API Error: " . $apiErrorMsg; // Overwrite for cleaner logs

                    // Tailor the user-facing message for common, understandable errors
                    if (isset($decodedError['error']['status'])) {
                        switch ($decodedError['error']['status']) {
                            case 'INVALID_ARGUMENT':
                                $userFriendlyError = "There was an issue with the request sent to the AI, possibly due to a configuration problem or invalid input.";
                                break;
                            case 'PERMISSION_DENIED':
                                $userFriendlyError = "Authentication with the AI service failed. Please verify that the API key is correct and has the necessary permissions.";
                                break;
                        }
                    }
                }
            }

            // Log the detailed, technical error and return the safe, user-friendly one.
            $this->logPrompt($prompt, $requestBody, $rawResponse, $detailedError);
            return "ERROR: " . $userFriendlyError;
        }
    }
}
