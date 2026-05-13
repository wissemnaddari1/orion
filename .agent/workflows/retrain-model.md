---
description: How to retrain the ML model and update the AI service
---

This workflow automates the process of generating new data, syncronizing the training set, and retraining the ML model.

1. Run the master pipeline script to refresh data and retrain:
// turbo
```powershell
python ai_services/offer_predictor/run_pipeline.py
```

2. (Manual) Restart the FastAPI service to load the new model:
```powershell
# Stop the current service (usually CTRL+C) and run:
python ai_services/offer_predictor/main.py
```

> [!NOTE]
> The `run_pipeline.py` script automatically executes `seed_marketplace.py`, `generate_ml_data.py`, and `train_model.py` in sequence.
