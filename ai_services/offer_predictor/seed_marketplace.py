"""
seed_marketplace.py
Populates the Orion database with realistic marketplace data inspired by
real Upwork and Fiverr job listings, budgets, and freelancer profiles.

Service requests always have status='OPEN'.
Offers are ACCEPTED or REJECTED based on realistic acceptance logic.
"""
import random
import os
from faker import Faker
from db_config import get_connection
from datetime import datetime, timedelta

fake = Faker()

# ─── Real Upwork/Fiverr-inspired service request data ──────────────────────────
# Format: (title, description, budget_min, budget_max, duration_days)
SERVICE_REQUEST_TEMPLATES = [
    # Web Development
    (
        "Build a full-stack e-commerce website with React and Node.js",
        "We need a complete e-commerce platform. Requirements: product catalog with search/filter, shopping cart, Stripe payment integration, user authentication (JWT), admin dashboard for order management, and mobile-responsive design. Tech stack: React frontend, Node.js/Express backend, PostgreSQL database.",
        800, 4000, 30
    ),
    (
        "Develop a custom WordPress website with WooCommerce",
        "Looking for an experienced WordPress developer to build a professional website for our retail business. Needs: custom theme matching our brand, WooCommerce setup with 50+ products, payment gateway integration (PayPal + Stripe), SEO optimization, and speed optimization.",
        300, 1500, 14
    ),
    (
        "Create a SaaS dashboard with Next.js and Tailwind CSS",
        "We need a modern SaaS analytics dashboard. Features: real-time data charts (Chart.js), user management, subscription billing (Stripe), dark/light mode, responsive layout, and REST API integration. Must be production-ready with proper error handling.",
        600, 3000, 21
    ),
    (
        "Fix bugs and optimize performance of existing Laravel application",
        "Our Laravel 10 application has performance issues and several bugs. Need an experienced developer to: profile and fix N+1 query issues, implement Redis caching, fix 5 reported bugs (documented in Jira), upgrade dependencies, and write unit tests for critical modules.",
        200, 800, 7
    ),
    (
        "Build a REST API with Django REST Framework",
        "Develop a scalable REST API for our mobile app. Requirements: JWT authentication, CRUD endpoints for users/products/orders, file upload support, rate limiting, Swagger documentation, and deployment to AWS EC2. Must follow RESTful best practices.",
        400, 2000, 14
    ),
    (
        "Develop a real-time chat application with Socket.io",
        "Build a real-time messaging app. Features: private and group chats, message read receipts, file/image sharing, online presence indicators, push notifications, and message history. Backend: Node.js with Socket.io. Frontend: React.",
        500, 2500, 21
    ),
    (
        "Create a booking and appointment scheduling system",
        "We need a booking system for our medical clinic. Requirements: calendar view with availability management, patient registration, automated email/SMS reminders, payment collection, admin panel, and Google Calendar sync.",
        600, 3000, 28
    ),
    (
        "Migrate website from PHP to Python/Django",
        "Migrate our existing PHP 7 website to Django 4. The site has ~20 pages, a MySQL database with 15 tables, user authentication, and a basic CMS. Need zero-downtime migration with full data integrity.",
        400, 1800, 21
    ),

    # Mobile Development
    (
        "Develop a cross-platform mobile app with Flutter",
        "Build a fitness tracking app using Flutter (iOS + Android). Features: workout logging, progress charts, calorie counter, social sharing, push notifications, and integration with Apple Health / Google Fit. Must be published to both app stores.",
        800, 4000, 45
    ),
    (
        "Build an iOS app for restaurant ordering",
        "Native iOS app (Swift) for our restaurant chain. Features: menu browsing with photos, cart and checkout, order tracking, loyalty points, push notifications for order status, and integration with our existing POS system.",
        1000, 5000, 30
    ),
    (
        "Create a React Native delivery tracking app",
        "Build a delivery tracking app similar to Uber Eats. Two apps needed: customer app (track orders, rate drivers) and driver app (accept orders, navigation). Real-time location tracking with Google Maps API.",
        1200, 6000, 45
    ),

    # Design
    (
        "Design a complete brand identity for a tech startup",
        "We are a new fintech startup and need a full brand identity package. Deliverables: logo (3 concepts + revisions), color palette, typography guide, business card design, letterhead, social media kit (profile pictures, banners, post templates), and brand guidelines PDF.",
        300, 1500, 10
    ),
    (
        "Create UI/UX design for a mobile banking app",
        "Design a modern, user-friendly mobile banking app. Deliverables: user research report, user flow diagrams, wireframes (low-fi), high-fidelity mockups for 20+ screens, interactive prototype in Figma, and design system/component library.",
        500, 2500, 14
    ),
    (
        "Redesign our SaaS product landing page",
        "Our current landing page has a low conversion rate. Need a complete redesign: above-the-fold hero section, features section, pricing table, testimonials, FAQ, and CTA. Must be optimized for conversions. Deliver Figma file + HTML/CSS.",
        200, 1000, 7
    ),
    (
        "Design product packaging for cosmetics brand",
        "Design packaging for 5 skincare products (moisturizer, serum, toner, eye cream, face wash). Deliverables: 3D mockups, print-ready files (CMYK), label designs following FDA guidelines, and brand-consistent color scheme.",
        400, 2000, 14
    ),

    # Data Science & ML
    (
        "Build a machine learning model for customer churn prediction",
        "We have 2 years of customer data and need a churn prediction model. Requirements: exploratory data analysis, feature engineering, model training (try multiple algorithms), hyperparameter tuning, model evaluation report, and a simple API endpoint for predictions.",
        500, 2500, 21
    ),
    (
        "Create a Python web scraper for competitor price monitoring",
        "Build an automated web scraper to monitor competitor prices across 5 e-commerce websites. Requirements: scrape product name, price, availability daily, store in PostgreSQL, send email alerts when price drops >10%, and a simple dashboard to view trends.",
        200, 800, 7
    ),
    (
        "Develop a recommendation engine for our e-commerce platform",
        "Implement a product recommendation system. Approaches to explore: collaborative filtering, content-based filtering, and hybrid. Must integrate with our existing Django backend and MySQL database. Target: increase average order value by 15%.",
        600, 3000, 28
    ),
    (
        "Build an NLP sentiment analysis tool for customer reviews",
        "Analyze customer reviews from Amazon and Google. Requirements: scrape/import reviews, train a sentiment classifier (positive/negative/neutral), topic modeling to identify common complaints, and a dashboard showing sentiment trends over time.",
        400, 1800, 14
    ),

    # Content & Writing
    (
        "Write 20 SEO-optimized blog articles for SaaS company",
        "We need 20 long-form blog articles (1500-2000 words each) on topics related to project management software. Requirements: keyword research for each article, SEO-optimized structure (H1/H2/H3), internal linking suggestions, meta descriptions, and original research/statistics.",
        400, 1200, 30
    ),
    (
        "Create technical documentation for REST API",
        "Write comprehensive API documentation for our REST API (50+ endpoints). Deliverables: getting started guide, authentication guide, endpoint reference with request/response examples, error codes reference, code samples in Python/JavaScript/PHP, and Postman collection.",
        300, 1000, 14
    ),
    (
        "Translate website and app content from English to Arabic",
        "Translate 80 pages of website content and 500 app strings from English to Arabic. Must be native Arabic speaker with tech industry experience. Deliverables: translated content in original format (HTML/JSON), glossary of technical terms, and QA review.",
        200, 800, 10
    ),

    # DevOps & Cloud
    (
        "Set up AWS infrastructure with Terraform and CI/CD pipeline",
        "Configure production-ready AWS infrastructure. Requirements: VPC setup, EC2 auto-scaling, RDS (PostgreSQL), S3 + CloudFront, Route53, SSL certificates, GitHub Actions CI/CD pipeline, monitoring with CloudWatch, and infrastructure as code with Terraform.",
        500, 2500, 14
    ),
    (
        "Dockerize existing application and set up Kubernetes cluster",
        "Containerize our Node.js microservices (5 services) and deploy to Kubernetes. Requirements: Dockerfiles for each service, docker-compose for local dev, Kubernetes manifests (Deployments, Services, Ingress), Helm charts, and monitoring with Prometheus/Grafana.",
        600, 3000, 21
    ),
    (
        "Configure server security and perform penetration testing",
        "Perform a security audit and penetration test on our web application. Deliverables: vulnerability assessment report, OWASP Top 10 testing, SQL injection and XSS testing, server hardening recommendations, and implementation of security fixes.",
        400, 2000, 10
    ),
]

# ─── Realistic cover letter templates (Upwork/Fiverr style) ────────────────────
COVER_LETTER_TEMPLATES_GOOD = [
    """Hi,

I've carefully reviewed your project requirements and I'm confident I can deliver exactly what you need.

I have {years} years of experience in {specialty} and have completed {projects}+ similar projects. My recent work includes building {example_project} which had similar requirements to yours.

Here's my approach for your project:
1. {step1}
2. {step2}
3. {step3}

I'll provide daily progress updates and am available for calls anytime. My deliverables will include full source code, documentation, and {support} days of post-delivery support.

I noticed you need this in {duration} days — that's very achievable. I can start immediately.

Looking forward to working with you!""",

    """Hello,

Thank you for posting this project. After reading through your requirements carefully, I believe I'm the perfect fit.

My background: {years} years specializing in {specialty}, {projects}+ completed projects, and a {rating}/5 rating from {reviews} satisfied clients.

Why choose me:
- I've built {example_project} with similar complexity
- I follow {methodology} to ensure quality
- I communicate proactively and meet deadlines

For this project specifically, I would {specific_approach}. This ensures you get a production-ready solution, not just a prototype.

Timeline: I can complete this in {timeline} days (within your {duration}-day deadline).
Price: My quote of ${price} includes everything listed in your requirements plus {bonus}.

Let's schedule a quick call to discuss the details!""",

    """Hi there,

I specialize in {specialty} and your project is exactly the type of work I excel at.

Quick overview of my relevant experience:
- {years} years in {specialty}
- Completed {projects}+ projects on this platform
- {rating}/5 average rating with {reviews} reviews
- Recent similar project: {example_project}

My plan for your project:
Phase 1 ({phase1_days} days): {step1}
Phase 2 ({phase2_days} days): {step2}
Phase 3 ({phase3_days} days): {step3}

I'll use {methodology} and provide you with a staging environment for review before final delivery.

I'm available to start today. Let me know if you have any questions!""",
]

COVER_LETTER_TEMPLATES_AVERAGE = [
    """Hello,

I can help with your project. I have experience in {specialty} and have done similar work before.

I will complete the project within your timeline and budget. My approach will be straightforward and I'll keep you updated on progress.

Please let me know if you'd like to discuss further.""",

    """Hi,

I'm interested in your project. I have {years} years of experience in {specialty} and can deliver quality work.

I've worked on {projects} similar projects and understand what's needed. I can start soon and will communicate regularly.

Looking forward to hearing from you.""",

    """Hello,

I reviewed your requirements and I'm confident I can complete this project. I have relevant experience in {specialty}.

My rate is competitive and I deliver on time. I'll provide regular updates throughout the project.

Let me know if you want to discuss.""",
]

COVER_LETTER_TEMPLATES_BAD = [
    "I can do this project. I have experience. Please hire me.",
    "Hello, I am interested in your project. I will do it for the price. Contact me.",
    "I can complete this. I have done similar work. Let me know.",
    "Hi, I am available to work on this project immediately. I have skills in this area.",
]

SPECIALTIES = {
    "Web Development": ["full-stack web development", "React and Node.js", "Django and Python", "Laravel/PHP", "Next.js"],
    "Mobile Development": ["Flutter development", "iOS/Swift development", "React Native", "Android/Kotlin"],
    "Design": ["UI/UX design", "brand identity design", "Figma and Adobe XD", "graphic design"],
    "Data Science & ML": ["machine learning and data science", "Python data analysis", "NLP and AI", "computer vision"],
    "Content & Writing": ["technical writing", "SEO content creation", "copywriting", "translation"],
    "DevOps & Cloud": ["AWS and cloud infrastructure", "DevOps and CI/CD", "Docker and Kubernetes", "server administration"],
}

EXAMPLE_PROJECTS = [
    "a multi-vendor marketplace for a US client",
    "an enterprise SaaS dashboard with 10,000+ users",
    "a mobile app that reached 50,000 downloads",
    "an e-commerce platform processing $1M+ monthly",
    "a real-time analytics system for a fintech company",
    "a healthcare management system for 5 clinics",
    "a social media platform with 20,000 active users",
    "an AI-powered recommendation engine",
]

METHODOLOGIES = ["Agile/Scrum", "Test-Driven Development (TDD)", "clean architecture principles", "SOLID principles", "Git Flow"]

STEPS = [
    "Requirements analysis and technical specification",
    "Database design and API architecture",
    "Frontend development with responsive design",
    "Backend development and API integration",
    "Testing (unit, integration, and E2E)",
    "Deployment and performance optimization",
    "Code review and documentation",
    "User acceptance testing and bug fixes",
]

BONUSES = [
    "30 days of free bug fixes",
    "free deployment assistance",
    "a 1-hour training session",
    "complete documentation",
    "free minor revisions for 2 weeks",
]

DELIVERABLES_BY_CATEGORY = {
    "Web Development": [
        "- Complete source code (GitHub repository)\n- Deployed and live application\n- Technical documentation\n- 30-day post-delivery support for bug fixes",
        "- Full source code with comments\n- Database schema and migration files\n- API documentation (Swagger/Postman)\n- Deployment guide for your server",
        "- Production-ready codebase\n- Unit and integration tests\n- CI/CD pipeline configuration\n- Video walkthrough of the application",
    ],
    "Mobile Development": [
        "- Source code for iOS and Android\n- Published to App Store and Google Play\n- App store listing assets (screenshots, description)\n- 30-day post-launch support",
        "- Complete Flutter/React Native codebase\n- Build files (.ipa and .apk)\n- User manual\n- Push notification setup guide",
    ],
    "Design": [
        "- All design files (Figma/Adobe XD)\n- Exported assets in multiple formats (PNG, SVG, PDF)\n- Style guide and component library\n- Print-ready files if applicable",
        "- High-fidelity mockups for all screens\n- Interactive prototype link\n- Design system documentation\n- Handoff-ready files for developers",
    ],
    "Data Science & ML": [
        "- Jupyter notebooks with full analysis\n- Trained model files (.pkl)\n- Performance evaluation report\n- API endpoint for predictions\n- Documentation on how to retrain",
        "- Complete Python codebase\n- Data pipeline scripts\n- Model accuracy report\n- Deployment instructions",
    ],
    "Content & Writing": [
        "- All articles in Google Docs format\n- SEO analysis report\n- Keyword research spreadsheet\n- Meta descriptions for each article",
        "- Content in requested format (Word/HTML/Markdown)\n- Plagiarism report\n- Revision history\n- Style guide used",
    ],
    "DevOps & Cloud": [
        "- Infrastructure as Code (Terraform/CloudFormation)\n- CI/CD pipeline configuration\n- Monitoring and alerting setup\n- Runbook and documentation",
        "- Docker/Kubernetes configuration files\n- Deployment scripts\n- Security audit report\n- Architecture diagram",
    ],
}


def get_category_name(cursor, category_id):
    cursor.execute("SELECT name FROM worker_category WHERE id = %s", (category_id,))
    row = cursor.fetchone()
    return row[0] if row else "Web Development"


def generate_cover_letter(quality, specialty, worker_rating, worker_reviews, duration):
    """Generate a realistic cover letter based on quality tier."""
    years = random.randint(3, 10) if quality == 'good' else random.randint(1, 5)
    projects = random.randint(30, 150) if quality == 'good' else random.randint(5, 30)
    rating = round(worker_rating, 1)
    reviews = worker_reviews
    example = random.choice(EXAMPLE_PROJECTS)
    methodology = random.choice(METHODOLOGIES)
    step_list = random.sample(STEPS, 3)
    bonus = random.choice(BONUSES)
    phase_days = [max(1, duration // 3), max(1, duration // 3), max(1, duration - 2 * (duration // 3))]

    if quality == 'good':
        template = random.choice(COVER_LETTER_TEMPLATES_GOOD)
        return template.format(
            years=years, specialty=specialty, projects=projects,
            rating=rating, reviews=reviews, example_project=example,
            methodology=methodology, step1=step_list[0], step2=step_list[1], step3=step_list[2],
            bonus=bonus, duration=duration, timeline=max(1, duration - 2),
            price=random.randint(200, 2000),
            specific_approach=f"start with a detailed technical specification before writing any code",
            support=30, phase1_days=phase_days[0], phase2_days=phase_days[1], phase3_days=phase_days[2],
        )
    elif quality == 'average':
        template = random.choice(COVER_LETTER_TEMPLATES_AVERAGE)
        return template.format(
            years=years, specialty=specialty, projects=projects,
            rating=rating, reviews=reviews,
        )
    else:
        return random.choice(COVER_LETTER_TEMPLATES_BAD)


def seed_users(cursor, count=80):
    print(f"Seeding {count} users...")
    users = []
    for i in range(count):
        is_worker = i % 2 == 0
        role = 'ROLE_WORKER' if is_worker else 'ROLE_CLIENT'
        first_name = fake.first_name()
        last_name = fake.last_name()
        email = f"{first_name.lower()}.{last_name.lower()}{i}@orion-seed.com"
        username = f"{first_name.lower()}_{last_name.lower()}_{i}_{random.randint(1000, 9999)}"

        if is_worker:
            # Upwork/Fiverr rating distribution: skewed toward 4.0-5.0
            rating_avg = round(min(5.0, max(1.0, random.gauss(4.3, 0.5))), 2)
            # Reviews correlate with rating (top workers get more reviews)
            base = int((rating_avg - 1) * 25)
            total_reviews = max(0, base + random.randint(0, 50))
        else:
            rating_avg = None
            total_reviews = 0

        created_at = datetime.now() - timedelta(days=random.randint(60, 1000))
        users.append((
            email, username,
            '$2y$13$Cb.H.placeholder.hash.for.seed.data.only',
            role, 'ACTIVE', first_name, last_name,
            created_at, rating_avg, total_reviews
        ))

    query = """
    INSERT INTO users (email, username, password_hash, role, status, first_name, last_name, created_at, rating_avg, total_reviews, updated_at, phone_verified, email_verified, two_factor_enabled, account_balance, wallet_currency)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), 0, 1, 0, 0, 'USD')
    """
    cursor.executemany(query, users)
    print(f"  [OK] Inserted {count} users")


def get_user_ids(cursor, role):
    cursor.execute("SELECT id, rating_avg, total_reviews FROM users WHERE role = %s", (role,))
    return cursor.fetchall()


def seed_categories(cursor):
    print("Seeding categories...")
    categories = [
        ('Web Development', 'Websites, web apps, APIs, and CMS platforms'),
        ('Mobile Development', 'iOS, Android, and cross-platform mobile apps'),
        ('Graphic Design', 'Logos, brand identity, UI design, and print'),
        ('Data Science & ML', 'Machine learning, data analysis, and AI'),
        ('Writing & Content', 'Blog posts, copywriting, technical writing, and translation'),
        ('DevOps & Cloud', 'AWS, Docker, Kubernetes, CI/CD, and server management'),
        ('Video & Animation', 'Explainer videos, motion graphics, and editing'),
        ('Digital Marketing', 'SEO, PPC, social media, and email marketing'),
    ]
    cursor.execute("SELECT count(*) FROM worker_category")
    if cursor.fetchone()[0] == 0:
        query = "INSERT INTO worker_category (name, description, created_at) VALUES (%s, %s, NOW())"
        cursor.executemany(query, categories)
        print(f"  [OK] Inserted {len(categories)} categories")
    else:
        print("  [OK] Categories already exist")


def get_category_ids(cursor):
    cursor.execute("SELECT id, name FROM worker_category")
    return cursor.fetchall()


def seed_service_requests(cursor, client_ids, category_rows, count=80):
    print(f"Seeding {count} service requests...")
    requests = []

    # Map category names to IDs
    cat_map = {name: cid for cid, name in category_rows}

    # Assign templates to categories
    category_templates = {
        "Web Development": SERVICE_REQUEST_TEMPLATES[0:8],
        "Mobile Development": SERVICE_REQUEST_TEMPLATES[8:11],
        "Graphic Design": SERVICE_REQUEST_TEMPLATES[11:15],
        "Data Science & ML": SERVICE_REQUEST_TEMPLATES[15:19],
        "Writing & Content": SERVICE_REQUEST_TEMPLATES[19:22],
        "DevOps & Cloud": SERVICE_REQUEST_TEMPLATES[22:25],
    }

    for _ in range(count):
        # Pick a category that has templates
        cat_name = random.choice(list(category_templates.keys()))
        cat_id = cat_map.get(cat_name)
        if not cat_id:
            continue

        template = random.choice(category_templates[cat_name])
        title, description, b_min_base, b_max_base, dur_base = template

        # Add realistic variation
        variation = random.uniform(0.8, 1.3)
        budget_min = max(50, int(b_min_base * variation))
        budget_max = max(budget_min + 100, int(b_max_base * variation))
        duration = max(3, dur_base + random.randint(-3, 7))

        client_id = random.choice(client_ids)[0]
        created_at = datetime.now() - timedelta(days=random.randint(1, 90))

        requests.append((
            client_id, cat_id, title, description,
            budget_min, budget_max, 'OPEN', duration, created_at
        ))

    query = """
    INSERT INTO service_request (client_id, category_id, title, description, budget_min, budget_max, status, duration, created_at)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    cursor.executemany(query, requests)
    print(f"  [OK] Inserted {len(requests)} service requests")


def get_request_ids(cursor):
    cursor.execute("""
        SELECT sr.id, sr.budget_min, sr.budget_max, sr.duration, wc.name as category_name
        FROM service_request sr
        JOIN worker_category wc ON sr.category_id = wc.id
    """)
    return cursor.fetchall()


def compute_acceptance_probability(price, b_min, b_max, worker_rating, quality, message_len, has_deliverables, total_reviews):
    """
    Realistic acceptance probability modeled on Upwork/Fiverr dynamics.
    Key insight: price competitiveness + worker reputation are the top factors.
    """
    prob = 0.40  # Base probability

    # 1. Price factor (most important on real platforms)
    if b_max > 0:
        ratio = price / b_max
        if ratio <= 0.70:
            prob += 0.30   # Significantly under budget — very attractive
        elif ratio <= 0.90:
            prob += 0.20   # Under budget
        elif ratio <= 1.00:
            prob += 0.10   # At budget
        elif ratio <= 1.15:
            prob -= 0.05   # Slightly over — common on Upwork, still possible
        elif ratio <= 1.40:
            prob -= 0.20   # Over budget
        else:
            prob -= 0.35   # Way over budget

    # 2. Worker reputation (Upwork JSS / Fiverr level)
    if worker_rating >= 4.8:
        prob += 0.25   # Top Rated
    elif worker_rating >= 4.5:
        prob += 0.15   # Rising Talent
    elif worker_rating >= 4.0:
        prob += 0.05
    elif worker_rating >= 3.5:
        prob -= 0.05
    else:
        prob -= 0.20   # Low rating — rarely hired

    # 3. Review count (experience signal)
    if total_reviews >= 50:
        prob += 0.10
    elif total_reviews >= 20:
        prob += 0.05
    elif total_reviews >= 5:
        prob += 0.02
    else:
        prob -= 0.05   # New freelancer — clients are cautious

    # 4. Proposal quality (message length)
    if message_len >= 500:
        prob += 0.12   # Detailed, personalized proposal
    elif message_len >= 200:
        prob += 0.06
    elif message_len >= 80:
        prob += 0.00
    else:
        prob -= 0.15   # Generic/lazy proposal

    # 5. Deliverables listed
    if has_deliverables:
        prob += 0.08

    # 6. Human Variance (The "Subjective" factor)
    # Even a perfect offer might be rejected, or a poor one accepted for unknown reasons.
    human_variance = random.uniform(-0.05, 0.05)
    prob += human_variance

    # Clamp to realistic range [0.02, 0.95]
    return max(0.02, min(0.95, prob))


def seed_offers(cursor, request_rows, workers, count_per_request=5):
    print(f"Seeding offers ({count_per_request} per request)...")
    offers = []

    deliverables_map = {
        "Web Development": DELIVERABLES_BY_CATEGORY["Web Development"],
        "Mobile Development": DELIVERABLES_BY_CATEGORY["Mobile Development"],
        "Graphic Design": DELIVERABLES_BY_CATEGORY["Design"],
        "Data Science & ML": DELIVERABLES_BY_CATEGORY["Data Science & ML"],
        "Writing & Content": DELIVERABLES_BY_CATEGORY["Content & Writing"],
        "DevOps & Cloud": DELIVERABLES_BY_CATEGORY["DevOps & Cloud"],
    }

    for req in request_rows:
        req_id, b_min_dec, b_max_dec, duration, cat_name = req
        b_min = float(b_min_dec)
        b_max = float(b_max_dec)

        selected_workers = random.sample(workers, min(len(workers), count_per_request))

        for worker_row in selected_workers:
            worker_id, rating_dec, reviews = worker_row
            worker_rating = float(rating_dec) if rating_dec else 3.0
            total_reviews = int(reviews) if reviews else 0

            # Quality tier weighted by worker rating (better workers submit better proposals)
            if worker_rating >= 4.7:
                quality_weights = [0.75, 0.20, 0.05]
            elif worker_rating >= 4.2:
                quality_weights = [0.50, 0.35, 0.15]
            elif worker_rating >= 3.5:
                quality_weights = [0.25, 0.45, 0.30]
            else:
                quality_weights = [0.10, 0.30, 0.60]

            quality = random.choices(['good', 'average', 'bad'], weights=quality_weights)[0]

            # Price generation (Upwork/Fiverr patterns)
            if quality == 'good':
                # Good workers price competitively but not too cheap
                price = random.uniform(b_min * 0.85, b_max * 1.10)
                estimated_days = max(1, int(duration * random.uniform(0.75, 1.05)))
                revisions = random.randint(2, 5)
                priority = random.choices(['HIGH', 'MEDIUM'], weights=[0.3, 0.7])[0]
                is_urgent = random.random() < 0.15
            elif quality == 'average':
                # Average workers often price slightly over budget
                price = random.uniform(b_max * 0.95, b_max * 1.35)
                estimated_days = max(1, int(duration * random.uniform(1.0, 1.4)))
                revisions = random.randint(1, 3)
                priority = 'MEDIUM'
                is_urgent = False
            else:
                # Bad workers either underprice (no experience) or overprice (no awareness)
                if random.random() < 0.3:
                    price = random.uniform(b_min * 0.3, b_min * 0.7)  # Suspiciously cheap
                else:
                    price = random.uniform(b_max * 1.5, b_max * 3.0)  # Way overpriced
                estimated_days = max(1, int(duration * random.uniform(1.5, 2.5)))
                revisions = random.randint(0, 1)
                priority = 'LOW'
                is_urgent = False

            price = round(price, 2)

            # Get specialty for this category
            specialty_list = SPECIALTIES.get(cat_name, ["software development"])
            specialty = random.choice(specialty_list)

            # Generate cover letter
            message = generate_cover_letter(quality, specialty, worker_rating, total_reviews, duration)
            message_len = len(message)

            # Deliverables
            has_deliverables = quality == 'good' or (quality == 'average' and random.random() < 0.35)
            deliverable_options = deliverables_map.get(cat_name, DELIVERABLES_BY_CATEGORY["Web Development"])
            deliverables = random.choice(deliverable_options) if has_deliverables else None

            # Compute acceptance probability
            accept_prob = compute_acceptance_probability(
                price, b_min, b_max, worker_rating, quality,
                message_len, has_deliverables, total_reviews
            )
            is_accepted = random.random() < accept_prob
            status = 'ACCEPTED' if is_accepted else 'REJECTED'

            created_at = datetime.now() - timedelta(days=random.randint(1, 60))

            offers.append((
                price, estimated_days, message, deliverables,
                status, req_id, worker_id, created_at,
                revisions, 1 if is_urgent else 0, priority
            ))

    query = """
    INSERT INTO offer (price, estimated_time_days, message, deliverables, status, service_request_id, worker_id, created_at, included_revisions, is_urgent, priority_level)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    cursor.executemany(query, offers)

    accepted = sum(1 for o in offers if o[4] == 'ACCEPTED')
    rejected = sum(1 for o in offers if o[4] == 'REJECTED')
    print(f"  [OK] Inserted {len(offers)} offers ({accepted} accepted / {rejected} rejected)")
    print(f"  [OK] Acceptance rate: {accepted / len(offers) * 100:.1f}% (realistic target: 25-40%)")


def main():
    os.chdir(os.path.dirname(os.path.abspath(__file__)))

    conn = get_connection()
    if not conn:
        print("Could not connect to database.")
        return

    conn.autocommit = False
    cursor = conn.cursor()

    try:
        print("\n=== Orion Marketplace Seeder (Upwork/Fiverr Data) ===\n")

        # 1. Users
        cursor.execute("SELECT count(*) FROM users")
        user_count = cursor.fetchone()[0]
        if user_count < 200:
            seed_users(cursor, max(200 - user_count, 50))
            conn.commit()
        else:
            print(f"  [OK] Users already seeded ({user_count} users)")

        # 2. Categories
        seed_categories(cursor)
        conn.commit()

        # 3. Get IDs
        clients = get_user_ids(cursor, 'ROLE_CLIENT')
        workers = get_user_ids(cursor, 'ROLE_WORKER')
        cat_rows = get_category_ids(cursor)

        print(f"  Found: {len(clients)} clients, {len(workers)} workers, {len(cat_rows)} categories")

        if not clients or not workers or not cat_rows:
            print("ERROR: Missing required data. Cannot proceed.")
            return

        # 4. Service Requests (always OPEN)
        cursor.execute("SELECT count(*) FROM service_request")
        req_count = cursor.fetchone()[0]
        if req_count < 300:
            seed_service_requests(cursor, clients, cat_rows, max(300 - req_count, 100))
            conn.commit()
        else:
            print(f"  [OK] Service requests already seeded ({req_count} requests)")

        # 5. Offers
        cursor.execute("SELECT count(*) FROM offer")
        offer_count = cursor.fetchone()[0]
        if offer_count < 3000:
            req_ids = get_request_ids(cursor)
            seed_offers(cursor, req_ids, workers, count_per_request=5)
            conn.commit()
        else:
            print(f"  [OK] Offers already seeded ({offer_count} offers)")

        print("\n=== Seeding completed successfully! ===\n")

    except Exception as e:
        print(f"\nERROR: {e}")
        import traceback
        traceback.print_exc()
        conn.rollback()
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    main()
