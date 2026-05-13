from pathlib import Path

import joblib

SCRIPT_DIR = Path(__file__).resolve().parent
MODEL_PATH = SCRIPT_DIR / "contract_risk_model.pkl"

_model = None


def get_model():
    global _model

    if _model is None:
        if not MODEL_PATH.exists():
            raise FileNotFoundError(
                f"Model not found at {MODEL_PATH}. Place contract_risk_model.pkl in ai_contract_generator/."
            )
        _model = joblib.load(MODEL_PATH)

    return _model
