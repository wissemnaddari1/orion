"""
Tesseract OCR configuration for Windows.
Call configure_tesseract() at startup to set the correct path.
"""
import os
import platform


def configure_tesseract() -> bool:
    """
    Configure pytesseract with the correct Tesseract path on Windows.
    Returns True if Tesseract was found and configured.
    """
    if platform.system() != 'Windows':
        return True  # On Linux/Mac, tesseract is usually in PATH

    # Common Windows install paths
    paths = [
        r'C:\Program Files\Tesseract-OCR\tesseract.exe',
        r'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
    ]

    # Check TESSERACT_CMD env var
    env_path = os.environ.get('TESSERACT_CMD')
    if env_path and os.path.exists(env_path):
        paths.insert(0, env_path)

    for tess_path in paths:
        if os.path.exists(tess_path):
            import pytesseract
            pytesseract.pytesseract.tesseract_cmd = tess_path
            return True

    return False
