# AI Matchmaking Module

Binary classification model to rank freelancers for a service request. Trained on `matchmaking.csv` (target: `hired_label`). Used by the Symfony app to get Top 10 freelancers when a client creates a Service Request.

## Install dependencies

```bash
cd ai_matchmaking
pip install -r requirements.txt
```

Or from project root:

```bash
pip install -r ai_matchmaking/requirements.txt
```

## Train the model

From the **project root** (so `matchmaking.csv` is found):

```bash
python -m ai_matchmaking.train_model
```

This will:

- Load `matchmaking.csv`
- Drop leaky columns (`request_title`, `request_description`, `interaction_date`, `interaction_id`, etc.)
- OneHotEncode categoricals (category, level, language, timezone)
- Train XGBoost (or LogisticRegression if xgboost is missing)
- Print ROC-AUC, PR-AUC, confusion matrix
- Save:
  - `ai_matchmaking/models/matchmaking_pipeline.pkl`
  - `ai_matchmaking/models/features.json`

## Run the API

```bash
python -m uvicorn ai_matchmaking.api:app --reload --host 127.0.0.1 --port 8003
```

- **Health:** `GET http://127.0.0.1:8003/health`
- **Rank:** `POST http://127.0.0.1:8003/rank` with JSON body (see API spec in main README or `api.py`).

The Symfony app calls `POST http://127.0.0.1:8003/rank` to get the top 10 freelancers for a new service request.
