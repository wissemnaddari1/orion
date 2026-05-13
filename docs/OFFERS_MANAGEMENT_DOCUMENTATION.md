# Orion: Comprehensive Offers Management Documentation

The **Offers Management System** is a core module of the Orion Marketplace, facilitating the negotiation and contract initiation process between clients and workers. It integrates a high-performance PHP/Symfony backend with a specialized Python/FastAPI Machine Learning microservice.

---

## 👥 User Personas & Workflows

### 1. Worker (The Service Provider)
*   **Drafting Offers**: Workers can create detailed proposals with pricing, timelines, deliverables, and revision counts.
*   **AI Enhancement**: A live **AI Offer Assistant** provides real-time feedback, risk assessment (LOW/MEDIUM/HIGH), and actionable suggestions (e.g., "Adjust price to be more competitive") before submission.
*   **Offer Tracking**: A responsive dashboard with **AJAX-powered search and filters** to monitor the status of all sent proposals (Pending, Accepted, Rejected, Negotiating).
*   **Notifications**: Receives instant email alerts when a client accepts, rejects, or initiates a negotiation.

### 2. Client (The Service Requester)
*   **Reviewing Submissions**: A rich dashboard to view incoming offers for their service requests.
*   **AI Insight**: Every offer features an **AI Acceptance Probability** score (0-100%).
*   **AI Reasoning**: Interactive icons with tooltips explain *why* the AI gave a specific score (e.g., "Elite professional", "Premium price point").
*   **Offer Comparison**: A dedicated **Side-by-Side Comparison Panel** to evaluate multiple workers simultaneously based on price, rating, AI score, and delivery speed.
*   **Lifecycle Management**: One-click actions to Accept, Reject, or enter Negotiation mode.

### 3. Administrator (The Platform Manager)
*   **Global Oversight**: A master list of every offer on the platform.
*   **Performance Optimization**: AJAX-based listing that skips unnecessary heavy aggregations for faster response times.
*   **Bulk Management**: Efficient **Bulk Actions** (Archive, Delete, Mark as Read) for managing high volumes of data.

---

## 🛠️ Technical Architecture

### 1. Backend: Symfony (PHP 8.2+)
*   **`OfferPredictionService.php`**: Orchestrates communication with the AI microservice via a secure HTTP client.
*   **`OfferMailerService.php`**: Managed the transactional email lifecycle using Twig-based HTML templates.
*   **Controllers**:
    *   `Client\OfferController`: Manages the client's decision-making UI and comparison logic.
    *   `Worker\OfferAiController`: Acting as a proxy for real-time AI enhancement calls.
    *   `Admin\OfferController`: Handles bulk operations and performance-tuned global lists.

### 2. AI Intelligence: FastAPI (Python 3.10+)
*   **Model**: **Optimized Random Forest Classifier (v1.1)** with an **84% F1-score**.
*   **Feature Engineering**: Custom pipeline extracting 12 categorical and numerical signals including `price_ratio`, `deliverables_density`, and `urgency`.
*   **Reasoning Engine**: A model-driven system that uses internal feature weights to explain its predictions to the user.
*   **Automation**: A `run_pipeline.py` script that orchestrates the full **Seeding -> ETL -> Retraining** workflow.

### 3. Frontend: Modern UI (Vanilla JS & Twig)
*   **AJAX Core**: Seamless, zero-reload searching and filtering using the `fetch` API and Symfony partial rendering.
*   **Dynamic Components**: Glassmorphism-style cards, real-time score gauges, and interactive tooltips.
*   **Responsiveness**: Fully responsive templates optimized for both desktop comparison and mobile tracking.

---

## 📊 Database Schema (Key Entities)
*   **`offer`**: Stores core proposal data, relationships to workers/requests, and status.
*   **`service_request`**: The project context (budget, duration, category) used by the AI to evaluate the offer.
*   **`ml_offer_training`**: A specialized table containing extracted features used to train and refine the model.

---

## 📈 Platform Features Summary
| Feature | User Benefit | Technical Implementation |
| :--- | :--- | :--- |
| **Real-time AI Assistant** | Higher winning chance for workers. | Debounced AJAX -> FastAPI Enhancement Engine. |
| **AI Insights Tooltips** | Transparency in automated scoring. | Model Feature Importance -> JSON Logic -> Tooltip. |
| **AJAX Filters** | Fast navigation of high-volume lists. | Vanilla JS `URLSearchParams` -> Symfony Partials. |
| **Side-by-Side Panel** | Reduced "Compare & Contrast" friction. | Multi-Entity Fetch -> Horizontal Table Rendering. |
| **Email Notifications** | Real-time engagement. | Symfony Messenger/Mailer -> Twig HTML Templates. |
