<?php

// --- API Configuration ---
// The secret key required to authenticate with the Google Gemini API.
// CRITICAL: Replace 'YOUR_GEMINI_API_KEY_HERE' with your actual key.
define('GEMINI_API_KEY', 'AIzaSyBEkzJRNr-CvwqVCJQtcYs3bb2M-Ikq0pA');

// Specifies the exact generative model to use for creating responses.
// 'gemini-1.5-flash-latest' is a fast and capable model suitable for chat.
define('MODEL_ID', 'gemini-2.5-flash-lite');

// The specific Gemini API function to call. 'generateContent' is the standard
// endpoint for getting a complete response from the model.
define('API_ENDPOINT', 'generateContent');


// --- Embedding Configuration ---
// A master switch to turn the entire vector/semantic search system on or off.
// Set to 'false' to revert to a purely keyword-based search.
define('ENABLE_EMBEDDINGS', true);

// The specialized model used for converting text into numerical vectors (embeddings).
// 'text-embedding-004' is optimized for this task and is highly efficient.
define('EMBEDDING_MODEL_ID', 'text-embedding-004');


// --- NLP Configuration ---
define('NLP_STOP_WORDS', [
    'a', 'an', 'the', 'is', 'in', 'it', 'of', 'for', 'on', 'what', 'were',
    'my', 'that', 'we', 'to', 'user', 'note', 'system', 'please', 'and'
    // Add other words as needed
]);


// --- File Paths ---
// Defines the directory where all persistent data (memory files) will be stored.
define('DATA_DIR', __DIR__ . '/data');

// The filename for the JSON file that stores all conversation history.
define('INTERACTIONS_FILE', DATA_DIR . '/interactions.json');

// The filename for the JSON file that acts as the knowledge graph, storing
// information about concepts and keywords.
define('ENTITIES_FILE', DATA_DIR . '/entities.json');

// The filename for the log that records every prompt sent to the Gemini API.
// This is used for debugging and performance analysis.
define('PROMPTS_LOG_FILE', DATA_DIR . '/prompts.json');


// --- Memory Logic Configuration ---
// The amount to increase a memory's 'relevance_score' when it is successfully
// used as context for a good response. Higher values make the AI "learn" faster.
define('REWARD_SCORE', 0.5);

// The base amount to decrease a memory's 'relevance_score' during each new
// interaction. This simulates "forgetting" and prevents old, unused memories from cluttering the system.
define('DECAY_SCORE', 0.05);

// The starting 'relevance_score' for any new memory created.
define('INITIAL_SCORE', 1.0);

// The maximum number of interactions to keep in memory. When this number is
// exceeded, the system will delete the interactions with the lowest relevance scores.
define('PRUNING_THRESHOLD', 500);

// The maximum number of tokens to include in the context sent to the AI.
// This prevents the prompt from becoming too large and expensive.
define('CONTEXT_TOKEN_BUDGET', 4000);

// --- NEW: Short-Term Memory Configuration ---
// The number of most recent interactions to ALWAYS include in the context.
// Set to 1 to guarantee the AI remembers the very last thing said.
// Set to 2 or 3 to give it a slightly better conversational short-term memory.
// Set to 0 to disable and rely purely on the hybrid search.
define('FORCED_RECENT_INTERACTIONS', 2);


// --- Hybrid Search Tuning ---
// The core dial that balances keyword search against vector search.
// 0.0 = 100% keyword-based. The AI will only find exact matches.
// 1.0 = 100% semantic-based. The AI will only find conceptually similar ideas.
// 0.5 = A balanced mix, providing both precision and conceptual relevance.
define('HYBRID_SEARCH_ALPHA', 0.5);

// The number of top results to fetch from the semantic (vector) search stage.
// A higher number allows the fusion algorithm to consider more conceptually related
// memories, but may slightly slow down the retrieval process.
define('VECTOR_SEARCH_TOP_K', 15);


// --- Advanced Scoring & Relationships ---
// An extra 'relevance_score' bonus given to a new memory if it contains a
// keyword (entity) the AI has never seen before. Encourages learning new topics.
define('NOVELTY_BONUS', 0.3);

// The amount to increase the strength of the connection between two keywords
// every time they appear in the same user prompt.
define('RELATIONSHIP_STRENGTH_INCREMENT', 0.1);

// A multiplier that reduces the 'DECAY_SCORE' for memories related to the
// current conversation topic. This helps the AI "stay on topic" by forgetting
// relevant memories more slowly.
define('RECENT_TOPIC_DECAY_MODIFIER', 0.1);

// The amount to increase the 'relevance_score' of an interaction and its
// context when the user clicks the "Good" feedback button.
define('USER_FEEDBACK_REWARD', 0.5);

// The amount to decrease the 'relevance_score' of an interaction and its
// context when the user clicks the "Bad" feedback button.
define('USER_FEEDBACK_PENALTY', -0.5);
