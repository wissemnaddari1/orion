# Offers & Negotiation: Sprint Backlog

This backlog summarizes the existing and newly implemented functionalities across the Orion platform, structured for sprint planning and technical defense.

## 👥 Client Persona (The Requester)
*   **[EPIC] Offer Intelligence & Decision Support**
    *   **USER-101**: Side-by-Side Comparison Dashboard with interactive sorting (Price/AI Match).
    *   **USER-102**: Dynamic Badging (highlights **🏆 Best Value** and **⚡ Fastest** offers).
    *   **USER-103**: Model-Driven Reasoning (tooltips explaining AI scores).
    *   **USER-104**: Scope Preview Modal (quick-read of deliverables and messages).
*   **[EPIC] Offer Workflow Management**
    *   **USER-105**: List View with AJAX filters (Status, Price range, AI quality).
    *   **USER-106**: One-click **Accept** (triggers contract & notifications).
    *   **USER-107**: **Reject** workflow with automatic worker notification.
    *   **USER-108**: Initiate **Negotiation** (locks offer for term discussion).
    *   **USER-109**: Abort active negotiations.

## 👤 Worker Persona (The Provider)
*   **[EPIC] AI-Assisted Proposal Creation**
    *   **USER-201**: Draft-time **AI Score & Risk level** (LOW/MEDIUM/HIGH).
    *   **USER-202**: Live **Improvement Suggestions** (e.g., "Add more deliverables").
*   **[EPIC] Proposal Tracking & Response**
    *   **USER-203**: Proposal Tracker (Pending, Accepted, Rejected, Negotiating).
    *   **USER-204**: Service Request Discovery (browse open opportunities).
    *   **USER-205**: Accept/Reject client-initiated negotiation terms.

## 🛡️ Admin Persona (The Platform Manager)
*   **[EPIC] Platform Governance**
    *   **USER-301**: Global Offer Oversight (high-performance list for monitoring).
    *   **USER-302**: Bulk Management Actions (Archive, Delete, Multi-Status update).

## ⚙️ System & Infrastructure
*   **[EPIC] Automation & Notifications**
    *   **SYS-001**: Transactional Email Lifecycle (Accepted, Rejected, Negotiated).
    *   **SYS-002**: Automated ML Retraining Pipeline (`run_pipeline.py`).
    *   **SYS-003**: FastAPI Feature Extraction Proxy (shared ML logic between client/worker).
