# Project NEMI — Hybrid Memory System for AGI

**Project NEMI** is an experimental PHP application that implements a persistent, self‑organizing memory system using Retrieval‑Augmented Generation (RAG) and hybrid search (keyword + semantic). It demonstrates how to build a memory‑driven AI that learns from conversations over time.

## 🧠 Core Concepts

1. **Hybrid Search**: Combines lexical (keyword) and vector‑based semantic retrieval for highly relevant context.
2. **Reinforcement Scoring**: Memories accessed frequently gain relevance; unused memories decay and are eventually pruned.
3. **Decoupled Architecture**: Clear separation between orchestration (`index.php`), memory management, AI client, and embedding logic.
4. **Configurable Behavior**: Toggle embeddings on/off, adjust hybrid search alpha, and control retention policies via `config.php`.

## 📦 Components

| File | Role |
|------|------|
| `index.php` | Orchestrator: handles requests, builds prompts, renders UI |
| `MemoryManager.php` | Manages storage, retrieval, scoring, and pruning |
| `GeminiClient.php` | Communicates with Google Gemini API |
| `EmbeddingClient.php` | Generates vector embeddings for semantic search |
| `config.php` | Central configuration (API keys, search parameters) |

## ⚙️ Setup

1. **Prerequisites**
   - PHP 7.4+
   - cURL extension enabled
   - Google Gemini API key

2. **Configure**
   Edit `config.php`:
   ```php
   define('GEMINI_API_KEY', 'YOUR_KEY_HERE');
   define('ENABLE_EMBEDDINGS', true);
   define('HYBRID_SEARCH_ALPHA', 0.5);
   ```

3. **Run**
   ```bash
   php -S localhost:8000
   ```
   Visit `http://localhost:8000` and start chatting.

## 📖 Documentation

`project_nemi.txt` contains the full technical specification (Version 5.1) with architecture evolution, implementation details, and usage notes.

## 🔬 Research Context

Project NEMI explores how to give AI systems long‑term memory that mimics human forgetting and reinforcement. It is an educational resource for developers interested in RAG, vector embeddings, and knowledge graphs.

## 📄 License

MIT

---

*Not intended for production use without further hardening.*
