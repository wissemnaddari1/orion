"""
FastAPI service for matchmaking: POST /rank returns top_k freelancers by score.
"""
import json
from pathlib import Path

import numpy as np
import pandas as pd
from fastapi import FastAPI
from pydantic import BaseModel

SCRIPT_DIR = Path(__file__).resolve().parent
MODELS_DIR = SCRIPT_DIR / "models"
PIPELINE_PATH = MODELS_DIR / "matchmaking_pipeline.pkl"
FEATURES_PATH = MODELS_DIR / "features.json"

app = FastAPI(title="Matchmaking API", version="1.0.0")

# Lazy load pipeline and feature config
_pipeline = None
_features_config = None


def get_pipeline():
    global _pipeline
    if _pipeline is None:
        import joblib

        if not PIPELINE_PATH.exists():
            raise FileNotFoundError(
                "Model not found. Run: python -m ai_matchmaking.train_model"
            )
        _pipeline = joblib.load(PIPELINE_PATH)
    return _pipeline


def get_features_config():
    global _features_config
    if _features_config is None:
        if not FEATURES_PATH.exists():
            raise FileNotFoundError(
                "features.json not found. Run: python -m ai_matchmaking.train_model"
            )
        with open(FEATURES_PATH) as f:
            _features_config = json.load(f)
    return _features_config


# --- Request/Response models ---


class RequestPayload(BaseModel):
    request_id: int
    request_category: str
    request_budget_usd: float
    request_deadline_days: int
    request_complexity_1to5: int
    request_language: str
    request_timezone: str
    request_required_skills: str  # e.g. "Symfony|PHP|JWT"


class CandidatePayload(BaseModel):
    freelancer_id: int
    freelancer_primary_category: str
    freelancer_level: str
    freelancer_hourly_rate: float
    freelancer_rating_avg: float
    freelancer_total_reviews: int
    freelancer_completed_jobs: int
    freelancer_response_rate: float
    freelancer_language: str
    freelancer_timezone: str
    freelancer_skills: str
    skill_overlap_count: int
    category_match: int
    language_match: int
    timezone_match: int


class RankRequest(BaseModel):
    request: RequestPayload
    candidates: list[CandidatePayload]
    top_k: int = 10


class RankResultItem(BaseModel):
    freelancer_id: int
    score: float


class RankResponse(BaseModel):
    request_id: int
    top_k: int
    results: list[RankResultItem]


def _safe_float(v, default=0.0):
    try:
        return float(v)
    except (TypeError, ValueError):
        return default


def _safe_int(v, default=0):
    try:
        return int(v)
    except (TypeError, ValueError):
        return default


@app.post("/rank", response_model=RankResponse)
def rank(request_body: RankRequest):
    """Rank candidates and return top_k by hire probability score."""
    cfg = get_features_config()
    pipeline = get_pipeline()
    preprocessor = pipeline["preprocessor"]
    model = pipeline["model"]

    req = request_body.request
    top_k = min(request_body.top_k, len(request_body.candidates))
    if top_k <= 0:
        return RankResponse(
            request_id=req.request_id,
            top_k=0,
            results=[],
        )

    # Build one row per candidate (request features repeated + candidate features)
    rows = []
    for c in request_body.candidates:
        # request_skill_count / freelancer_skill_count: derive from skills strings if needed
        req_skills = (req.request_required_skills or "").split("|")
        req_skill_count = len([s for s in req_skills if s.strip()])
        fl_skills = (c.freelancer_skills or "").split("|")
        fl_skill_count = len([s for s in fl_skills if s.strip()])
        if req_skill_count == 0:
            req_skill_count = 1
        if fl_skill_count == 0:
            fl_skill_count = 1

        row = {
            "request_category": str(req.request_category or ""),
            "request_budget_usd": _safe_float(req.request_budget_usd),
            "request_deadline_days": _safe_int(req.request_deadline_days),
            "request_complexity_1to5": _safe_int(req.request_complexity_1to5),
            "request_language": str(req.request_language or ""),
            "request_timezone": str(req.request_timezone or ""),
            "freelancer_primary_category": str(c.freelancer_primary_category or ""),
            "freelancer_level": str(c.freelancer_level or ""),
            "freelancer_hourly_rate": _safe_float(c.freelancer_hourly_rate),
            "freelancer_rating_avg": _safe_float(c.freelancer_rating_avg),
            "freelancer_total_reviews": _safe_int(c.freelancer_total_reviews),
            "freelancer_completed_jobs": _safe_int(c.freelancer_completed_jobs),
            "freelancer_response_rate": _safe_float(c.freelancer_response_rate),
            "freelancer_language": str(c.freelancer_language or ""),
            "freelancer_timezone": str(c.freelancer_timezone or ""),
            "skill_overlap_count": _safe_int(c.skill_overlap_count),
            "request_skill_count": req_skill_count,
            "freelancer_skill_count": fl_skill_count,
            "category_match": _safe_int(c.category_match),
            "language_match": _safe_int(c.language_match),
            "timezone_match": _safe_int(c.timezone_match),
        }
        rows.append((c.freelancer_id, row))

    df = pd.DataFrame([r[1] for r in rows])

    # Align columns with training (categorical fill, numeric fill)
    for col in cfg.get("categorical", []):
        if col in df.columns:
            df[col] = df[col].fillna("__missing__").astype(str)
    for col in cfg.get("numeric", []):
        if col in df.columns:
            df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)

    X = preprocessor.transform(df)
    scores = model.predict_proba(X)[:, 1]

    # Sort by score descending and take top_k
    indexed = list(zip([r[0] for r in rows], scores.tolist()))
    indexed.sort(key=lambda x: -x[1])
    top = indexed[:top_k]

    return RankResponse(
        request_id=req.request_id,
        top_k=len(top),
        results=[RankResultItem(freelancer_id=fid, score=round(s, 4)) for fid, s in top],
    )


@app.get("/health")
def health():
    return {"status": "ok"}
