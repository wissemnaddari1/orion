# Worker Profile CV AI — Run Guide

This guide explains how to run the **CV parsing AI** for the Worker Profile feature. When creating or editing a worker profile, you can upload a CV (PDF, DOCX, or image) and the AI will automatically extract title, bio, skills, experience, location, etc.

## Architecture

```
Worker Profile (Symfony)  →  CvParserService  →  AI Service (Python main.py)  →  cv_parser.py
     /worker/profiles/parse-cv                    http://127.0.0.1:8001/parse-cv
```

## Prerequisites

- **Python 3.10+**
- **Tesseract OCR** (for image-based CVs: JPG, PNG)
  - Windows: [Download from GitHub](https://github.com/UB-Mannheim/tesseract/wiki)
  - Add Tesseract to your PATH (e.g. `C:\Program Files\Tesseract-OCR`)

## Step 1: Install AI Service Dependencies

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main\ai_service
pip install -r requirements.txt
```

## Step 2: Start the AI Service (Port 8001)

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main\ai_service
python main.py
```

Or with uvicorn (with auto-reload):

```powershell
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

The service will be available at: **http://127.0.0.1:8001**

Verify it's running:
- Health: http://127.0.0.1:8001/
- API docs: http://127.0.0.1:8001/docs

## Step 3: Start Symfony

In a **separate terminal**:

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main
symfony serve
```

## Step 4: Use CV Upload in Worker Profile

1. Log in as a **Freelancer** (worker)
2. Go to **Worker Profiles** → **Create new profile**
3. Click **Upload CV** and select a PDF, DOCX, or image file
4. The AI will extract and fill the form fields automatically

## Environment Variables

In `.env`:

```env
PYTHON_AI_SERVICE_URL=http://127.0.0.1:8001
```

This URL is used by `CvParserService` for CV parsing.

## Supported File Types

- **PDF** — Text extraction via PyPDF2/pdfplumber
- **DOCX** — Via python-docx
- **JPG, PNG** — OCR via Tesseract (requires Tesseract installed)

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "AI service unavailable" | Ensure `python main.py` is running on port 8001 |
| "Could not extract text" | For images: install Tesseract OCR and add to PATH |
| Module not found (e.g. pdfplumber) | Run `pip install -r requirements.txt` in ai_service |
| Port 8001 in use | Stop other process or change port in main.py and .env |

## Quick Start (All Services)

For a full Orion setup, you may need multiple AI services. Minimum for **Worker Profile CV**:

1. **Terminal 1** — AI Certificate/CV Service (port 8001):
   ```powershell
   cd ai_service && python main.py
   ```

2. **Terminal 2** — Symfony:
   ```powershell
   symfony serve
   ```

Then visit: https://127.0.0.1:8000/worker/profiles/new
