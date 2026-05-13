import pytesseract
from PIL import Image
import os
import json

from tesseract_config import configure_tesseract
configure_tesseract()

def test_ocr():
    try:
        version = pytesseract.get_tesseract_version()
        print(f"Tesseract version: {version}")
        
        # Test on a small portion or just open the image to see if it works
        img_path = 'dataset/files/d426d76d-102b-4c93-8cd5-92f058078b20.jpg'
        if not os.path.exists(img_path):
            print(f"Image not found: {img_path}")
            return
            
        print(f"Opening image: {img_path}")
        img = Image.open(img_path)
        
        print("Running OCR (this might take a few seconds)...")
        # Run on a small crop to speed up the test
        w, h = img.size
        crop = img.crop((0, 0, w, h // 4))
        text = pytesseract.image_to_string(crop, lang='fra+eng')
        
        print("OCR Result (First few lines):")
        print("-" * 20)
        print(text[:200])
        print("-" * 20)
        
        if len(text.strip()) > 10:
            print("SUCCESS: OCR is working!")
        else:
            print("WARNING: OCR returned very little text.")
            
    except Exception as e:
        print(f"ERROR: {e}")

if __name__ == "__main__":
    test_ocr()
