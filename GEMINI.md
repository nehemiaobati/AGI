# Project NEMI: Gemini-Powered AI Agent

## Project Overview

Project NEMI is an advanced PHP-based AI agent designed to understand user input, manage conversational memory, and classify user intent. It leverages the Google Gemini API for generating vector embeddings and generative content. The project incorporates natural language processing (NLP) techniques for text preprocessing and classification, enabling a hybrid search mechanism for memory retrieval. Its core focus is on developing a dynamic, persistent, and self-organizing memory system, mimicking aspects of human memory.

## Key Technologies

*   **PHP:** Core programming language (PHP 7.4 or higher).
*   **NlpTools Library:** Used for tokenization, stemming, stop word removal, and text classification (TF-IDF, Multinomial Naive Bayes).
*   **Google Gemini API:** Utilized for generating vector embeddings (`text-embedding-004`) and for generative content (`gemini-2.5-flash-lite`).
*   **cURL:** For making HTTP requests to the Gemini API.
*   **Bootstrap 5:** For frontend styling (e.g., in `test_index.php`).
*   **Composer:** For dependency management (`nlp-tools/nlp-tools`).

## Architecture Highlights

*   **`config.php`**: Centralized configuration for API keys, model IDs, file paths, and various parameters governing memory management, hybrid search, and NLP.
*   **`EmbeddingClient.php`**: A dedicated client for interacting with the Gemini API to convert text into numerical vector embeddings. Designed for easy swapping with other embedding providers.
*   **`GeminiClient.php`**: Communicates with the Google Gemini API for content generation.
*   **`MemoryManager.php`**: Manages the AI's long-term and short-term memory. It stores user interactions, extracts and updates entities, and retrieves relevant context using a hybrid search approach (combining semantic vector search and lexical keyword search). It also includes mechanisms for memory pruning and a time-aware system prompt.
*   **`index.php`**: The main orchestrator, handling user requests, coordinating memory and AI client interactions, building prompts, and rendering the UI.
*   **`train.php`**: A command-line script responsible for training the text classification models. It preprocesses text from `training_data.csv` and trains a Multinomial Naive Bayes classifier using TF-IDF features.
*   **`test_index.php`**: A web-based interface for testing the trained text classifier. It loads the serialized models (`tf_idf.model`, `classifier.model`) and allows users to input text to see the predicted intent.
*   **`training_data.csv`**: The dataset used for training the text classification model.
*   **`classifier.model` & `tf_idf.model`**: Serialized PHP objects representing the trained classifier and TF-IDF feature factory, respectively.

## Building and Running

### Prerequisites

*   PHP (version 7.4 or higher recommended)
*   cURL extension enabled for PHP
*   Google Gemini API Key
*   Composer for PHP dependencies. Run `composer install` in the project root if `vendor/` directory is not present.

### Configuration

1.  **Replace API Key:** Open `project_nemi/config.php` and replace `'YOUR_GEMINI_API_KEY_HERE'` with your actual Gemini API key.
2.  **Enable Embeddings (Optional):** By default, embeddings are enabled (`ENABLE_EMBEDDINGS` is `true`). To disable semantic search and rely solely on keyword search, set this to `false`.
3.  **Tune Hybrid Search (Optional):** Adjust `HYBRID_SEARCH_ALPHA` in `project_nemi/config.php` to balance keyword and semantic search. A value of `0.5` provides a balanced approach.

### Training the Classification Models

To train the text classification models, execute the `train.php` script from the `project_nemi/test` directory:

```bash
php project_nemi/test/train.php
```

This will generate `tf_idf.model` and `classifier.model` in the `project_nemi/test` directory.

### Testing the Text Classifier

To test the text classifier via the web interface, you need a PHP-enabled web server.

1.  **Start PHP's built-in web server (for development) from the project root:**
    ```bash
    php -S localhost:8000 -t project_nemi
    ```
2.  **Access in browser:** Open your web browser and navigate to `http://localhost:8000/test/test_index.php`.

### Running the Main AI Application

1.  **Start PHP's built-in web server (for development) from the project root:**
    ```bash
    php -S localhost:8000 -t project_nemi
    ```
2.  **Access in browser:** Open your web browser and navigate to `http://localhost:8000/index.php`.
    Interact with the AI by typing your queries in the provided text area.

## Development Conventions

*   **PHP Version:** Requires PHP 7.4 or higher due to the use of arrow functions (`fn`).
*   **Dependencies:** Managed via `composer`. Ensure `composer install` has been run in the project root.
*   **Configuration:** All major configurable parameters are defined as constants in `config.php`.
*   **Error Reporting:** Enabled in development scripts (`train.php`, `test_index.php`).
*   **Code Style:** Object-oriented approach with classes for specific functionalities (e.g., `EmbeddingClient`, `MemoryManager`).
*   **NLP Preprocessing:** Consistent text preprocessing (tokenization, stop word removal, stemming) is applied during both model training and inference.
*   **Architectural Pattern:** Adheres to a "Brain Analogy" architectural pattern (Orchestration, Memory System, Communication, Rules & Personality).
*   **Documentation:** Strict versioning, mandatory changelog, and code synchronization rules are outlined in the `README.md` for future document updates.
