"""
CV Parser Module — Self-Contained Extraction Engine for WorkerProfile

Extracts profile information from ANY type of CV (PDF export, Word, scanned,
graphical, multi-column, French/English/Arabic, etc.) and maps to WorkerProfile
attributes: title, bio, hourly_rate, experience_years, location (+ skills for UI).

Fully self-contained: uses OCR + enhanced regex + heuristic scoring.
No external API key required (same approach as certificate_analyzer.py).
"""

import re
import os
import datetime
from typing import Dict, Any, List

# Output schema aligned with WorkerProfile entity (Symfony)
WORKER_PROFILE_KEYS = ("title", "bio", "experience_years", "hourly_rate", "location", "skills", "phoneNumber", "email")
MAX_TITLE_LEN = 255
MAX_BIO_LEN = 500
MAX_LOCATION_LEN = 255
MAX_SKILLS = 15

import PyPDF2
from docx import Document
import pytesseract
from PIL import Image, ImageFilter, ImageEnhance

# Optional imports (graceful degradation)
try:
    import pdfplumber
    HAS_PDFPLUMBER = True
except ImportError:
    HAS_PDFPLUMBER = False

try:
    from pdf2image import convert_from_path
    HAS_PDF2IMAGE = True
except ImportError:
    HAS_PDF2IMAGE = False


# ─────────────────────────────────────────────────────────────
# Known data for heuristic matching
# ─────────────────────────────────────────────────────────────
# Very short tokens that need strict word-boundary + context to avoid false positives
_SHORT_AMBIGUOUS_SKILLS = {'r', 'c', 'go', 'dart', 'rust', 'swift', 'scala', 'erp', 'git'}

KNOWN_SKILLS = [
    # Programming & Web
    'python', 'java', 'javascript', 'typescript', 'php', 'c#', 'c++',
    'ruby', 'golang', 'kotlin', 'perl',
    'matlab', 'lua', 'haskell', 'elixir', 'clojure',
    'html', 'css', 'sass', 'scss', 'less', 'tailwind', 'bootstrap',
    'react', 'reactjs', 'react.js', 'angular', 'angularjs', 'vue', 'vuejs', 'vue.js',
    'next.js', 'nextjs', 'nuxt', 'nuxtjs', 'svelte', 'ember',
    'node.js', 'nodejs', 'express', 'express.js', 'fastapi', 'flask', 'django',
    'spring', 'spring boot', 'laravel', 'symfony', 'codeigniter', 'rails',
    'asp.net', '.net', 'blazor', 'wpf',
    # Mobile
    'android', 'ios', 'react native', 'flutter', 'xamarin', 'ionic',
    'swiftui', 'objective-c',
    # Data & AI
    'sql', 'mysql', 'postgresql', 'postgres', 'mongodb', 'redis', 'elasticsearch',
    'sqlite', 'oracle', 'mariadb', 'cassandra', 'dynamodb', 'firebase',
    'tensorflow', 'pytorch', 'keras', 'scikit-learn', 'pandas', 'numpy',
    'matplotlib', 'tableau', 'power bi', 'machine learning', 'deep learning',
    'nlp', 'computer vision', 'data science', 'big data', 'hadoop', 'spark',
    # DevOps & Cloud
    'docker', 'kubernetes', 'k8s', 'jenkins', 'ci/cd', 'ansible', 'terraform',
    'aws', 'azure', 'gcp', 'google cloud', 'heroku', 'digitalocean', 'vercel',
    'nginx', 'apache', 'linux', 'unix', 'bash', 'powershell',
    'git', 'github', 'gitlab', 'bitbucket', 'svn',
    # Design & Media
    'photoshop', 'illustrator', 'figma', 'sketch', 'xd', 'adobe xd',
    'indesign', 'after effects', 'premiere pro', 'lightroom',
    'canva', 'coreldraw', 'blender', '3ds max', 'maya', 'cinema 4d',
    'ui/ux', 'ui design', 'ux design', 'graphic design', 'web design',
    'montage vidéo', 'montage video', 'photography', 'photographie',
    # Office & Business
    'excel', 'word', 'powerpoint', 'microsoft office', 'google sheets',
    'sap', 'salesforce', 'crm', 'erp', 'jira', 'trello', 'asana', 'slack',
    'scrum', 'agile', 'kanban', 'project management', 'gestion de projet',
    # Trades & Manual
    'plumbing', 'plomberie', 'electrical', 'électricité', 'carpentry', 'menuiserie',
    'welding', 'soudure', 'painting', 'peinture', 'masonry', 'maçonnerie',
    'hvac', 'climatisation', 'roofing', 'couverture', 'tiling', 'carrelage',
    # Marketing & Communication
    'seo', 'sem', 'google ads', 'facebook ads', 'social media', 'réseaux sociaux',
    'content marketing', 'email marketing', 'copywriting', 'rédaction',
    'community management', 'marketing digital', 'digital marketing',
    # Languages
    'français', 'anglais', 'arabe', 'espagnol', 'allemand', 'italien',
    'french', 'english', 'arabic', 'spanish', 'german', 'italian',
    'chinese', 'japanese', 'portuguese', 'russian', 'turkish', 'korean',
    # Certifications / Frameworks
    'aws certified', 'cisco', 'comptia', 'pmp', 'itil', 'prince2',
    'google analytics', 'hubspot', 'wordpress', 'shopify', 'magento', 'prestashop',
    'api rest', 'restful', 'graphql', 'websocket', 'microservices',
    'tdd', 'bdd', 'unit testing', 'selenium', 'cypress', 'jest', 'pytest',
]

# Words that should NOT be recognized as a title
TITLE_IGNORE_WORDS = [
    'stage', 'stages', 'intitulé', 'intitule', 'poste', 'job',
    'étudiant', 'etudiante', 'étudiante',
    'curriculum vitae', 'cv', 'resume', 'résumé',
    'exemple', 'example', 'template', 'modèle',
]

TITLE_KEYWORDS = [
    # Tech
    'developer', 'développeur', 'développeuse', 'engineer', 'ingénieur', 'ingénieure',
    'designer', 'architecte', 'architect', 'analyst', 'analyste',
    'administrator', 'administrateur', 'administratrice',
    'programmer', 'programmeur', 'programmeuse',
    'data scientist', 'data engineer', 'data analyst',
    'devops', 'sysadmin', 'dba', 'webmaster',
    'full stack', 'fullstack', 'frontend', 'front-end', 'backend', 'back-end',
    'mobile', 'ios', 'android', 'cloud', 'security', 'sécurité',
    # Management
    'manager', 'gérant', 'gérante', 'director', 'directeur', 'directrice',
    'lead', 'chef', 'head', 'supervisor', 'superviseur',
    'coordinator', 'coordinateur', 'coordinatrice',
    'responsable', 'chargé', 'chargée',
    # Business
    'consultant', 'conseiller', 'conseillère',
    'commercial', 'commerciale', 'sales', 'vendeur', 'vendeuse',
    'account manager', 'business analyst', 'product owner', 'product manager',
    'project manager', 'chef de projet', 'scrum master',
    # Creative
    'graphic', 'graphiste', 'ui', 'ux', 'ui/ux',
    'photographer', 'photographe', 'videographer', 'vidéaste',
    'editor', 'éditeur', 'monteur', 'monteuse',
    'content creator', 'créateur de contenu', 'community manager',
    'rédacteur', 'rédactrice', 'copywriter',
    # Education & Health
    'teacher', 'professeur', 'formateur', 'formatrice', 'trainer', 'coach',
    'médecin', 'doctor', 'infirmier', 'infirmière', 'nurse', 'pharmacien',
    # Trades
    'plumber', 'plombier', 'electrician', 'électricien', 'électricienne',
    'carpenter', 'menuisier', 'mechanic', 'mécanicien', 'mécanicienne',
    'technician', 'technicien', 'technicienne',
    'contractor', 'entrepreneur', 'artisan',
    'mason', 'maçon', 'painter', 'peintre',
    'welder', 'soudeur', 'roofer', 'couvreur',
    # General
    'specialist', 'spécialiste', 'expert', 'freelancer', 'freelance', 'indépendant',
    'assistant', 'assistante', 'secretary', 'secrétaire',
    'agent', 'officer', 'associate',
    'intern', 'stagiaire', 'student', 'étudiant', 'étudiante',
    'founder', 'co-founder', 'ceo', 'cto', 'cfo', 'coo', 'owner',
    'marketing', 'communication', 'logistics', 'logistique',
    'comptable', 'accountant', 'financier', 'financial',
    'receptionist', 'réceptionniste', 'hôtesse', 'hostess',
    'traducteur', 'traductrice', 'translator', 'interpreter', 'interprète',
    'journalist', 'journaliste', 'writer', 'écrivain',
    'chef cuisinier', 'cuisinier', 'cuisinière', 'cook', 'baker', 'boulanger',
    'driver', 'chauffeur', 'livreur', 'delivery',
    'cleaner', 'agent d\'entretien', 'gardien', 'security guard',
    'web', 'software', 'senior', 'junior', 'mid-level',
]

SENIORITY_PREFIXES = [
    'senior', 'junior', 'lead', 'principal', 'chief', 'head',
    'mid-level', 'entry-level', 'staff', 'associate',
]

SECTION_HEADERS = {
    'profile': ['profil', 'profile', 'about', 'about me', 'à propos', 'a propos',
                 'summary', 'résumé', 'resume', 'objective', 'objectif',
                 'présentation', 'presentation', 'introduction', 'who i am'],
    'experience': ['experience', 'expérience', 'expériences', 'experiences',
                    'expériences professionnelles', 'work experience',
                    'professional experience', 'parcours professionnel',
                    'employment history', 'work history', 'career'],
    'education': ['education', 'formation', 'formations', 'études', 'etudes',
                   'academic', 'diplômes', 'diplomes', 'qualifications', 'scholaire'],
    'skills': ['skills', 'compétences', 'competences', 'technologies', 'expertise',
               'stack', 'tools', 'outils', 'technical skills', 'compétences techniques',
               'savoir-faire', 'savoir faire', 'hard skills', 'soft skills',
               'informatique', 'logiciels', 'software'],
    'languages': ['langues', 'languages', 'linguistique'],
    'contact': ['contact', 'coordonnées', 'coordonnees', 'informations personnelles',
                'personal information'],
    'interests': ['centres d\'intérêt', 'centres d\'interet', 'hobbies', 'interests',
                   'loisirs', 'activités', 'activities'],
    'references': ['references', 'références', 'referees'],
    'certifications': ['certifications', 'certificates', 'certificats', 'accréditations'],
    'volunteer': ['bénévolat', 'benevolat', 'volunteer', 'volunteering'],
}

CITIES_DB = [
    # Tunisia
    'tunis', 'ariana', 'ben arous', 'manouba', 'nabeul', 'zaghouan', 'bizerte',
    'béja', 'beja', 'jendouba', 'kef', 'siliana', 'kairouan', 'kasserine',
    'sidi bouzid', 'sousse', 'monastir', 'mahdia', 'sfax', 'gafsa', 'tozeur',
    'kebili', 'gabès', 'gabes', 'médenine', 'medenine', 'tataouine', 'hammamet',
    'la marsa', 'carthage', 'lac', 'ennasr',
    # France
    'paris', 'lyon', 'marseille', 'toulouse', 'nice', 'nantes', 'strasbourg',
    'montpellier', 'bordeaux', 'lille', 'rennes', 'reims', 'saint-étienne',
    'toulon', 'grenoble', 'dijon', 'angers', 'nîmes', 'clermont-ferrand',
    # Other
    'london', 'new york', 'berlin', 'dubai', 'doha', 'riyadh', 'casablanca',
    'algiers', 'alger', 'chicago', 'newark', 'lincoln park', 'los angeles',
    'san francisco', 'seattle', 'boston', 'miami', 'houston', 'dallas',
    'toronto', 'montreal', 'vancouver', 'brussels', 'bruxelles', 'geneva',
    'genève', 'zurich', 'amsterdam', 'madrid', 'barcelona', 'rome', 'milan',
    'munich', 'frankfurt', 'hamburg', 'vienna', 'prague', 'warsaw', 'istanbul',
    'cairo', 'le caire', 'rabat', 'marrakech', 'fes', 'fès', 'oran',
    'constantine', 'annaba', 'jeddah', 'muscat', 'abu dhabi', 'sharjah',
    'manama', 'kuwait city', 'amman', 'beirut', 'beyrouth', 'damas',
]

COUNTRIES_DB = [
    'tunisie', 'tunisia', 'france', 'usa', 'united states', 'états-unis',
    'canada', 'uk', 'united kingdom', 'royaume-uni', 'germany', 'allemagne',
    'spain', 'espagne', 'italy', 'italie', 'belgium', 'belgique',
    'switzerland', 'suisse', 'netherlands', 'pays-bas', 'morocco', 'maroc',
    'algeria', 'algérie', 'egypt', 'égypte', 'libya', 'libye',
    'saudi arabia', 'arabie saoudite', 'uae', 'émirats', 'qatar',
    'turkey', 'turquie', 'lebanon', 'liban', 'jordan', 'jordanie',
    'iraq', 'irak', 'kuwait', 'koweït', 'oman', 'bahrain', 'bahrein',
]


class CvParser:
    """Parse CV documents and extract structured profile data — fully self-contained engine"""

    def __init__(self):
        from tesseract_config import configure_tesseract
        configure_tesseract()

        self._tesseract_available = self._check_tesseract()
        print("[CV] Parser initialized (self-contained engine)")
        print(f"     Tesseract OCR: {'available' if self._tesseract_available else 'NOT found'}")

    def _check_tesseract(self) -> bool:
        """Check if Tesseract OCR is available"""
        try:
            pytesseract.get_tesseract_version()
            return True
        except Exception:
            return False

    # ─────────────────────────────────────────────────────────────
    # Main parse orchestrator
    # ─────────────────────────────────────────────────────────────
    def parse(self, file_path: str, file_type: str) -> Dict[str, Any]:
        """
        Parse CV file using self-contained multi-strategy approach.
        1. Extract text (pdfplumber/PyPDF2/docx/OCR)
        2. Pre-process image for better OCR if needed
        3. Enhanced regex extraction on all text
        4. Heuristic confidence scoring
        """
        print(f"[CV] Parsing file: {file_path} ({file_type})")

        try:
            file_ext = file_type.lower().lstrip('.')

            # Step 1: Extract text from the document
            text = self._extract_text(file_path, file_ext)
            text_length = len(text.strip()) if text else 0
            print(f"[CV] Extracted {text_length} characters")

            if text and text_length > 30:
                print(f"[CV] Text preview: {text[:200]}")

            # Step 2: If image and text is short, try enhanced OCR
            if file_ext in ('jpg', 'jpeg', 'png', 'webp') and text_length < 100:
                print("[CV] Short text from image - trying enhanced OCR...")
                enhanced_text = self._extract_from_image_enhanced(file_path)
                if len(enhanced_text.strip()) > text_length:
                    text = enhanced_text
                    text_length = len(text.strip())
                    print(f"[CV] Enhanced OCR: {text_length} characters")

            # Step 3: Extract fields with enhanced regex
            if text and text_length > 10:
                print("[CV] Extracting fields with enhanced regex engine...")
                result = self._extract_all_fields(text)
                strategy = "enhanced_regex"

                if text_length < 50:
                    strategy = "regex_short_text"
                    print(f"[CV] Short text ({text_length} chars), results may be partial")

                result['strategy'] = strategy
                print(f"[CV] Extraction done (strategy: {strategy}, confidence: {result.get('confidence', 0)})")
                return self._normalize_for_worker_profile(result)

            return self._empty_result("Could not extract text from CV (OCR may need Tesseract installed)")

        except Exception as e:
            print(f"[CV] Parsing exception: {e}")
            import traceback
            traceback.print_exc()
            return self._empty_result(f"Parsing error: {str(e)}")

    # ─────────────────────────────────────────────────────────────
    # Text extraction
    # ─────────────────────────────────────────────────────────────
    def _extract_text(self, file_path: str, file_type: str) -> str:
        """Extract text from different file formats"""
        if file_type == 'pdf':
            return self._extract_from_pdf(file_path)
        elif file_type in ['doc', 'docx']:
            return self._extract_from_docx(file_path)
        elif file_type in ['jpg', 'jpeg', 'png', 'webp']:
            return self._extract_from_image(file_path)
        else:
            raise ValueError(f"Unsupported file type: {file_type}")

    def _extract_from_pdf(self, file_path: str) -> str:
        """Extract text from PDF — pdfplumber → PyPDF2 → OCR"""
        text = ""

        # Try pdfplumber first (better for multi-column)
        if HAS_PDFPLUMBER:
            try:
                with pdfplumber.open(file_path) as pdf:
                    for page in pdf.pages:
                        page_text = page.extract_text() or ""
                        text += page_text + "\n"
                if len(text.strip()) > 80:
                    print(f"📋 pdfplumber extracted {len(text.strip())} chars")
                    return text
            except Exception as e:
                print(f"[CV] pdfplumber failed: {e}")

        # Fallback to PyPDF2
        try:
            with open(file_path, 'rb') as file:
                pdf_reader = PyPDF2.PdfReader(file)
                for page in pdf_reader.pages:
                    page_text = page.extract_text() or ""
                    text += page_text + "\n"

            if len(text.strip()) > 80:
                print(f"📋 PyPDF2 extracted {len(text.strip())} chars")
                return text
        except Exception as e:
            print(f"[CV] PyPDF2 failed: {e}")

        # OCR fallback for scanned PDFs
        if HAS_PDF2IMAGE and self._tesseract_available:
            try:
                print("📸 Attempting OCR on PDF pages...")
                images = convert_from_path(file_path)
                ocr_text = ""
                for img in images:
                    ocr_text += pytesseract.image_to_string(img, lang='fra+eng') + "\n"
                if len(ocr_text.strip()) > len(text.strip()):
                    print(f"📋 OCR extracted {len(ocr_text.strip())} chars")
                    return ocr_text
            except Exception as ocr_err:
                print(f"[CV] OCR fallback failed: {ocr_err}")

        return text

    def _extract_from_docx(self, file_path: str) -> str:
        """Extract text from DOCX"""
        try:
            doc = Document(file_path)
            text = "\n".join([p.text for p in doc.paragraphs])
            return text
        except Exception as e:
            raise Exception(f"DOCX extraction failed: {str(e)}")

    def _extract_from_image(self, file_path: str) -> str:
        """Extract text from image using Tesseract OCR"""
        if not self._tesseract_available:
            print("[CV] Tesseract not available, cannot OCR image")
            return ""
        try:
            image = Image.open(file_path)
            text = pytesseract.image_to_string(image, lang='fra+eng')
            print(f"📋 Image OCR extracted {len(text)} chars")
            return text
        except Exception as e:
            print(f"[CV] Image OCR failed: {e}")
            return ""

    def _extract_from_image_enhanced(self, file_path: str) -> str:
        """Enhanced OCR: pre-process image for better text extraction"""
        if not self._tesseract_available:
            return ""
        try:
            image = Image.open(file_path)

            # Convert to grayscale
            gray = image.convert('L')

            # Increase contrast
            enhancer = ImageEnhance.Contrast(gray)
            high_contrast = enhancer.enhance(2.0)

            # Sharpen
            sharpened = high_contrast.filter(ImageFilter.SHARPEN)

            # Scale up small images
            w, h = sharpened.size
            if w < 1000 or h < 1000:
                scale = max(1500 / w, 1500 / h)
                sharpened = sharpened.resize((int(w * scale), int(h * scale)), Image.LANCZOS)

            # OCR with multiple configs
            configs = [
                '--psm 3 --oem 3',   # Fully automatic
                '--psm 6 --oem 3',   # Uniform block of text
                '--psm 4 --oem 3',   # Single column of variable-size text
            ]

            best_text = ""
            for config in configs:
                try:
                    text = pytesseract.image_to_string(sharpened, lang='fra+eng', config=config)
                    if len(text.strip()) > len(best_text.strip()):
                        best_text = text
                except Exception:
                    continue

            return best_text

        except Exception as e:
            print(f"[CV] Enhanced OCR failed: {e}")
            return ""

    # ─────────────────────────────────────────────────────────────
    # Main extraction engine (enhanced regex + heuristics)
    # ─────────────────────────────────────────────────────────────
    def _extract_all_fields(self, text: str) -> Dict[str, Any]:
        """Extract all WorkerProfile fields from text using enhanced regex"""
        title = self._extract_title(text)
        bio = self._extract_bio(text)
        experience_years = self._extract_experience(text)
        hourly_rate = self._extract_rate(text)
        location = self._extract_location(text)
        skills = self._extract_skills(text)
        phone = self._extract_phone(text)
        email = self._extract_email(text)

        confidence = self._calculate_confidence(title, bio, experience_years, hourly_rate, location, skills)

        return {
            'title': title,
            'bio': bio,
            'experience_years': experience_years,
            'hourly_rate': hourly_rate,
            'location': location,
            'skills': skills,
            'phoneNumber': phone,
            'email': email,
            'confidence': confidence,
        }

    # ─────────────────────────────────────────────────────────────
    # Field extractors
    # ─────────────────────────────────────────────────────────────

    def _extract_title(self, text: str) -> str:
        """Extract professional title using keyword matching + position heuristics"""
        lines = [line.strip() for line in text.split('\n') if line.strip()]

        # Headers to skip
        ignored = set()
        for category, headers in SECTION_HEADERS.items():
            for h in headers:
                ignored.add(h.lower())

        # Find where the first section header appears — only look for title before that
        first_section_idx = len(lines)
        for i, line in enumerate(lines):
            clean = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
            if clean in ignored and i > 0:  # skip if it's the very first line
                first_section_idx = i
                break

        # Limit header search zone (before first section header, max 15 lines)
        header_zone = min(first_section_idx, 15)

        # Strategy 1: Look for explicit "title/poste" label
        for line in lines[:header_zone]:
            m = re.match(r'^(?:intitul[ée]\s+du\s+poste|poste|titre|title|position)\s*[:/]\s*(.+)', line, re.IGNORECASE)
            if m:
                candidate = m.group(1).strip()
                # Filter out generic words like "STAGE"
                candidate_words = [w for w in candidate.split('/') if w.strip().lower() not in 
                                   [t for t in TITLE_IGNORE_WORDS]]
                candidate = ' / '.join(w.strip() for w in candidate_words if w.strip())
                if candidate and 3 < len(candidate) < 80 and candidate.lower() not in [t for t in TITLE_IGNORE_WORDS]:
                    return self._clean_title(candidate)

        # Strategy 2: Lines 2-5 in the header zone — title keyword match
        for line in lines[1:min(5, header_zone)]:
            line_lower = line.lower()
            if line_lower in ignored:
                continue
            if any(iw in line_lower for iw in TITLE_IGNORE_WORDS):
                continue
            if len(line.split()) > 10 or len(line) < 3:
                continue
            if any(kw in line_lower for kw in TITLE_KEYWORDS):
                title = self._clean_title(line)
                if 3 < len(title) < 80:
                    return title

        # Strategy 3: Scan header zone for title keywords
        for line in lines[:header_zone]:
            line_lower = line.lower()
            if line_lower in ignored:
                continue
            if any(iw in line_lower for iw in TITLE_IGNORE_WORDS):
                continue
            if len(line.split()) > 10:
                continue
            if any(kw in line_lower for kw in TITLE_KEYWORDS):
                title = self._clean_title(line)
                if 3 < len(title) < 80:
                    return title

        # Strategy 4: If line 1 looks like a name, line 2 might be title regardless of keywords
        if len(lines) >= 2 and header_zone >= 2:
            first = lines[0].strip()
            second = lines[1].strip()
            # First line looks like a name (2-4 capitalized words)
            if re.match(r'^[A-ZÀ-Ú][a-zà-ú]+(\s+[A-ZÀ-Ú][a-zà-ú]+){0,3}$', first) or \
               re.match(r'^[A-ZÀ-Ú\s]{3,50}$', first):
                if second.lower() not in ignored and \
                   not any(iw in second.lower() for iw in TITLE_IGNORE_WORDS) and \
                   3 < len(second) < 80 and len(second.split()) <= 8:
                    return self._clean_title(second)

        # Strategy 5: Fallback — extract the first/most recent job title from experience section
        exp_headers = SECTION_HEADERS['experience']
        exp_start = -1
        for i, line in enumerate(lines):
            clean = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
            if clean in exp_headers:
                exp_start = i + 1
                break

        if exp_start != -1:
            for line in lines[exp_start:exp_start + 5]:
                line_lower = line.lower()
                clean = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
                # Skip section headers and empty lines
                is_header = False
                for cat, hdrs in SECTION_HEADERS.items():
                    if cat != 'experience' and clean in hdrs:
                        is_header = True
                        break
                if is_header:
                    break
                # Skip lines with dates or company names (usually have commas + years)
                if re.search(r'\b(19|20)\d{2}\b', line):
                    continue
                if len(line.split()) > 8:
                    continue
                if any(kw in line_lower for kw in TITLE_KEYWORDS) or len(line.split()) <= 5:
                    title = self._clean_title(line)
                    if 3 < len(title) < 80:
                        return title

        return ""

    def _clean_title(self, raw: str) -> str:
        """Clean and normalize a title string"""
        # Remove bullet points, pipes, leading/trailing symbols
        title = re.split(r'[•|/]', raw)[0].strip()
        title = re.sub(r'^[-–—:*#>]+\s*', '', title)
        title = re.sub(r'^(i am a|je suis|looking for|recherche)\s+', '', title, flags=re.IGNORECASE)
        # Remove phone numbers and email fragments
        title = re.sub(r'[\d\-+().]{5,}', '', title).strip()
        title = re.sub(r'\S+@\S+', '', title).strip()
        # Title case
        if title.isupper() or title.islower():
            title = title.title()
        return title.strip()

    def _extract_bio(self, text: str) -> str:
        """Extract bio/summary section"""
        lines = [l.strip() for l in text.split('\n')]

        # Strategy 1: Find a profile/summary section header
        profile_headers = SECTION_HEADERS['profile']
        bio_start = -1

        for i, line in enumerate(lines):
            clean_line = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
            if clean_line in profile_headers:
                bio_start = i + 1
                break

        if bio_start != -1:
            bio_lines = []
            for line in lines[bio_start:]:
                if line == '' and bio_lines:
                    break
                if line == '':
                    continue
                # Stop at next section header
                clean = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
                is_header = False
                for cat, headers in SECTION_HEADERS.items():
                    if cat != 'profile' and clean in headers:
                        is_header = True
                        break
                if is_header and bio_lines:
                    break
                if len(bio_lines) >= 5:
                    break
                bio_lines.append(line)
            if bio_lines:
                return ' '.join(bio_lines)[:MAX_BIO_LEN]

        # Strategy 2: First long paragraph (likely a profile summary)
        for i in range(min(3, len(lines)), min(30, len(lines))):
            line = lines[i].strip()
            if len(line) > 80 and not any(x in line.lower() for x in ['http', 'www', '@', '+33', '+216']):
                return line[:MAX_BIO_LEN]

        # Strategy 3: Combine multiple medium-length consecutive lines after the first few
        for start in range(2, min(15, len(lines))):
            if len(lines[start]) > 40:
                chunk = []
                for j in range(start, min(start + 4, len(lines))):
                    if lines[j] and len(lines[j]) > 20:
                        chunk.append(lines[j])
                    elif chunk:
                        break
                if len(' '.join(chunk)) > 80:
                    return ' '.join(chunk)[:MAX_BIO_LEN]

        return ""

    def _extract_experience(self, text: str) -> int:
        """Extract years of experience"""
        text_lower = text.lower()

        # Explicit patterns
        explicit_patterns = [
            r'(\d+)\+?\s*years?\s*(?:of\s*)?experience',
            r'experience[:\s]+(\d+)\+?\s*years?',
            r'(\d+)\s*years?\s*in\s*(?:the\s*)?(?:field|industry)',
            r'(\d+)\+?\s*ans?\s*d\'?expérience',
            r'(\d+)\+?\s*ans?\s*d\'?experience',
            r'expérience[:\s]+(\d+)\s*ans?',
            r'experience[:\s]+(\d+)\s*ans?',
            r'(\d+)\s*années?\s*d\'?expérience',
            r'(\d+)\+?\s*ans?\b',
        ]

        for pattern in explicit_patterns:
            match = re.search(pattern, text_lower)
            if match:
                try:
                    val = int(match.group(1))
                    if 0 < val < 50:
                        return val
                except ValueError:
                    continue

        # Date range calculation
        current_year = datetime.datetime.now().year
        years = re.findall(r'(?:19|20)\d{2}', text)
        is_present = any(term in text_lower for term in
                         ['present', 'current', 'maintenant', "aujourd'hui", 'actuel', 'en cours'])

        if years:
            years_int = [int(y) for y in years if 1970 < int(y) <= current_year + 1]
            if years_int:
                min_year = min(years_int)
                max_year = max(years_int)
                if is_present:
                    max_year = current_year
                diff = max_year - min_year
                if 0 < diff < 50:
                    return diff

        return 0

    def _extract_rate(self, text: str) -> str:
        """Extract hourly rate"""
        rate_patterns = [
            r'\$(\d+(?:[.,]\d{2})?)\s*(?:/\s*)?(?:hr|hour|h\b)',
            r'(\d+(?:[.,]\d{2})?)\s*(?:dollars?|usd)\s*(?:per\s*)?(?:hr|hour)',
            r'hourly\s*rate[:\s]+\$?(\d+(?:[.,]\d{2})?)',
            r'€\s*(\d+(?:[.,]\d{2})?)\s*(?:/\s*)?(?:h|hr|hour|heure)',
            r'(\d+(?:[.,]\d{2})?)\s*€\s*(?:/\s*)?(?:h|hr|heure)',
            r'(\d+(?:[.,]\d{2})?)\s*(?:eur|euros?)\s*(?:/\s*)?(?:h|hr|heure)',
            r'tarif\s*(?:horaire\s*)?[:\s]*(\d+(?:[.,]\d{2})?)',
            r'taux\s*(?:horaire\s*)?[:\s]*(\d+(?:[.,]\d{2})?)',
            r'(\d+(?:[.,]\d{2})?)\s*(?:dt|tnd|din)\s*/\s*h',
            r'rate[:\s]+(\d+(?:[.,]\d{2})?)\s*(?:per\s*hour)?',
        ]

        for pattern in rate_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                try:
                    return match.group(1).replace(',', '.')
                except:
                    continue
        return ""

    def _extract_location(self, text: str) -> str:
        """Extract location using multiple strategies"""
        lines = [line.strip() for line in text.split('\n') if line.strip()]
        text_lower = text.lower()

        # Strategy 1: Explicit address patterns
        # US address: City, ST 12345
        us_match = re.search(r'([A-Z][a-zA-Z\s]+),\s*([A-Z]{2})\s*(\d{5})?', text)
        if us_match:
            candidate = us_match.group(0)
            if not any(x in candidate.lower() for x in TITLE_KEYWORDS[:20]):
                return candidate.strip()

        # FR address: 75000 Paris or Paris (75)
        fr_match = re.search(r'(\d{5})\s+([A-ZÀ-Ú][a-zà-ú]+(?:\s+[A-ZÀ-Ú][a-zà-ú]+)?)', text)
        if fr_match:
            return fr_match.group(0).strip()

        fr_match2 = re.search(r'([A-ZÀ-Ú][a-zà-ú]+)\s*\((\d{2,5})\)', text)
        if fr_match2:
            city = fr_match2.group(1)
            for known_city in CITIES_DB:
                if city.lower() == known_city:
                    # Look for country on same or nearby line
                    return self._find_location_context(lines, city)

        # Strategy 2: Known city match
        for i, line in enumerate(lines[:25]):
            line_lower = line.lower()
            for city in CITIES_DB:
                if re.search(r'\b' + re.escape(city) + r'\b', line_lower):
                    # Try to get "City, Country" format
                    loc = self._format_location_from_line(line, city)
                    if loc:
                        return loc[:MAX_LOCATION_LEN]

        # Strategy 3: "City, Country" pattern
        for line in lines[:25]:
            m = re.search(r'([A-ZÀ-Ú][a-zà-ú]+(?:\s+[A-ZÀ-Ú][a-zà-ú]+)?)\s*,\s*([A-ZÀ-Ú][a-zà-ú]+(?:\s+[A-ZÀ-Ú][a-zà-ú]+)?)', line)
            if m:
                candidate = m.group(0)
                if not any(x in candidate.lower() for x in TITLE_KEYWORDS[:20]):
                    return candidate.strip()

        # Strategy 4: Country only
        for country in COUNTRIES_DB:
            if re.search(r'\b' + re.escape(country) + r'\b', text_lower):
                return country.title()

        return ""

    def _find_location_context(self, lines: List[str], city: str) -> str:
        """Find full location context around a city mention"""
        for line in lines[:25]:
            if city.lower() in line.lower():
                # Check for country in same line
                line_lower = line.lower()
                for country in COUNTRIES_DB:
                    if country in line_lower:
                        return f"{city}, {country.title()}"
                return city
        return city

    def _format_location_from_line(self, line: str, city: str) -> str:
        """Format a location from a line containing a known city"""
        line_lower = line.lower()

        # Check for "City, Country" or "City - Country"
        for country in COUNTRIES_DB:
            if country in line_lower:
                return f"{city.title()}, {country.title()}"

        # Check for zip code nearby
        zip_match = re.search(r'\b\d{4,5}\b', line)
        if zip_match:
            return f"{city.title()} {zip_match.group(0)}"

        # Simple "City (dept)"
        dept_match = re.search(r'\((\d{2,5})\)', line)
        if dept_match:
            return f"{city.title()} ({dept_match.group(1)})"

        return city.title()

    def _extract_skills(self, text: str) -> List[str]:
        """Extract skills using section detection + inline keyword matching"""
        found_skills = []

        # Strategy 1: Find skills section
        lines = text.split('\n')
        skill_headers = SECTION_HEADERS['skills']
        skill_start = -1

        for i, line in enumerate(lines):
            clean = re.sub(r'[:\-–—_*#]', '', line).strip().lower()
            if clean in skill_headers:
                skill_start = i + 1
                break

        if skill_start != -1:
            raw_skills = " ".join(lines[skill_start:skill_start + 10])
            parts = re.split(r'[,|•·\n;]', raw_skills)
            for part in parts:
                s = part.strip()
                if s and 1 < len(s) < 50:
                    # Stop if we hit a section header
                    clean = re.sub(r'[:\-–—_*#]', '', s).strip().lower()
                    is_header = False
                    for cat, headers in SECTION_HEADERS.items():
                        if cat != 'skills' and clean in headers:
                            is_header = True
                            break
                    if is_header:
                        break
                    found_skills.append(s)

        # Strategy 2: Inline detection from KNOWN_SKILLS
        text_lower = text.lower()
        existing_lower = {s.lower() for s in found_skills}
        for skill in KNOWN_SKILLS:
            if skill in existing_lower:
                continue
            # Skip ambiguous short skills in inline detection
            if skill in _SHORT_AMBIGUOUS_SKILLS:
                continue
            if len(skill) <= 3:
                # For short skills, require exact word boundary
                if re.search(r'\b' + re.escape(skill) + r'\b', text_lower):
                    found_skills.append(skill.upper())
                    existing_lower.add(skill)
            else:
                if skill in text_lower:
                    found_skills.append(skill.title())
                    existing_lower.add(skill)

        # Deduplicate (case-insensitive)
        seen = set()
        unique_skills = []
        for s in found_skills:
            key = s.lower().strip()
            if key and key not in seen:
                seen.add(key)
                unique_skills.append(s.strip())

        return unique_skills[:MAX_SKILLS]

    def _extract_phone(self, text: str) -> str:
        """Extract phone number using regex with broader support for formats"""
        # Specific regional patterns
        tn_pattern = r'\b[24579]\d{7}\b' # Tunisian: 8 digits starting with mobile/fixe codes
        fr_pattern = r'\b0[1-9](?:[ ./-]?\d{2}){4}\b' # French: 10 digits starting with 0

        # General patterns
        patterns = [
            tn_pattern,
            fr_pattern,
            # International with optional + and various separators
            r'\+?\d{1,3}(?:[ ./-]?\(?\d{1,4}\)?){2,5}',
            # US/General format
            r'\(?\d{3}\)?[-. ]?\d{3}[-. ]?\d{4}\b',
            # Any sequence of 8-15 digits
            r'\b\d{8,15}\b'
        ]
        
        found_numbers = []
        for pattern in patterns:
            matches = re.findall(pattern, text)
            for match in matches:
                # Basic validation: must have between 8 and 15 digits
                digits = re.sub(r'\D', '', match)
                if 8 <= len(digits) <= 15:
                    found_numbers.append(match.strip())
        
        # Return the first one found that looks the most like a phone number
        if found_numbers:
            # Prefer numbers with '+' or separators as they are less likely to be random IDs
            for num in found_numbers:
                if '+' in num or any(s in num for s in [' ', '.', '-', '/']):
                    return num
            return found_numbers[0]
        return ""

    def _extract_email(self, text: str) -> str:
        """Extract email address using robust regex"""
        # More inclusive email regex
        pattern = r'\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b'
        match = re.search(pattern, text)
        if match:
            return match.group(0).lower().strip()
        return ""

    # ─────────────────────────────────────────────────────────────
    # Confidence scoring (like certificate_analyzer)
    # ─────────────────────────────────────────────────────────────
    def _calculate_confidence(self, title, bio, experience, rate, location, skills) -> int:
        """Calculate confidence score based on extracted fields (0-100)"""
        scores = []
        reasons = []

        # Title
        if title and len(title) > 3:
            scores.append(90)
            reasons.append(f"Title found: '{title[:40]}'")
        else:
            scores.append(10)
            reasons.append("No title found")

        # Bio
        if bio and len(bio) > 80:
            scores.append(85)
        elif bio and len(bio) > 30:
            scores.append(60)
        elif bio:
            scores.append(30)
        else:
            scores.append(5)

        # Experience
        if experience and experience > 0:
            scores.append(80)
        else:
            scores.append(30)

        # Location
        if location:
            scores.append(85)
        else:
            scores.append(20)

        # Skills
        if skills and len(skills) >= 5:
            scores.append(90)
        elif skills and len(skills) >= 2:
            scores.append(70)
        elif skills:
            scores.append(40)
        else:
            scores.append(10)

        # Rate (optional, doesn't penalize much)
        if rate:
            scores.append(80)

        # Final average
        if scores:
            confidence = int(sum(scores) / len(scores))
        else:
            confidence = 0

        return max(0, min(100, confidence))

    # ─────────────────────────────────────────────────────────────
    # Utilities
    # ─────────────────────────────────────────────────────────────
    def _empty_result(self, error: str = "") -> Dict[str, Any]:
        """Return empty result structure aligned with WorkerProfile"""
        return self._normalize_for_worker_profile({
            'title': '',
            'bio': '',
            'experience_years': None,
            'hourly_rate': '',
            'location': '',
            'skills': [],
            'phoneNumber': '',
            'email': '',
            'confidence': 0,
            'error': error,
        })

    def _normalize_for_worker_profile(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Normalize parser output to WorkerProfile schema.
        Ensures all types of CVs produce valid WorkerProfile attributes.
        """
        title = (data.get('title') or '').strip()[:MAX_TITLE_LEN]
        bio = (data.get('bio') or '').strip()[:MAX_BIO_LEN]
        location = (data.get('location') or '').strip()[:MAX_LOCATION_LEN]

        try:
            experience_years = int(data.get('experience_years') or 0)
            experience_years = max(0, experience_years) if experience_years is not None else None
        except (TypeError, ValueError):
            experience_years = None

        raw_rate = data.get('hourly_rate')
        if raw_rate is None:
            hourly_rate = ''
        else:
            raw_rate = str(raw_rate).strip()
            digits = re.sub(r'[^\d.,]', '', raw_rate.replace(',', '.'))
            if digits:
                parts = digits.split('.')
                if len(parts) == 1:
                    hourly_rate = parts[0]
                else:
                    hourly_rate = parts[0] + '.' + (parts[1][:2] if len(parts[1]) > 2 else parts[1])
            else:
                hourly_rate = ''

        skills = data.get('skills') or []
        if isinstance(skills, str):
            skills = [s.strip() for s in skills.split(',') if s.strip()]
        skills = [str(s).strip() for s in skills if s][:MAX_SKILLS]

        confidence = data.get('confidence', 0)
        out = {
            'title': title,
            'bio': bio,
            'experience_years': experience_years,
            'hourly_rate': hourly_rate,
            'location': location,
            'skills': skills,
            'phoneNumber': (data.get('phoneNumber') or '').strip(),
            'email': (data.get('email') or '').lower().strip(),
            'confidence': min(100, max(0, confidence)),
        }
        if 'error' in data:
            out['error'] = data['error']
        if 'strategy' in data:
            out['strategy'] = data['strategy']
        return out
