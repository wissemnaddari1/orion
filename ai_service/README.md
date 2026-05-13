<<<<<<< HEAD
# Orion AI Certificate Verification Service

FastAPI microservice for AI-powered certificate verification.
=======
# Orion AI Service

FastAPI microservice for AI-powered certificate verification and CV parsing.
>>>>>>> origin/gestioncategory

## Requirements

- Python 3.10+
- pip
<<<<<<< HEAD
=======
- **Tesseract OCR** (required for image-based CV parsing)
>>>>>>> origin/gestioncategory

## Installation

```bash
cd ai_service
python -m venv venv

# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate

pip install -r requirements.txt
```

<<<<<<< HEAD
=======
## Tesseract OCR Setup

Tesseract is required for extracting text from image-based CVs (JPG, PNG).

**Windows:**
```bash
# Download from: https://github.com/UB-Mannheim/tesseract/wiki
# Install and make sure "fra" (French) language is included
# Default install path: C:\Program Files\Tesseract-OCR\
```

**Linux:**
```bash
sudo apt-get install tesseract-ocr tesseract-ocr-fra
```

**Mac:**
```bash
brew install tesseract
brew install tesseract-lang  # For French language pack
```

>>>>>>> origin/gestioncategory
## Running the Service

```bash
python main.py
```

Or with uvicorn directly:

```bash
uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

The API will be available at: `http://127.0.0.1:8001`

## API Documentation

Once running, visit:
- Swagger UI: `http://127.0.0.1:8001/docs`
- ReDoc: `http://127.0.0.1:8001/redoc`

<<<<<<< HEAD
## Endpoint
=======
## Endpoints
>>>>>>> origin/gestioncategory

### POST /verify-certificate

Upload a certificate file for verification.

**Request:**
- Form-data with file field
- Supported: PDF, JPG, PNG, WebP
- Max size: 5MB

**Response:**
```json
{
  "status": "valid|fake|needs_review",
  "confidence": 75,
  "extracted_text": "Certificate text...",
<<<<<<< HEAD
  "reasons": ["File size: 2.3 MB", "80 legit keywords found", ...],
=======
  "reasons": ["File size: 2.3 MB", "80 legit keywords found"],
>>>>>>> origin/gestioncategory
  "analyzed_at": "2026-02-06T03:00:00",
  "file_type": "pdf"
}
```

<<<<<<< HEAD
## Optional: Tesseract OCR

For better text extraction from images, install Tesseract:

**Windows:**
```bash
# Download from: https://github.com/UB-Mannheim/tesseract/wiki
# Add to PATH
```

**Linux:**
```bash
sudo apt-get install tesseract-ocr
```

**Mac:**
```bash
brew install tesseract
```

Without Tesseract, the service still works using basic image analysis.
=======
### POST /parse-cv

Upload a CV file for profile data extraction. **Fully self-contained** — no API key needed. Uses OCR + enhanced regex + heuristic scoring (same approach as certificate verification).

**Request:**
- Form-data with file field
- Supported: PDF, DOC, DOCX, JPG, PNG
- Max size: 10MB

**Response:**
```json
{
  "title": "Senior Software Engineer",
  "bio": "Experienced developer with...",
  "experience_years": 5,
  "hourly_rate": "75.00",
  "location": "Paris, France",
  "skills": ["PHP", "JavaScript", "Python"],
  "confidence": 85
}
```

## Architecture

Both analyzers follow the same self-contained pattern:

| Component | Certificate Analyzer | CV Parser |
|-----------|---------------------|-----------|
| Text source | PyPDF2 + OCR | pdfplumber + PyPDF2 + OCR |
| Image handling | OCR + heuristics | Enhanced OCR (preprocessing) |
| Analysis | Keyword scoring | Regex + skill matching |
| Output | confidence + status | WorkerProfile fields |
| API key needed | ❌ No | ❌ No |
>>>>>>> origin/gestioncategory
