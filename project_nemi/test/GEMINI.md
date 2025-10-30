# Project NEMI: Gemini-Powered AI Agent

## Project Overview

Project NEMI is a PHP-based AI agent designed to understand user input, manage conversational memory, and classify user intent. It leverages the Google Gemini API for generating vector embeddings and potentially for content generation. The project incorporates natural language processing (NLP) techniques for text preprocessing and classification, enabling a hybrid search mechanism for memory retrieval.

## Key Technologies

*   **PHP:** Core programming language.
*   **NlpTools Library:** Used for tokenization, stemming, stop word removal, and text classification (TF-IDF, Multinomial Naive Bayes).
*   **Google Gemini API:** Utilized for generating vector embeddings (`text-embedding-004`) and potentially for generative content (`gemini-1.5-flash-latest` or `gemini-2.5-flash-lite`).
*   **cURL:** For making HTTP requests to the Gemini API.
*   **Bootstrap 5:** For the frontend styling of the `test_index.php` web interface.

## Architecture Highlights

*   **`config.php`**: Centralized configuration for API keys, model IDs, file paths, and various parameters governing memory management, hybrid search, and NLP.
*   **`EmbeddingClient.php`**: A dedicated client for interacting with the Gemini API to convert text into numerical vector embeddings. Designed for easy swapping with other embedding providers.
*   **`MemoryManager.php`**: Manages the AI's long-term and short-term memory. It stores user interactions, extracts and updates entities, and retrieves relevant context using a hybrid search approach (combining semantic vector search and lexical keyword search). It also includes mechanisms for memory pruning and a time-aware system prompt.
*   **`train.php`**: A command-line script responsible for training the text classification models. It preprocesses text from `training_data.csv` and trains a Multinomial Naive Bayes classifier using TF-IDF features.
*   **`test_index.php`**: A web-based interface for testing the trained text classifier. It loads the serialized models (`tf_idf.model`, `classifier.model`) and allows users to input text to see the predicted intent.
*   **`training_data.csv`**: The dataset used for training the text classification model.
*   **`classifier.model` & `tf_idf.model`**: Serialized PHP objects representing the trained classifier and TF-IDF feature factory, respectively.

## Building and Running

### Training the Classification Models

To train the text classification models, execute the `train.php` script from your terminal:

```bash
php train.php
```

This will generate `tf_idf.model` and `classifier.model` in the current directory.

### Testing the Text Classifier

To test the text classifier via the web interface, you need a PHP-enabled web server (e.g., Apache, Nginx, or PHP's built-in server).

1.  **Start PHP's built-in web server (for development):**
    ```bash
    php -S localhost:8000
    ```
2.  **Access in browser:** Open your web browser and navigate to `http://localhost:8000/test_index.php`.

### Running the AI Agent (Core Logic)

The core AI agent logic resides within `MemoryManager.php` and `EmbeddingClient.php`. These classes are designed to be integrated into a larger application that handles user input and generates AI responses. There isn't a single "run" command for the entire agent as it's a set of components.

## Development Conventions

*   **PHP Version:** Requires PHP 7.4 or higher due to the use of arrow functions (`fn`).
*   **Dependencies:** Managed via `composer` (indicated by `vendor/autoload.php` in `MemoryManager.php` and `train.php`). Ensure `composer install` has been run in the project root.
*   **Configuration:** All major configurable parameters are defined as constants in `config.php`.
*   **Error Reporting:** Enabled in development scripts (`train.php`, `test_index.php`).
*   **Code Style:** Object-oriented approach with classes for specific functionalities (e.g., `EmbeddingClient`, `MemoryManager`).
*   **NLP Preprocessing:** Consistent text preprocessing (tokenization, stop word removal, stemming) is applied during both model training and inference.
