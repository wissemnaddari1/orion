"""
Orion AI Certificate Verification Service
FastAPI microservice for analyzing certificate documents
"""

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List
import os
import tempfile
import re
from datetime import datetime

# Import verification modules
from tesseract_config import configure_tesseract
configure_tesseract()

from certificate_analyzer import CertificateAnalyzer
from cv_parser import CvParser
from dataset_manager import DatasetManager
from category_suggester import CategorySuggester

# Load environment variables from .env file
from dotenv import load_dotenv
load_dotenv()



app = FastAPI(
    title="Orion Certificate Verification API",
    description="AI-powered certificate verification service",
    version="1.0.0"
)

# CORS middleware for Symfony to access
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify Symfony domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# Initialize analyzer
analyzer = CertificateAnalyzer()

# Initialize analyzers
analyzer = CertificateAnalyzer()
cv_parser = CvParser()
dataset_manager = DatasetManager(os.path.join(os.path.dirname(__file__), "dataset"))
category_suggester = CategorySuggester()
print(f"[AI] Gemini Suggester initialized with model: {category_suggester.model_id}")



class VerificationResult(BaseModel):
    """Response model for certificate verification"""
    status: str  # valid, fake, needs_review
    confidence: int  # 0-100
    extracted_text: str
    reasons: List[str]
    analyzed_at: str
    file_type: str


@app.get("/")
async def root():
    """Health check endpoint"""
    return {
        "service": "Orion AI Certificate Verification",
        "status": "running",
        "version": "1.0.0"
    }


@app.post("/verify-certificate", response_model=VerificationResult)
async def verify_certificate(file: UploadFile = File(...)):
    """
    Verify uploaded certificate document
    
    Args:
        file: Certificate file (PDF, JPG, PNG, WebP)
        
    Returns:
        VerificationResult with status, confidence, and analysis
    """
    # Validate file type
    allowed_extensions = ['.pdf', '.jpg', '.jpeg', '.png', '.webp']
    file_ext = os.path.splitext(file.filename)[1].lower()
    
    if file_ext not in allowed_extensions:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type. Allowed: {', '.join(allowed_extensions)}"
        )
    
    # Save to temporary file
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=file_ext) as tmp_file:
            content = await file.read()
            tmp_file.write(content)
            tmp_path = tmp_file.name
        
        # Analyze certificate
        result = analyzer.analyze(tmp_path, file_ext)
        
        # Clean up temp file
        os.unlink(tmp_path)
        
        return VerificationResult(
            status=result['status'],
            confidence=result['confidence'],
            extracted_text=result['extracted_text'],
            reasons=result['reasons'],
            analyzed_at=datetime.now().isoformat(),
            file_type=file_ext[1:]  # Remove dot
        )
        
    except Exception as e:
        # Clean up temp file if it exists
        if 'tmp_path' in locals() and os.path.exists(tmp_path):
            os.unlink(tmp_path)
        
        raise HTTPException(
            status_code=500,
            detail=f"Analysis failed: {str(e)}"
        )




class CvParseResult(BaseModel):
    """Response model for CV parsing"""
    title: str
    bio: str
    experience_years: int | None
    hourly_rate: str
    location: str
    skills: list[str]
    phoneNumber: str
    email: str
    confidence: int


@app.post("/parse-cv", response_model=CvParseResult)
async def parse_cv(file: UploadFile = File(...), collect: bool = False):
    """
    Parse uploaded CV document and extract profile data
    
    Args:
        file: CV file (PDF, DOCX, DOC, JPG, PNG)
        collect: Whether to save to dataset automatically
        
    Returns:
        CvParseResult with extracted profile information
    """
    # Validate file type
    allowed_extensions = ['.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png']
    file_ext = os.path.splitext(file.filename)[1].lower()
    
    if file_ext not in allowed_extensions:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type. Allowed: {', '.join(allowed_extensions)}"
        )
    
    # Save to temporary file
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=file_ext) as tmp_file:
            content = await file.read()
            tmp_file.write(content)
            tmp_path = tmp_file.name
        
        # Parse CV
        print(f"DEBUG: Processing file {tmp_path}")
        result = cv_parser.parse(tmp_path, file_ext)
        print(f"DEBUG: Parsing result: {result}")
        
        # Save to dataset if requested
        if collect:
            dataset_manager.add_item(tmp_path, result)
            
        # Clean up temp file
        os.unlink(tmp_path)
        
        return CvParseResult(
            title=result.get('title', ''),
            bio=result.get('bio', ''),
            experience_years=result.get('experience_years'),
            hourly_rate=result.get('hourly_rate', ''),
            location=result.get('location', ''),
            skills=result.get('skills', []),
            phoneNumber=result.get('phoneNumber', ''),
            email=result.get('email', ''),
            confidence=result.get('confidence', 0),
        )
        
    except Exception as e:
        # Clean up temp file if it exists
        if 'tmp_path' in locals() and os.path.exists(tmp_path):
            os.unlink(tmp_path)
        
        raise HTTPException(
            status_code=500,
            detail=f"CV parsing failed: {str(e)}"
        )


class DatasetItem(BaseModel):
    """Model for a dataset item"""
    title: str
    bio: str
    experience_years: int | None
    hourly_rate: str
    location: str
    skills: list[str]
    phoneNumber: str = ""
    email: str = ""


@app.post("/dataset/collect")
async def collect_cv(data: DatasetItem, file: UploadFile = File(...)):
    """
    Manually collect a CV and its (corrected) labels into the dataset
    """
    file_ext = os.path.splitext(file.filename)[1].lower()
    
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=file_ext) as tmp_file:
            content = await file.read()
            tmp_file.write(content)
            tmp_path = tmp_file.name
        
        item_id = dataset_manager.add_item(tmp_path, data.dict())
        os.unlink(tmp_path)
        
        return {"success": True, "item_id": item_id}
    except Exception as e:
        if 'tmp_path' in locals() and os.path.exists(tmp_path):
            os.unlink(tmp_path)
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/dataset")
async def get_dataset_summary():
    """Get dataset statistics and recent items"""
    return dataset_manager.get_summary()


@app.get("/dataset/export")
async def export_dataset():
    """Return the dataset index file"""
    if not os.path.exists(dataset_manager.index_file):
        raise HTTPException(status_code=404, detail="Dataset index not found")
    
    from fastapi.responses import FileResponse
    return FileResponse(
        dataset_manager.index_file, 
        media_type='application/x-jsonlines',
        filename="dataset_index.jsonl"
    )


class CategorySuggestRequest(BaseModel):
    theme: str
    existing_categories: list[str]| None = None


class CategorySuggestResult(BaseModel):
    name: str = ""
    description: str = ""
    average_hourly_rate: float = 0.0
    success: bool
    error: str = ""


@app.post("/suggest-category", response_model=CategorySuggestResult)
async def suggest_category(request: CategorySuggestRequest):
    """
    Suggest category details based on a theme using AI
    """
    result = category_suggester.suggest(request.theme, request.existing_categories)
    return CategorySuggestResult(
        name=result.get('name', ''),
        description=result.get('description', ''),
        average_hourly_rate=result.get('average_hourly_rate', 0.0),
        success=result.get('success', False),
        error=result.get('error', '')
    )



@app.get("/health")
async def health_check():
    """Health check for monitoring"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat()
    }




@app.get("/test-deps")
async def test_deps():
    """Test if dependencies like Tesseract are correctly installed"""
    import pytesseract
    import shutil
    import platform
    
    tess_installed = shutil.which("tesseract") is not None
    if not tess_installed and platform.system() == 'Windows':
        alt_path = r'C:\Program Files\Tesseract-OCR\tesseract.exe'
        tess_installed = os.path.exists(alt_path)
    
    return {
        "os": platform.system(),
        "tesseract_in_path": tess_installed,
        "tesseract_cmd": pytesseract.pytesseract.tesseract_cmd,
        "poppler_installed": shutil.which("pdftocairo") is not None or shutil.which("pdftoppm") is not None
    }



if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8001)
