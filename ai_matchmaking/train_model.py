"""
Train binary classification model for matchmaking (hired_label 0/1).
Saves pipeline and feature list for inference.
"""
import json
import os
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.metrics import (
    confusion_matrix,
    precision_recall_curve,
    roc_auc_score,
    average_precision_score,
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import OneHotEncoder

# Try XGBoost first, fallback to LogisticRegression
try:
    from xgboost import XGBClassifier

    MODEL_CLASS = XGBClassifier
    MODEL_KWARGS = {"random_state": 42}
except ImportError:
    from sklearn.linear_model import LogisticRegression

    MODEL_CLASS = LogisticRegression
    MODEL_KWARGS = {"random_state": 42, "max_iter": 1000}

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
CSV_PATH = PROJECT_ROOT / "matchmaking.csv"
MODELS_DIR = SCRIPT_DIR / "models"
PIPELINE_PATH = MODELS_DIR / "matchmaking_pipeline.pkl"
FEATURES_PATH = MODELS_DIR / "features.json"

# Columns to drop (leak or useless for inference)
DROP_COLUMNS = [
    "interaction_id",
    "interaction_date",
    "request_id",
    "request_title",
    "request_description",
    "hired_probability_simulated",
]

TARGET = "hired_label"

CATEGORICAL_COLUMNS = [
    "request_category",
    "request_language",
    "request_timezone",
    "freelancer_primary_category",
    "freelancer_level",
    "freelancer_language",
    "freelancer_timezone",
]

NUMERIC_COLUMNS = [
    "request_budget_usd",
    "request_deadline_days",
    "request_complexity_1to5",
    "freelancer_hourly_rate",
    "freelancer_rating_avg",
    "freelancer_total_reviews",
    "freelancer_completed_jobs",
    "freelancer_response_rate",
    "skill_overlap_count",
    "request_skill_count",
    "freelancer_skill_count",
    "category_match",
    "language_match",
    "timezone_match",
]


def main():
    MODELS_DIR.mkdir(parents=True, exist_ok=True)

    print("Loading CSV...")
    df = pd.read_csv(CSV_PATH)

    # Drop leaky/unused columns
    to_drop = [c for c in DROP_COLUMNS if c in df.columns]
    df = df.drop(columns=to_drop, errors="ignore")

    if TARGET not in df.columns:
        raise ValueError(f"Target column '{TARGET}' not found.")

    # Build feature set: only columns we need
    feature_cols = [c for c in CATEGORICAL_COLUMNS + NUMERIC_COLUMNS if c in df.columns]
    missing = set(CATEGORICAL_COLUMNS + NUMERIC_COLUMNS) - set(feature_cols)
    if missing:
        print("Warning: missing columns (will fill or skip):", missing)

    X = df[feature_cols].copy()
    y = df[TARGET].astype(int)

    # Fill NaN for categorical (OneHotEncoder needs string)
    for c in CATEGORICAL_COLUMNS:
        if c in X.columns:
            X[c] = X[c].fillna("__missing__").astype(str)
    for c in NUMERIC_COLUMNS:
        if c in X.columns:
            X[c] = pd.to_numeric(X[c], errors="coerce").fillna(0)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    cat_cols = [c for c in CATEGORICAL_COLUMNS if c in X.columns]
    num_cols = [c for c in NUMERIC_COLUMNS if c in X.columns]

    preprocessor = ColumnTransformer(
        [
            (
                "cat",
                OneHotEncoder(handle_unknown="ignore", sparse_output=False),
                cat_cols,
            ),
            ("num", "passthrough", num_cols),
        ],
        remainder="drop",
    )

    X_train_enc = preprocessor.fit_transform(X_train)
    X_test_enc = preprocessor.transform(X_test)

    # Feature names after transform (for consistency at inference)
    cat_names = preprocessor.named_transformers_["cat"].get_feature_names_out(cat_cols)
    all_feature_names = list(cat_names) + num_cols

    model = MODEL_CLASS(**MODEL_KWARGS)
    model.fit(X_train_enc, y_train)

    # Evaluate
    y_pred_proba = model.predict_proba(X_test_enc)[:, 1]
    y_pred = (y_pred_proba >= 0.5).astype(int)

    roc_auc = roc_auc_score(y_test, y_pred_proba)
    pr_auc = average_precision_score(y_test, y_pred_proba)
    cm = confusion_matrix(y_test, y_pred)

    print("ROC-AUC:", round(roc_auc, 4))
    print("PR-AUC:", round(pr_auc, 4))
    print("Confusion matrix:\n", cm)

    # Save pipeline (preprocessor + model) and feature list
    import joblib

    pipeline = {"preprocessor": preprocessor, "model": model}
    joblib.dump(pipeline, PIPELINE_PATH)

    features_config = {
        "categorical": cat_cols,
        "numeric": num_cols,
        "feature_order": all_feature_names,
    }
    with open(FEATURES_PATH, "w") as f:
        json.dump(features_config, f, indent=2)

    print("Saved:", PIPELINE_PATH, FEATURES_PATH)


if __name__ == "__main__":
    main()
