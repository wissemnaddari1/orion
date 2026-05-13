# Orion AI: Offer Intelligence Engines

Orion utilizes a unified AI core to provide intelligence for both sides of the marketplace. While both engines share the same underlying Machine Learning model, they serve different user roles and objectives.

## 1. Client Offer Analyzer
**Role:** Evaluator (Post-Submission)  
**Endpoint:** `/predict-offer`  

The **Client Offer Analyzer** evaluates offers that have already been submitted. It helps clients quickly identify the most promising offers by predicting the likelihood of acceptance based on historic success patterns.

- **Primary Output:** 0-100% Acceptance Probability & Model-Driven Reasoning.
- **Improved Metrics:** The v1.1-optimized model achieves **84% accuracy** on the test set.

## 2. Offer Enhancement Engine
**Role:** Assistant (Pre-Submission)  
**Endpoint:** `/offer-enhancement`  

The **Offer Enhancement Engine** acts as a live mentor for workers while they are drafting an offer. It uses the same predictive intelligence to provide real-time feedback and actionable suggestions to improve the offer's quality before final submission.

- **Primary Output:** Actionable Suggestions & Risk Level Assessment.
- **Consistency:** Uses the same mathematical weights as the Client Analyzer, ensuring alignment.

---

## Technical Architecture (v1.1-Optimized)
Both engines share the following resources in the `ai_services/offer_predictor` microservice:

- **Model:** `model/offer_model.pkl` (Optimized RandomForest)
- **Features:** `feature_engineering.py` (Now includes `is_urgent`, `priority`, `deliverables`, and `category`).
- **Intelligence:** `predictor.py` (Feature-importance based reasoning engine).

### Data Quality Scaling
The current model is trained on **2,305 high-quality marketplace records** with realistic categorical distribution and human variance simulation.
