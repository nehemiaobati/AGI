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
define('PROMPTS_LOG_FILE', DATA_DIR . '/prompts.json'); // Log file for prompts

// --- Memory Logic V4 Configuration ---
define('REWARD_SCORE', 0.5);          // Base score added to a memory when it's recalled
define('DECAY_SCORE', 0.05);         // Base score subtracted from unused memories
define('INITIAL_SCORE', 1.0);        // Starting score for a new memory
define('PRUNING_THRESHOLD', 500);    // Max interactions before pruning
define('CONTEXT_TOKEN_BUDGET', 4000); // Max words for context

// --- NEW: Advanced Scoring & Relationships ---
define('NOVELTY_BONUS', 0.3);        // Extra reward for interactions that introduce a new entity
define('RELATIONSHIP_STRENGTH_INCREMENT', 0.1); // How much to strengthen a link between two entities
define('RECENT_TOPIC_DECAY_MODIFIER', 0.1); // Multiplier for decay (0.1 = 90% less decay for recent topics)
define('USER_FEEDBACK_REWARD', 0.5);   // Score boost for a "Good Answer"
define('USER_FEEDBACK_PENALTY', -0.5); // Score penalty for a "Bad Answer"






