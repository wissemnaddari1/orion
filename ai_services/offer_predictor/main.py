from fastapi import FastAPI, HTTPException
import os

from pydantic import BaseModel
from typing import Optional
from predictor import OfferPredictor

app = FastAPI(title="Orion Offer Predictor Service")

# Initialize model loader
predictor = OfferPredictor()

from enhancement_engine import OfferEnhancementEngine
import pandas as pd
from db_config import get_sqlalchemy_engine

class OfferInput(BaseModel):
    price: float
    message: Optional[str] = ""
    estimated_time_days: float
    included_revisions: int = 0
    deliverables: Optional[str] = ""

class RequestInput(BaseModel):
    budget_min: float
    budget_max: float
    duration: float

class WorkerInput(BaseModel):
    rating_avg: float
    total_reviews: int

class PredictionRequest(BaseModel):
    offer: OfferInput
    service_request: RequestInput
    worker: WorkerInput

class BatchPredictionRequest(BaseModel):
    requests: list[PredictionRequest]

class FeatureInput(BaseModel):
    price_ratio: float
    budget_position: float
    message_length: int
    deliverables_length: int
    has_deliverables: int
    timeline_ratio: float
    included_revisions: int
    worker_rating_avg: float
    total_reviews: int

class EnhancementResponse(BaseModel):
    score: float
    acceptance_probability: float
    suggestions: list[str]
    risk_level: str


class NLPAnalyzeRequest(BaseModel):
    """Java/Spring Orion stack: POST /nlp/analyze-message on the offer service host."""

    message: Optional[str] = None
    text: Optional[str] = None


@app.get("/")
def read_root():
    return {
        "status": "ok",
        "service": "Offer Predictor",
        "version": predictor.version,
        "routes": {
            "predict": "POST /predict-offer",
            "predict_batch": "POST /predict-offers",
            "enhance": "POST /enhance-offer",
            "nlp": "POST /nlp/analyze-message",
            "analytics": "GET /analytics/ai-impact",
            "health": "GET /health",
        },
    }


@app.get("/health")
def health():
    """Spring / Symfony / probes — same contract as other Orion AI services."""
    return {"status": "ok", "service": "offer-predictor", "version": predictor.version}


@app.post("/predict-offer")
def predict_offer(payload: PredictionRequest):
    result = predictor.predict(
        payload.offer.model_dump(),
        payload.service_request.model_dump(),
        payload.worker.model_dump()
    )
    
    if "error" in result:
        raise HTTPException(status_code=500, detail=result["error"])
        
    return result

@app.post("/predict-offers")
def predict_offers(payload: BatchPredictionRequest):
    results = []
    for req in payload.requests:
        res = predictor.predict(
            req.offer.model_dump(),
            req.service_request.model_dump(),
            req.worker.model_dump()
        )
        results.append(res)
    return results

@app.post("/offer-enhancement", response_model=EnhancementResponse)
def offer_enhancement(features: FeatureInput):
    # Reuse predictor logic for probability and reasoning
    try:
        f_dict = features.dict()
        df = pd.DataFrame([f_dict])
        
        # Ensure column order matches training
        X = df[predictor.feature_cols].fillna(0)
        
        # 1. Probability
        probability = predictor.model.predict_proba(X)[0][1]
        
        # 2. Reasoning (using the now public method)
        reasons = predictor.generate_reasons(f_dict)
        
        # 3. Enhancement Suggestions
        suggestions = OfferEnhancementEngine.generate_suggestions(f_dict, reasons)
        risk_level = OfferEnhancementEngine.determine_risk_level(probability)
        
        return EnhancementResponse(
            score=round(probability * 100, 2),
            acceptance_probability=round(float(probability), 4),
            suggestions=suggestions,
            risk_level=risk_level
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Enhancement error: {str(e)}")

@app.post("/enhance-offer", response_model=EnhancementResponse)
def enhance_offer(payload: PredictionRequest):
    """
    Takes RAW offer data (same as /predict-offer), computes features,
    and returns enhancement suggestions.
    """
    try:
        from feature_engineering import OfferFeatureExtractor
        
        # 1. Compute features from raw data
        f_dict = OfferFeatureExtractor.compute_features(
            payload.offer.model_dump(),
            payload.service_request.model_dump(),
            payload.worker.model_dump()
        )
        
        if not f_dict:
            raise HTTPException(status_code=400, detail="Feature extraction failed")
            
        # 2. Probability
        df = pd.DataFrame([f_dict])
        X = df[predictor.feature_cols].fillna(0)
        probability = predictor.model.predict_proba(X)[0][1]
        
        # 3. Reasoning
        reasons = predictor.generate_reasons(f_dict)
        
        # 4. Enhancement Suggestions
        suggestions = OfferEnhancementEngine.generate_suggestions(f_dict, reasons)
        risk_level = OfferEnhancementEngine.determine_risk_level(probability)
        
        return EnhancementResponse(
            score=round(probability * 100, 2),
            acceptance_probability=round(float(probability), 4),
            suggestions=suggestions,
            risk_level=risk_level
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Enhancement error: {str(e)}")


@app.post("/nlp/analyze-message")
def nlp_analyze_message(payload: NLPAnalyzeRequest):
    """
    Lightweight message analysis for Orion (Java + Symfony share this process).
    Extend with a real NLP model later; response shape is stable for clients.
    """
    raw = (payload.message or payload.text or "").strip()
    words = raw.split()
    wc = len(words)
    cl = len(raw)

    risk_kw = [
        "security", "authentication", "gdpr", "compliance", "payment",
        "encryption", "password", "breach", "urgent", "deadline",
    ]
    lower = raw.lower()
    hits = [k for k in risk_kw if k in lower]

    # Simple heuristic sentiment hint in [-1, 1]
    pos = sum(1 for w in ("great", "thanks", "please", "love", "excellent") if w in lower)
    neg = sum(1 for w in ("angry", "refund", "disappointed", "terrible", "scam", "late") if w in lower)
    sentiment_hint = max(-1.0, min(1.0, (pos - neg) * 0.25))

    return {
        "message_length": cl,
        "word_count": wc,
        "risk_keywords_found": hits,
        "risk_keyword_count": len(hits),
        "sentiment_hint": round(sentiment_hint, 3),
        "language_hint": "unknown",
        "topics": [],
    }


@app.get("/analytics/ai-impact")
def ai_impact_analytics():
    """
    Computes the historical acceptance rate grouped by AI score ranges:
    0-40, 40-60, 60-80, 80-100
    Uses the ml_offer_training table which has ground truth `is_accepted`.
    """
    try:
        engine = get_sqlalchemy_engine()
        # Fetch the historical features and the actual outcome
        df = pd.read_sql("SELECT * FROM ml_offer_training", engine)
        
        if df.empty:
            return []

        # Predict probabilities for the entire historical dataset in bulk
        X = df[predictor.feature_cols].fillna(0)
        probabilities = predictor.model.predict_proba(X)[:, 1]
        
        # Add probability as a percentage column (0 to 100)
        df['ai_score'] = probabilities * 100

        # Define bins and labels as requested by the user
        bins = [0, 40, 60, 80, 100]
        labels = ["0-40", "40-60", "60-80", "80-100"]

        # Cut into bins, inclusive of right edge
        df['score_range'] = pd.cut(df['ai_score'], bins=bins, labels=labels, include_lowest=True, right=True)

        # Calculate acceptance rate per group
        # is_accepted is 1 or 0, so mean() is the fraction accepted
        grouped = df.groupby('score_range', observed=False)['is_accepted'].mean() * 100
        
        # Fill missing ranges with 0
        grouped = grouped.fillna(0)

        results = []
        for range_label in labels:
            rate = grouped.get(range_label, 0)
            results.append({
                "range": range_label,
                "rate": round(float(rate), 2)
            })

        return results

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Analytics error: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("PORT", 8002))
    uvicorn.run(app, host="0.0.0.0", port=port)

