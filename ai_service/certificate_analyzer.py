"""
Certificate Analyzer - Core AI logic
"""

import re
from typing import Dict, List
from PIL import Image
import PyPDF2


class CertificateAnalyzer:
    """Main analyzer class for certificate verification"""
    
    # Keywords that indicate legitimacy
    LEGITIMATE_KEYWORDS = [
        'certificate', 'certification', 'certified', 'diploma', 'degree',
        'awarded', 'conferred', 'completed', 'accredited', 'licensed',
        'professional', 'qualified', 'authorized', 'registered', 'official',
        'bachelor', 'master', 'doctorate', 'phd', 'associate',
        'university', 'college', 'institute', 'academy', 'school',
        'ministry', 'government', 'national', 'international',
        'signature', 'seal', 'stamp', 'date', 'issued', 'valid'
    ]
    
    # Suspicious keywords
    SUSPICIOUS_KEYWORDS = [
        'sample', 'example', 'template', 'draft', 'test',
        'fake', 'copy', 'duplicate', 'replica', 'mock',
        'placeholder', 'demo', 'preview', 'watermark',
        'lorem ipsum', 'xxx', 'yyy', 'edit here', 'type here'
    ]
    
    # Known issuers
    KNOWN_ISSUERS = [
        'coursera', 'udemy', 'linkedin', 'google', 'microsoft',
        'amazon', 'aws', 'cisco', 'oracle', 'comptia',
        'university', 'college', 'institute'
    ]
    
    def analyze(self, file_path: str, file_ext: str) -> Dict:
        """Main analysis method"""
        if file_ext == '.pdf':
            return self._analyze_pdf(file_path)
        else:
            return self._analyze_image(file_path)
    
    def _analyze_pdf(self, file_path: str) -> Dict:
        """Analyze PDF certificate"""
        scores = []
        reasons = []
        extracted_text = ""
        
        try:
            # Extract text
            with open(file_path, 'rb') as file:
                pdf_reader = PyPDF2.PdfReader(file)
                for page in pdf_reader.pages:
                    extracted_text += page.extract_text() + " "
            
            extracted_text = extracted_text.lower().strip()
            
            # File size
            import os
            file_size = os.path.getsize(file_path)
            size_score = self._score_file_size(file_size, 'pdf')
            scores.append(size_score * 100)
            reasons.append(f"File size: {self._format_bytes(file_size)}")
            
            if extracted_text:
                # Keyword analysis
                keyword_result = self._analyze_keywords(extracted_text)
                scores.append(keyword_result['score'])
                reasons.append(keyword_result['reason'])
                
                # Structure
                structure_score = self._analyze_structure(extracted_text)
                scores.append(structure_score)
                reasons.append(f"Document structure: {'good' if structure_score > 60 else 'poor'}")
                
                # Dates
                date_score = self._analyze_dates(extracted_text)
                scores.append(date_score)
                reasons.append(f"Date patterns: {'found' if date_score > 50 else 'missing'}")
            else:
                scores.append(30)
                reasons.append("Limited text extraction")
            
        except Exception as e:
            return {
                'status': 'needs_review',
                'confidence': 0,
                'extracted_text': '',
                'reasons': [f'PDF analysis error: {str(e)}']
            }
        
        return self._calculate_result(scores, reasons, extracted_text)
    
    def _analyze_image(self, file_path: str) -> Dict:
        """Analyze image certificate"""
        scores = []
        reasons = []
        extracted_text = ""
        
        try:
            import os
            img = Image.open(file_path)
            width, height = img.size
            
            # File size
            file_size = os.path.getsize(file_path)
            size_score = self._score_file_size(file_size, 'image')
            scores.append(size_score * 100)
            reasons.append(f"File size: {self._format_bytes(file_size)}")
            
            # Dimensions
            dim_score = self._score_dimensions(width, height)
            scores.append(dim_score * 100)
            reasons.append(f"Dimensions: {width}x{height}")
            
            # Aspect ratio
            aspect_score = self._score_aspect_ratio(width, height)
            scores.append(aspect_score * 100)
            reasons.append(f"Aspect ratio: {'standard' if aspect_score > 0.7 else 'unusual'}")
            
            # Try OCR
            try:
                import pytesseract
                from tesseract_config import configure_tesseract
                configure_tesseract()
                extracted_text = pytesseract.image_to_string(img).lower()
                
                if extracted_text.strip():
                    keyword_result = self._analyze_keywords(extracted_text)
                    scores.append(keyword_result['score'])
                    reasons.append(f"OCR: {keyword_result['reason']}")
                else:
                    scores.append(50)
                    reasons.append("OCR: No text detected")
            except:
                scores.append(60)
                reasons.append("OCR not available")
            
        except Exception as e:
            return {
                'status': 'needs_review',
                'confidence': 0,
                'extracted_text': '',
                'reasons': [f'Image analysis error: {str(e)}']
            }
        
        return self._calculate_result(scores, reasons, extracted_text)
    
    def _score_file_size(self, size: int, file_type: str) -> float:
        """Score file size (returns 0-1)"""
        if file_type == 'pdf':
            if size < 10 * 1024:
                return 0.2
            elif size < 50 * 1024:
                return 0.4
            elif size <= 5 * 1024 * 1024:
                return 0.8
            return 0.6
        else:
            if size < 50 * 1024:
                return 0.3
            elif size <= 5 * 1024 * 1024:
                return 0.8
            return 0.6
    
    def _score_dimensions(self, width: int, height: int) -> float:
        """Score image dimensions (returns 0-1)"""
        min_dim = min(width, height)
        max_dim = max(width, height)
        
        if min_dim < 200 or max_dim < 300:
            return 0.2
        elif min_dim >= 500 and max_dim >= 700:
            return 0.9
        elif min_dim >= 300 and max_dim >= 400:
            return 0.7
        return 0.5
    
    def _score_aspect_ratio(self, width: int, height: int) -> float:
        """Score aspect ratio (returns 0-1)"""
        ratio = width / height if height > 0 else 1
        standard_ratios = [1.41, 1.29, 1.33, 0.71, 0.77, 0.75]
        
        min_diff = min(abs(ratio - std) for std in standard_ratios)
        
        if min_diff < 0.1:
            return 0.9
        elif min_diff < 0.2:
            return 0.7
        return 0.5
    
    def _analyze_keywords(self, text: str) -> Dict:
        """Analyze keywords (returns score 0-100 and reason)"""
        legitimate_count = sum(1 for kw in self.LEGITIMATE_KEYWORDS if kw in text)
        suspicious_count = sum(1 for kw in self.SUSPICIOUS_KEYWORDS if kw in text)
        issuer_count = sum(1 for issuer in self.KNOWN_ISSUERS if issuer in text)
        
        score = 50
        score += min(legitimate_count * 5, 40)
        score -= suspicious_count * 15
        score += min(issuer_count * 10, 20)
        score = max(0, min(100, score))
        
        reason = f"{legitimate_count} legit, {suspicious_count} suspicious keywords"
        
        return {'score': score, 'reason': reason}
    
    def _analyze_structure(self, text: str) -> int:
        """Analyze document structure (returns 0-100)"""
        score = 50
        
        if re.search(r'[a-z]+\s+[a-z]+', text):
            score += 10
        if re.search(r'\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}', text):
            score += 15
        if len(text.split()) > 50:
            score += 10
        
        return min(100, score)
    
    def _analyze_dates(self, text: str) -> int:
        """Check for date patterns (returns 0-100)"""
        patterns = [
            r'\d{1,2}[\/-]\d{1,2}[\/-]\d{4}',
            r'\d{4}[\/-]\d{1,2}[\/-]\d{1,2}',
            r'(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,2},?\s+\d{4}'
        ]
        
        found = sum(1 for pattern in patterns if re.search(pattern, text, re.IGNORECASE))
        
        if found > 0:
            return min(50 + (found * 15), 85)
        return 40
    
    def _calculate_result(self, scores: List[float], reasons: List[str], text: str) -> Dict:
        """Calculate final result"""
        if not scores:
            return {
                'status': 'needs_review',
                'confidence': 0,
                'extracted_text': text[:500],
                'reasons': ['No analysis scores available']
            }
        
        confidence = int(sum(scores) / len(scores))
        
        if confidence >= 70:
            status = 'valid'
        elif confidence >= 40:
            status = 'needs_review'
        else:
            status = 'fake'
        
        return {
            'status': status,
            'confidence': confidence,
            'extracted_text': text[:500],
            'reasons': reasons
        }
    
    def _format_bytes(self, bytes_val: int) -> str:
        """Format bytes to human readable"""
        for unit in ['B', 'KB', 'MB']:
            if bytes_val < 1024:
                return f"{bytes_val:.1f} {unit}"
            bytes_val /= 1024
        return f"{bytes_val:.1f} GB"
