# ChromaDB Knowledge Base Service

A FastAPI service that provides vector search capabilities for the AI Chat knowledge base using ChromaDB.

## Requirements

- Python 3.9+ (miniconda py39 environment)
- ChromaDB 0.6+
- FastAPI
- PyPDF2 (for PDF parsing)
- python-docx (for Word document parsing)

## Directory Structure

```
services/chroma/
├── main.py           # FastAPI application
├── config.py         # Configuration settings
├── requirements.txt  # Python dependencies
├── start.sh          # Startup script
└── README.md         # This file

storage/knowledge/
├── uploads/          # Uploaded files (auto-created)
└── chroma_db/        # ChromaDB persistent storage (auto-created)
```

## Installation

Install dependencies into the Python environment you will use to run the service. If needed:

```bash
python3 -m pip install -r requirements.txt
```

## Running the Service

### Option 1: Using the startup script

```bash
cd /var/www/html/AIChatforChildren/services/chroma
./start.sh
```

### Option 2: Direct Python execution

```bash
cd /var/www/html/AIChatforChildren/services/chroma
python3 main.py
```

The service will start on `http://127.0.0.1:4001`

`start.sh` and the admin rebuild task both support `CHROMA_PYTHON_PATH`, but they no
longer depend on a machine-specific absolute path. If `CHROMA_PYTHON_PATH` is empty
or invalid, the app will try common Python commands and virtualenv locations
automatically, then choose the first runtime that can import `chromadb`, `fastapi`,
and `pymysql`.

## Rebuilding Metadata After Migration

If `storage/knowledge/uploads/` was copied to a new environment but the MySQL
tables `kb_documents` / `kb_chunks` are empty or incomplete, run:

```bash
cd /path/to/AIChatforChildren/services/chroma
python3 rebuild_kb.py
```

Useful flags:

```bash
python3 rebuild_kb.py --dry-run
python3 rebuild_kb.py --force
```

What it does:

- scans `storage/knowledge/uploads/`
- restores missing `kb_documents`
- rebuilds `kb_chunks`
- upserts vectors back into Chroma

Notes:

- it reuses the current parser/chunking/classification pipeline, so recovered
  metadata is rebuilt from file contents
- if the original MySQL rows are gone, the exact old title/original filename
  may be unrecoverable; the script will infer a title from the document text

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Health check |
| GET | `/api/health` | Detailed health check |
| POST | `/api/upload` | Upload a file |
| GET | `/api/files` | List all uploaded files |
| DELETE | `/api/files/{file_id}` | Delete a file |
| PUT | `/api/files/{file_id}/rename` | Rename a file |
| GET | `/api/search?query=...` | Search the knowledge base |
| GET | `/api/context?query=...` | Get context for AI chat |

## Supported File Types

- PDF (.pdf)
- Plain Text (.txt)
- Markdown (.md)
- Microsoft Word (.doc, .docx)

## Configuration

Edit `config.py` to change:

- `PORT`: Service port (default: 4001)
- `MAX_FILE_SIZE`: Maximum upload size (default: 20MB)
- `MAX_FILENAME_LENGTH`: Maximum filename length (default: 100 chars)
- `CHUNK_SIZE`: Text chunk size for vectorization (default: 500 chars)
- `CHUNK_OVERLAP`: Overlap between chunks (default: 50 chars)

## Integration with PHP

The PHP application proxies requests to this service via the `KnowledgeController` class. The service must be running for the Knowledge Base management page to function.

## Notes

- The service uses ChromaDB's default embedding model (all-MiniLM-L6-v2)
- Files are stored locally in `storage/knowledge/uploads/`
- Vector embeddings are persisted in `storage/knowledge/chroma_db/`
- Supports both Chinese and English text content
