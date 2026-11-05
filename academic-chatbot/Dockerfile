# Base image
FROM python:3.11-slim

# Set working directory
WORKDIR /app

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV OLLAMA_BASE_URL=http://ollama:11434

# Install system dependencies (Tesseract, Indonesian language pack, Poppler utils, libmagic)
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr \
    tesseract-ocr-ind \
    poppler-utils \
    libmagic1 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy and install dependencies
COPY requirements.linux.txt .
RUN pip install --no-cache-dir -r requirements.linux.txt

# Copy all project files
COPY . .

# Ensure storage directories exist
RUN mkdir -p docs vectorstore data

# Expose port
EXPOSE 8000

# Run application using uvicorn
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "2"]
