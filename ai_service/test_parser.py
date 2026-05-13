"""Quick test for the self-contained CV parser"""
import json
from cv_parser import CvParser

p = CvParser()

# Simulate text that would come from OCR on Raphaël Martin's CV
cv_text = """Raphaël MARTIN
INTITULE DU POSTE / STAGE

PROFIL
Commercial diplômé, j'ai une expérience de 3 ans en tant qu'Assistant Commercial, et 4 ans en tant que Commercial chez DIOR. Je suis passionné et ai un bon sens relationnel que je saurai mettre au service de votre entreprise.

CONTACT
06 06 06 06 06
raphael.martin@gmail.com
Paris, France

COMPETENCES
Sens du contact
Communication
Capacité d'adaptation
Polyvalence
Logique
Rigueur
Autonomie

EXPERIENCES PROFESSIONNELLES
Commercial
DIOR, Paris 2019-2022
Assistant Commercial Export
ORANGE, Paris 2016-2019
Assistant Commercial Stagiaire
DANONE, Paris 2015

FORMATION
Licence Pro Commerce et Distribution
Université Sorbonne, Paris 2012-2015
BTS Négociations et digitalisation relation client
ESUP, Paris 2012-2015
"""

print("=" * 60)
print("TEST 1: French CV (Raphaël Martin)")
print("=" * 60)
result = p._extract_all_fields(cv_text)
print(json.dumps(result, indent=2, ensure_ascii=False))

# Test 2: English CV (Brian R. Baxter)
cv_text2 = """BRIAN R. BAXTER
GRAPHIC & WEB DESIGNER

ABOUT ME
Lorem ipsum is simply dummy text of the printing and typesetting industry. Creative professional with 8 years of experience in graphic and web design.

CONTACT ME
+1-789-310-6988
yourinfo@email.com
196 Prudence Street
Lincoln Park, MI 48146

JOB EXPERIENCE
SENIOR WEB DESIGNER        2020-Present
Creative Agency / Chicago
GRAPHIC DESIGNER            2015-2020
Creative Agency / Chicago
MARKETING MANAGER           2013-2015
Manufacturing Agency / NJ

SKILLS
Adobe Photoshop
Adobe Illustrator
Microsoft Word
Microsoft PowerPoint
HTML-5/CSS-3

EDUCATION
STANFORD UNIVERSITY
MASTER DEGREE GRADUATE
2015-2021
UNIVERSITY OF CHICAGO
BACHELOR DEGREE GRADUATE
2007-2010
"""

print("\n" + "=" * 60)
print("TEST 2: English CV (Brian R. Baxter)")
print("=" * 60)
result2 = p._extract_all_fields(cv_text2)
print(json.dumps(result2, indent=2, ensure_ascii=False))

# Test 3: French student CV (Emilie Michaud)
cv_text3 = """EMILIE MICHAUD
STAGE / JOB ÉTUDIANT

CONTACT
06 06 06 06 06
michaud.emilie@gmail.com
Paris (75) France

PROFIL
Étudiante en 3ème année de Licence, passionnée de langues étrangères et dotée d'un bon sens relationnel. Je recherche un emploi étudiant en tant qu'hôtesse d'accueil dans un hôtel.

LANGUES
Francais
Anglais
Italien

COMPETENCES
Sens du relationnel
Travail en équipe
Créativité
Ouverture d'esprit
Ponctualité
Photoshop
Montage vidéo

EXPÉRIENCES PROFESSIONNELLES
TRADUCTRICE STAGIAIRE
Traduc'entreprise, Paris (75) juillet 2022
ASSISTANTE DE DIRECTION STAGIAIRE
EDF, Paris (75) février 2014

BÉNÉVOLAT
BÉNÉVOLE
Le Secours Populaire, Paris (75) mars 2021
"""

print("\n" + "=" * 60)
print("TEST 3: French Student CV (Emilie Michaud)")
print("=" * 60)
result3 = p._extract_all_fields(cv_text3)
print(json.dumps(result3, indent=2, ensure_ascii=False))
