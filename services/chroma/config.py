"""
Configuration for ChromaDB Knowledge Base Service
Reads settings from environment variables or uses defaults
"""
import os
import mimetypes
from pathlib import Path

# Load .env file if python-dotenv is available
try:
    from dotenv import load_dotenv
    # Find .env file in project root
    env_path = Path(__file__).parent.parent.parent / '.env'
    if env_path.exists():
        load_dotenv(env_path)
except ImportError:
    pass

# Base paths
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(os.path.dirname(BASE_DIR))
STORAGE_DIR = os.path.join(PROJECT_ROOT, "storage", "knowledge")

# ChromaDB settings
CHROMA_PERSIST_DIR = os.path.join(STORAGE_DIR, "chroma_db")
CHROMA_COLLECTION_NAME = "knowledge_base"

# File upload settings
UPLOAD_DIR = os.path.join(STORAGE_DIR, "uploads")
ALLOWED_EXTENSIONS = {".pdf", ".txt", ".doc", ".docx", ".md", ".html", ".htm"}

# Get max file size from env (in MB), default to 20MB
_max_size_mb = int(os.getenv('CHROMA_MAX_FILE_SIZE', '20'))
MAX_FILE_SIZE = _max_size_mb * 1024 * 1024

MAX_FILENAME_LENGTH = 100

# Server settings
HOST = os.getenv('CHROMA_SERVICE_HOST', '127.0.0.1')
PORT = int(os.getenv('CHROMA_SERVICE_PORT', '4001'))

# Embedding model - uses local ONNX model from cache
EMBEDDING_MODEL = "all-MiniLM-L6-v2"

# Text processing settings
CHUNK_SIZE = 500
CHUNK_OVERLAP = 50

# Database settings shared with the PHP app
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_NAME = os.getenv("DB_NAME", "")
DB_USERNAME = os.getenv("DB_USERNAME", "")
DB_PASS = os.getenv("DB_PASS", "")
DB_SOCKET = os.getenv("DB_SOCKET", "").strip()

# Retrieval defaults
DEFAULT_CHILD_AGE_BAND = os.getenv("KB_DEFAULT_CHILD_AGE_BAND", "6_12")

# Parser/status defaults
STATUS_PENDING = "pending"
STATUS_COMPLETED = "completed"
STATUS_FAILED = "failed"

FORMAT_BY_EXT = {
    ".pdf": "pdf",
    ".txt": "txt",
    ".md": "md",
    ".html": "html",
    ".htm": "html",
    ".docx": "docx",
    ".doc": "other",
}


def guess_mime_type(filename: str, upload_mime_type: str = "") -> str:
    if upload_mime_type:
        return upload_mime_type

    guessed, _ = mimetypes.guess_type(filename)
    return guessed or "application/octet-stream"
