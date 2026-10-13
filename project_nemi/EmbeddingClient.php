<?php

/**
 * Handles the generation of vector embeddings via the Gemini API.
 * This class is decoupled from the main content generation client
 * to allow for easy swapping with other embedding providers (e.g., local Ollama).
 */
class EmbeddingClient
{
    private string $apiKey;
    private string $modelId;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
        $this->modelId = EMBEDDING_MODEL_ID;
        $keyParam = (!empty($this->apiKey) && $this->apiKey !== 'YOUR_GEMINI_API_KEY_HERE') ? "?key=" . urlencode($this->apiKey) : "";
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelId}:embedContent" . $keyParam;
    }

    /**
     * Converts a string of text into a vector embedding.
     *
     * @param string $text The text to embed.
     * @return array|null The vector as an array of floats, or null on error.
     */
    public function getEmbedding(string $text): ?array
    {
        if (!ENABLE_EMBEDDINGS) {
            return null; // Return null if embeddings are disabled in config
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $ch = curl_init();
        try {
            $requestBody = [
                'model'   => "models/{$this->modelId}",
                'content' => ['parts' => [['text' => $trimmed]]]
            ];

            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $rawResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("cURL Error getting embedding: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new Exception("API Error ({$httpCode}) getting embedding: " . $rawResponse);
            }

            $decodedResponse = json_decode($rawResponse, true);
            $embedding = $decodedResponse['embedding']['values'] ?? null;

            if ($embedding === null || !is_array($embedding)) {
                error_log("Failed to find embedding in API response: " . $rawResponse);
                throw new Exception("Could not find embedding values in API response.");
            }

            return $embedding;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }
}
