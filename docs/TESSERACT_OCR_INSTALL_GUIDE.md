# Tesseract OCR — Installation Guide (Windows)

Tesseract OCR is required for the **Worker Profile CV AI** and **Certificate Verification** to extract text from image-based documents (JPG, PNG). The project uses **English** and **French** languages.

---

## Method 1: Manual Installation (Recommended)

### Step 1: Download the installer

1. Go to: **https://github.com/UB-Mannheim/tesseract/wiki**
2. Download the latest 64-bit installer:
   - Direct link: [tesseract-ocr-w64-setup-5.5.0.20241111.exe](https://github.com/tesseract-ocr/tesseract/releases/download/5.5.0/tesseract-ocr-w64-setup-5.5.0.20241111.exe)
   - Or from [GitHub Releases](https://github.com/tesseract-ocr/tesseract/releases)

### Step 2: Run the installer

1. Double-click the downloaded `.exe`
2. **Important:** During setup, check **"Additional language data"** and select:
   - **French** (fra) — required for CV parsing in French
   - **English** (eng) — included by default
3. Use the default install path: `C:\Program Files\Tesseract-OCR`
4. Complete the installation

### Step 3: Add Tesseract to PATH

1. Press **Win + R**, type `sysdm.cpl`, press Enter
2. Go to **Advanced** tab → **Environment Variables**
3. Under **System variables**, select **Path** → **Edit**
4. Click **New** and add: `C:\Program Files\Tesseract-OCR`
5. Click **OK** on all dialogs

### Step 4: Verify installation

Open a **new** PowerShell or Command Prompt and run:

```powershell
tesseract --version
```

You should see something like: `tesseract 5.5.0`

To list installed languages:

```powershell
tesseract --list-langs
```

You should see `eng` and `fra` in the list.

---

## Method 2: Windows Package Manager (winget)

If winget works on your system:

```powershell
winget install -e --id UB-Mannheim.TesseractOCR
```

**Note:** You may need to add Tesseract to PATH manually after installation (see Step 3 above). The default path is `C:\Program Files\Tesseract-OCR`.

---

## Method 3: Chocolatey

If you have [Chocolatey](https://chocolatey.org/) installed:

```powershell
choco install tesseract
```

---

## Add French Language (if missing)

If you installed without French and need it later:

1. Download `fra.traineddata` from: https://github.com/tesseract-ocr/tessdata/raw/main/fra.traineddata
2. Save it to: `C:\Program Files\Tesseract-OCR\tessdata\`
3. Run `tesseract --list-langs` to verify

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `tesseract` not recognized | Add `C:\Program Files\Tesseract-OCR` to PATH (Step 3) |
| Restart terminal after adding PATH | Close and reopen PowerShell/CMD |
| "Error opening data file" for French | Install French language during setup or add `fra.traineddata` manually |
| Different install path | The project auto-detects `C:\Program Files\Tesseract-OCR\tesseract.exe` — if you used another path, set it in `ai_service/cv_parser.py` or `ai_service/verify_ocr.py` |

---

## Test from Python

After installation, test from the project:

```powershell
cd C:\Users\yassm\OneDrive\Desktop\integ\CodeVeins-main\ai_service
python -c "import pytesseract; print('Tesseract version:', pytesseract.get_tesseract_version())"
```

If this prints a version number, Tesseract is correctly installed and accessible from Python.
