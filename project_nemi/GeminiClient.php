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
        $keyParam = (!empty($this->apiKey) && $this->apiKey !== 'YOUR_GEMINI_API_KEY_HERE') ? "?key=" . urlencode($this->apiKey) : "";
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelId}:" . API_ENDPOINT . $keyParam;
    }

    private function logPrompt(string $prompt, array $requestBody, string $rawResponse, ?string $error = null): void
    {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0775, true);
        }

        $logData = [];
        if (file_exists(PROMPTS_LOG_FILE)) {
            $content = file_get_contents(PROMPTS_LOG_FILE);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $logData = $decoded;
            }
        }
        $decodedResponse = json_decode($rawResponse, true);

        $toolNames = [];
        foreach ($requestBody['tools'] ?? [] as $tool) {
            if (is_array($tool)) {
                $toolNames = array_merge($toolNames, array_keys($tool));
            }
        }

        $logEntry = [
            'timestamp' => date('c'),
            'request' => [
                'model' => $this->modelId,
                'tools' => array_values(array_unique($toolNames)),
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
        file_put_contents(PROMPTS_LOG_FILE, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function generateResponse(string $prompt, array $enabledTools = []): string
    {
        $rawResponse = '';
        $requestBody = [];

        try {
            $requestBody = [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => 8192],
            ];

            $requestTools = [];
            if (in_array('googleSearch', $enabledTools, true)) {
                $requestTools[] = ['googleSearch' => new stdClass()];
            }

            if (!empty($requestTools)) {
                $requestBody['tools'] = $requestTools;
            }

            $ch = curl_init();
            try {
                curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $rawResponse = curl_exec($ch);

                if (curl_errno($ch)) {
                    throw new Exception("cURL Error: " . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    throw new Exception("API Error: HTTP {$httpCode}. Response: " . $rawResponse);
                }
            } finally {
                curl_close($ch);
            }

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
            $userFriendlyError = "An unexpected error occurred while communicating with the AI. " . $detailedError;

            // Attempt to parse a more specific error message from the API's raw response
            if (!empty($rawResponse)) {
                $decodedError = json_decode($rawResponse, true);
                if (isset($decodedError['error']['message'])) {
                    $apiErrorMsg = $decodedError['error']['message'];
                    $detailedError = "API Error: " . $apiErrorMsg;
                    $userFriendlyError = "AI Service Error: " . $apiErrorMsg;
                }
            }

            // Log the detailed, technical error and return the safe, deterministic one.
            $this->logPrompt($prompt, $requestBody, $rawResponse, $detailedError);
            return "ERROR: " . $userFriendlyError;
        }
    }
}
