<?php

// --- API Configuration ---
// IMPORTANT: For production, use environment variables instead of hardcoding keys.
define('GEMINI_API_KEY', 'AIzaSyBEkzJRNr-CvwqVCJQtcYs3bb2M-Ikq0pA');
define('MODEL_ID', 'gemini-flash-lite-latest'); // A powerful, generally available model
define('API_ENDPOINT', 'generateContent'); // Use the more reliable non-streaming endpoint

// --- File Paths ---
define('DATA_DIR', __DIR__ . '/data');
define('INTERACTIONS_FILE', DATA_DIR . '/interactions.json');
define('ENTITIES_FILE', DATA_DIR . '/entities.json');

// --- Memory Logic Configuration ---
define('REWARD_SCORE', 0.5);      // Score to add to a memory when it's recalled
define('DECAY_SCORE', 0.05);     // Score to subtract from memories not used
define('INITIAL_SCORE', 1.0);    // Starting score for a new memory
define('PRUNING_THRESHOLD', 50); // Max number of interactions before pruning old ones
define('CONTEXT_TOKEN_BUDGET', 4000); // Max "tokens" (approximated as words) to use for context
