# Orion — Launch Guide

Quick guide to launch the Orion app with all AI services (CV parsing, certificates, etc.).

---

## Prerequisites

- **Symfony** (PHP) — main app
- **Python 3.10+** — AI services
- **Tesseract OCR** — installed and configured (see `TESSERACT_OCR_INSTALL_GUIDE.md`)
- **MySQL/MariaDB** — database

---

## Step 1: Start the AI Service (CV + Certificates)

This service handles:
- **CV parsing** for Worker Profile
- **Certificate verification**

Open a terminal:

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main\ai_service
python main.py
```

Or with auto-reload:

```powershell
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

**Verify:** Open http://127.0.0.1:8001/ — you should see `{"service":"Orion AI Certificate Verification","status":"running"}`

---

## Step 2: Start Symfony

Open a **second** terminal:

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main
symfony serve
```

**Verify:** Open https://127.0.0.1:8000/

---

## Step 3: Use CV Upload (Worker Profile)

1. Log in as a **Freelancer**
2. Go to **Worker Profiles** → **Create new profile**
3. Click **Upload CV** and select a PDF, DOCX, or image (JPG/PNG)
4. The AI extracts and fills the form automatically

---

## Optional: Other AI Services

| Service | Port | Command | When needed |
|---------|------|---------|-------------|
| Face recognition | 8002 | `cd ai_face_service && python main.py` | Face login |
| Ticket Support AI | 8005 | `cd ai_service && python -m uvicorn ticket_support_api:app --host 127.0.0.1 --port 8005` | Ticket suggestions |

---

## Quick Reference

**Minimum to use CV upload:**
1. Terminal 1: `cd ai_service && python main.py`
2. Terminal 2: `symfony serve`
3. Visit: https://127.0.0.1:8000/worker/profiles/new

**Troubleshooting:**
- "AI service unavailable" → Ensure `python main.py` is running on port 8001
- "Could not extract text" → Tesseract must be installed (see `TESSERACT_OCR_INSTALL_GUIDE.md`)
