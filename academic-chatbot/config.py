from pathlib import Path
from functools import lru_cache
from pydantic_settings import BaseSettings, SettingsConfigDict

# Base directory = folder academic-chatbot/
BASE_DIR = Path(__file__).resolve().parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    # Ollama (LLM)
    ollama_base_url: str = "http://localhost:11434"
    ollama_model: str = "qwen2.5:7b"

    # Embedding Model
    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"

    # Chunking Strategy
    chunk_size: int = 800
    chunk_overlap: int = 150

    # Retrieval
    top_k: int = 5
    hybrid_alpha: float = 0.6

    # Path Penyimpanan
    vectorstore_path: str = "vectorstore"
    docs_path: str = "docs"
    registry_path: str = "data/index_registry.json"

    # Keamanan
    internal_api_key: str

    # Batas Upload & Concurrency
    max_upload_size_mb: int = 20
    max_concurrent_llm: int = 5

    # Rate Limiting
    rate_limit_per_minute: int = 10

    # Helper Properties
    @property
    def vectorstore_abs_path(self) -> Path:
        return BASE_DIR / self.vectorstore_path

    @property
    def docs_abs_path(self) -> Path:
        return BASE_DIR / self.docs_path

    @property
    def registry_abs_path(self) -> Path:
        return BASE_DIR / self.registry_path

    @property
    def max_upload_size_bytes(self) -> int:
        return self.max_upload_size_mb * 1024 * 1024


@lru_cache()
def get_settings() -> Settings:
    return Settings()
