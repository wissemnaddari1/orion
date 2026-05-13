from dataclasses import dataclass
import re

from .model_loader import get_model
from .models import ContractGenerationRequest


LOW_MAX = 0.33
MEDIUM_MAX = 0.66
PRICE_SCALE = 1000.0
PRICE_PER_DAY_SCALE = 20.0


@dataclass(frozen=True)
class RiskResult:
    risk_score: float
    risk_level: str


def _word_count(value: str) -> int:
    words = [part for part in re.split(r"\s+", value.strip()) if part]
    return len(words)


def _prepare_features(payload: ContractGenerationRequest) -> dict[str, float | int | str]:
    delivery_days = max(int(payload.deliveryDays), 1)
    price = float(payload.price)

    return {
        # Normalize monetary features to keep inference scale aligned with training.
        "price": price / PRICE_SCALE,
        "deliveryDays": delivery_days,
        "deliveryMode": payload.deliveryMode.value,
        "deliveryModeOnline": 1 if payload.deliveryMode.value == "ONLINE" else 0,
        "clientRating": float(payload.clientRating),
        "freelancerRating": float(payload.freelancerRating),
        "negotiationCount": int(payload.negotiationCount),
        "numberOfMilestones": int(payload.numberOfMilestones),
        # Use word counts instead of raw character lengths to avoid oversized penalties.
        "titleLength": _word_count(payload.serviceTitle),
        "descriptionLength": _word_count(payload.serviceDescription),
        "requirementsLength": _word_count(payload.requirements),
        "ratingGap": abs(float(payload.clientRating) - float(payload.freelancerRating)),
        "pricePerDay": (price / delivery_days) / PRICE_PER_DAY_SCALE,
    }


def _score_from_model(model, features: dict[str, float | int | str]) -> float:
    # Preferred path: align to model-declared feature order (works for most sklearn models/pipelines).
    if hasattr(model, "feature_names_in_"):
        ordered_names = list(model.feature_names_in_)
        matrix = [[features.get(name, 0) for name in ordered_names]]
        if hasattr(model, "predict_proba"):
            probabilities = model.predict_proba(matrix)
            score = float(probabilities[0][1])
            return max(0.0, min(1.0, score))
        prediction = float(model.predict(matrix)[0])
        return max(0.0, min(1.0, prediction))

    # Common path for models that can consume mapping rows.
    row = [features]
    if hasattr(model, "predict_proba"):
        try:
            probabilities = model.predict_proba(row)
            score = float(probabilities[0][1])
            return max(0.0, min(1.0, score))
        except Exception:
            pass

    try:
        prediction = float(model.predict(row)[0])
    except Exception as exc:
        raise RuntimeError(f"Unable to score model with current features: {exc}") from exc

    if prediction > 1.0:
        return 1.0
    if prediction < 0.0:
        return 0.0
    return prediction


def _map_risk_level(score: float) -> str:
    if score <= LOW_MAX:
        return "LOW"
    if score <= MEDIUM_MAX:
        return "MEDIUM"
    return "HIGH"


def calculate_contract_risk(payload: ContractGenerationRequest) -> RiskResult:
    model = get_model()
    features = _prepare_features(payload)
    score = _score_from_model(model, features)
    risk_level = _map_risk_level(score)

    return RiskResult(
        risk_score=round(score, 4),
        risk_level=risk_level,
    )
