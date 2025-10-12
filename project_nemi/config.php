<?php

// --- API Configuration ---
define('GEMINI_API_KEY', 'AIzaSyBEkzJRNr-CvwqVCJQtcYs3bb2M-Ikq0pA');
define('MODEL_ID', 'gemini-flash-lite-latest'); // A powerful, generally available model
define('API_ENDPOINT', 'generateContent'); // Use the more reliable non-streaming endpoint

// --- NEW: Embedding Configuration ---
define('ENABLE_EMBEDDINGS', true); // Master switch to enable/disable vector search.
define('EMBEDDING_MODEL_ID', 'text-embedding-004'); // Specialized model for embeddings.

// --- File Paths ---
define('DATA_DIR', __DIR__ . '/data');
define('INTERACTIONS_FILE', DATA_DIR . '/interactions.json');
define('ENTITIES_FILE', DATA_DIR . '/entities.json');
define('PROMPTS_LOG_FILE', DATA_DIR . '/prompts.json');

// --- Memory Logic Configuration ---
define('REWARD_SCORE', 0.5);
define('DECAY_SCORE', 0.05);
define('INITIAL_SCORE', 1.0);
define('PRUNING_THRESHOLD', 500);
define('CONTEXT_TOKEN_BUDGET', 4000);

// --- NEW: Hybrid Search Tuning ---
// Balances between keyword (relevance_score) and semantic (similarity) search.
// 0.0 = Pure keyword search. 1.0 = Pure semantic search. 0.5 is balanced.
define('HYBRID_SEARCH_ALPHA', 0.5);
// The number of top semantic results to fetch before ranking.
define('VECTOR_SEARCH_TOP_K', 15);


// --- Advanced Scoring & Relationships ---
define('NOVELTY_BONUS', 0.3);
define('RELATIONSHIP_STRENGTH_INCREMENT', 0.1);
define('RECENT_TOPIC_DECAY_MODIFIER', 0.1);
define('USER_FEEDBACK_REWARD', 0.5);
define('USER_FEEDBACK_PENALTY', -0.5);
