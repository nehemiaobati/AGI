# Project NEMI v5.1

## Executive Summary

Project NEMI is an advanced AI initiative focused on developing a dynamic, persistent, and self-organizing memory system. It leverages a Retrieval-Augmented Generation (RAG) framework and a Hybrid Search capability, fusing traditional keyword retrieval with modern semantic vector search. The system is designed to mimic key aspects of human memory, including prioritizing relevant information, establishing connections between concepts, and gradually "forgetting" outdated or irrelevant data, providing a robust foundation for a memory-driven AGI.

## Architecture Evolution

The architecture of Project NEMI has progressed through several key versions:

*   **V0.0: Foundational Concept - The Simple Log:** Began as a straightforward objective to create a persistent memory by logging all user and AI interactions in a structured JSON format. Suffered from limitations in scalability, retrieval, and relational context.
*   **V2.0: A Multi-Layered Memory Architecture:** Redesigned to function more like a relational database with four components: Sessions, Interactions, Entities (Knowledge Graph), and Users.
*   **V3.0: Reinforcement-Based Memory and RAG:** Introduced memory relevance scoring, reinforcement (reward) for used memories, forgetting (decay/penalty), and Retrieval-Augmented Generation (RAG).
*   **V4.0: Practical Implementation and the "Brain Analogy":** Translated theoretical concepts into a functional PHP application using the "Brain Analogy" architectural pattern (Orchestration, Memory System, Communication, Rules & Personality).
*   **V5.0: Hybrid Search and Decoupled Architecture:** Moved beyond lexical search to combine keyword and semantic search. Introduced vector embeddings and a decoupled Embedding Client.

## Key Features (v5.1)

*   **Hybrid Search System:** Combines keyword-based lexical search with vector-based semantic search for superior contextual recall.
*   **Decoupled Embedding Logic:** A new `EmbeddingClient.php` isolates all embedding-related API calls, allowing for easy adaptation to other embedding providers.
*   **Configurable Search Behavior:** `config.php` includes a master switch (`ENABLE_EMBEDDINGS`) and tuning parameters (`HYBRID_SEARCH_ALPHA`) to control search behavior.
*   **Automatic Semantic Encoding:** New interactions automatically generate vector embeddings for semantic searchability.
*   **Robust Error Handling:** Enhanced error handling in `GeminiClient.php` provides user-friendly messages and detailed logs.

## Setup and Running

### Prerequisites

*   PHP (version 7.4 or higher recommended)
*   cURL extension enabled for PHP
*   Google Gemini API Key

### Configuration

1.  **Replace API Key:** Open `config.php` and replace `'YOUR_GEMINI_API_KEY_HERE'` with your actual Gemini API key.
2.  **Enable Embeddings (Optional):** By default, embeddings are enabled (`ENABLE_EMBEDDINGS` is `true`). To disable semantic search and rely solely on keyword search, set this to `false`.
3.  **Tune Hybrid Search (Optional):** Adjust `HYBRID_SEARCH_ALPHA` in `config.php` to balance keyword and semantic search. A value of `0.5` provides a balanced approach.

### Running the Application

1.  Ensure you have a local web server environment (e.g., Apache, Nginx, or PHP's built-in server).
2.  Navigate to the project directory in your terminal.
3.  Start the PHP built-in server: `php -S localhost:8000` (or your preferred port).
4.  Open your web browser and go to `http://localhost:8000` (or the address you configured).
5.  Interact with the AI by typing your queries in the provided text area.

## Core Components

*   **`index.php` (Conscious Thought):** The main orchestrator. Handles user requests, coordinates memory and AI client interactions, builds prompts, and renders the UI.
*   **`MemoryManager.php` (The Memory System):** Manages all memory operations, including loading, saving, hybrid retrieval, relevance scoring, and pruning.
*   **`GeminiClient.php` (Senses & Voice):** Communicates with the Google Gemini API for content generation.
*   **`EmbeddingClient.php` (The Conceptual Librarian):** Generates text embeddings (vectors) for semantic search.
*   **`config.php` (Personality & Rules):** Centralized configuration file for API keys, paths, and memory/search logic constants.

## Source Code

The complete source code for the core components (`config.php`, `EmbeddingClient.php`, `GeminiClient.php`, `index.php`, `MemoryManager.php`) is included within this project.

## Nota Bene: Rules for Future Document Updates

AI and/or agents must adhere to the following rules to maintain the consistency and integrity of this document.

1.  **Strict Versioning:** All modifications, regardless of size, mandate a version increment.
    *   Patch (e.g., 4.2 -> 4.2.1): Typographical corrections, code comments, non-functional changes.
    *   Minor (e.g., 4.2 -> 4.3): New features, changes in logic, enhancements that are backward-compatible.
    *   Major (e.g., 4.0 -> 5.0): Significant architectural changes or rewrites that are not backward-compatible.
2.  **Mandatory Changelog:** Every new version must be documented in a "Revision History" section added to the top of the document. The entry must include the new version number, date, author, and a concise, bulleted list of all changes made.
3.  **Code Synchronization:** The source code presented within this document must be an exact and complete representation of the functional source code files. Any change to a .php file must be immediately and accurately reflected in its corresponding code block herein.
4.  **Preserve Architecture:** New functionality must be integrated into the existing "Brain Analogy" component structure (index.php, MemoryManager.php, GeminiClient.php, config.php). If a new component is necessary, its purpose and interaction with the existing components must be explicitly defined.
5.  **Centralize Configuration:** All new constants, thresholds, or configurable parameters must be added exclusively to config.php. No hardcoded "magic values" are permitted in the logic of other files.
6.  **Maintain Structural Integrity:** The existing numbered heading structure of this document must be preserved. New content should be added as subsections within the relevant existing section. Do not alter the primary document flow.
7.  **Direct and Specific Language:** All descriptions of changes must be technical, direct, and unambiguous. Clearly state what was changed, why it was changed, and reference the specific function or component affected.
