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
            return "ERROR: Gemini API Key is not configured in config.php.";
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
            
            if (curl_errno($ch)) { throw new Exception("cURL Error: " . curl_error($ch)); }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) { throw new Exception("API Error: HTTP {$httpCode}. Response: " . $rawResponse); }
            curl_close($ch);

            $decodedResponse = json_decode($rawResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("JSON Decode Error."); }

            $responseText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($responseText === null) {
                throw new Exception("Could not find text part in response, check for tool calls.");
            }
            
            $this->logPrompt($prompt, $requestBody, $rawResponse);
            return $responseText;

        } catch (Exception $e) {
            $this->logPrompt($prompt, $requestBody, $rawResponse, $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }
}