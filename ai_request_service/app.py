"""
Flask API — Service Requirement Engine
=======================================
Architecture: 6 per-category RandomForest models
Endpoint:     POST /ai-predict
Input:        { category, budget_max, duration }
Output:       { category, tier, bpd, titles, requirements }
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import numpy as np
import json
import re
import os

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}) # Force allow all origins

# ── Config ──────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
META_PATH = os.path.join(BASE_DIR, "model_meta.json")

# ── Load meta ────────────────────────────────────────────────────────────────
with open(META_PATH, "r") as f:
    META = json.load(f)

Q25        = META["q25"]
Q50        = META["q50"]
Q75        = META["q75"]
FEATURES   = META["features"]
CATEGORIES = META["categories"]

# ── Load all 6 models at startup ─────────────────────────────────────────────
def safe_filename(cat):
    return re.sub(r'[^a-z0-9]+', '_', cat.lower()).strip('_')

MODELS   = {}
ENCODERS = {}

for cat in CATEGORIES:
    safe = safe_filename(cat)
    MODELS[cat]   = joblib.load(os.path.join(BASE_DIR, f"model_{safe}.pkl"))
    ENCODERS[cat] = joblib.load(os.path.join(BASE_DIR, f"encoder_{safe}.pkl"))

print(f"✅ Loaded {len(MODELS)} models: {list(MODELS.keys())}")


# ── Helpers ──────────────────────────────────────────────────────────────────
def get_tier(bpd):
    if bpd > Q75:   return 3, "ELITE"
    elif bpd > Q50: return 2, "HIGH"
    elif bpd > Q25: return 1, "MEDIUM"
    else:           return 0, "LOW"

def get_duration_bucket(d):
    if d <= 30:   return 0
    elif d <= 60: return 1
    elif d <= 90: return 2
    else:         return 3

HIGH_IMPORTANCE_KEYWORDS = [
    "security", "authentication", "gdpr", "compliance",
    "audit", "payment", "permissions", "roles", "ssl",
    "encryption", "backup", "disaster", "sla", "logging",
    "secrets", "tls", "biometric"
]

def boost_high_importance(proba_row, tier_int, title_list, boost=0.08):
    if tier_int < 2:
        return proba_row
    boosted = proba_row.copy()
    for i, title in enumerate(title_list):
        if any(kw in title.lower() for kw in HIGH_IMPORTANCE_KEYWORDS):
            boosted[i] = min(1.0, boosted[i] + boost)
    total = boosted.sum()
    return boosted / total if total > 0 else boosted


def recommend_titles(category, budget_max, duration, top_k=15):
    """Core prediction function used by the /ai-predict endpoint."""
    bpd            = budget_max / duration
    tier_int, tier = get_tier(bpd)
    blog           = np.log1p(budget_max)
    dbuck          = get_duration_bucket(duration)

    rf  = MODELS[category]
    le  = ENCODERS[category]

    X_in  = np.array([[tier_int, blog, dbuck, bpd, budget_max, duration]])
    proba = rf.predict_proba(X_in)[0]

    model_titles = le.inverse_transform(rf.classes_)
    proba        = boost_high_importance(proba, tier_int, model_titles)

    top_idx    = np.argsort(proba)[::-1][:top_k]
    top_titles = model_titles[top_idx].tolist()
    top_scores = proba[top_idx].tolist()

    return {
        "category"  : category,
        "budget_max": budget_max,
        "duration"  : duration,
        "bpd"       : round(bpd, 2),
        "tier"      : tier,
        "titles"    : top_titles,
        "scores"    : [round(s, 4) for s in top_scores],
    }


# ── Routes ───────────────────────────────────────────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "models_loaded": len(MODELS)})


@app.route("/ai-predict", methods=["POST"])
def predict():
    try:
        data = request.get_json(force=True)

        # ── Validate inputs ──────────────────────────────────────────────────
        category = data.get("category", "").strip()
        budget_max = data.get("budget_max")
        duration   = data.get("duration")

        if not category:
            return jsonify({"error": "Missing field: category"}), 400
        if budget_max is None:
            return jsonify({"error": "Missing field: budget_max"}), 400
        if duration is None:
            return jsonify({"error": "Missing field: duration"}), 400
        if category not in CATEGORIES:
            return jsonify({
                "error": f"Unknown category '{category}'",
                "valid_categories": CATEGORIES
            }), 400

        budget_max = float(budget_max)
        duration   = int(duration)

        if budget_max <= 0 or duration <= 0:
            return jsonify({"error": "budget_max and duration must be positive"}), 400

        # ── Predict ──────────────────────────────────────────────────────────
        top_k  = int(data.get("top_k", 15))
        result = recommend_titles(category, budget_max, duration, top_k=top_k)

        matched_cat = next((c for c in CATEGORIES if c.lower() == category.lower()), None)

        if not matched_cat:
            return jsonify({
                "error": f"Unknown category '{category}'",
                "valid_categories": CATEGORIES
            }), 400
        category = matched_cat
        # ── Format for Symfony ───────────────────────────────────────────────
        # Symfony controller reads: result["titles"] for the WHERE IN query
        # and result["tier"] for priority mapping
        return jsonify({
            "status"    : "success",
            "category"  : result["category"],
            "tier"      : result["tier"],
            "bpd"       : result["bpd"],
            "titles"    : result["titles"],   # ← clean array for SQL WHERE IN
            "scores"    : result["scores"],   # ← confidence scores (optional)
        })

    except Exception as e:
        import traceback
        print(traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route("/categories", methods=["GET"])
def get_categories():
    """Returns all valid categories — useful for Symfony dropdown."""
    return jsonify({"categories": CATEGORIES})


if __name__ == "__main__":
    # Default 5010 avoids clash with face service on 5000 (Orion stack). Override with PORT=5000 if needed.
    port = int(os.environ.get("PORT", "5010"))
    app.run(host="127.0.0.1", port=port, debug=True)