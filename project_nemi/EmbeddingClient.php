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
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelId}:embedContent?key={$this->apiKey}";
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

        try {
            $requestBody = [
                'model'   => "models/{$this->modelId}",
                'content' => ['parts' => [['text' => $text]]]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $rawResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("cURL Error getting embedding: " . curl_error($ch));
            }
            curl_close($ch);

            $decodedResponse = json_decode($rawResponse, true);
            $embedding = $decodedResponse['embedding']['values'] ?? null;

            if ($embedding === null) {
                // Log the actual response for debugging
                error_log("Failed to find embedding in API response: " . $rawResponse);
                throw new Exception("Could not find embedding in API response.");
            }

            return $embedding;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }
}