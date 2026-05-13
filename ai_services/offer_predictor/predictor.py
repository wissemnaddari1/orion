import os
import pickle
import pandas as pd
from feature_engineering import OfferFeatureExtractor

class OfferPredictor:
    def __init__(self):
        self.model = None
        self.feature_cols = None
        self.version = "unknown"
        self._load_model()
        
    def _load_model(self):
        try:
            model_dir = os.path.join(os.path.dirname(__file__), 'model')
            model_path = os.path.join(model_dir, 'offer_model.pkl')
            cols_path = os.path.join(model_dir, 'feature_columns.pkl')
            version_path = os.path.join(model_dir, 'version.txt')
            
            if os.path.exists(model_path):
                with open(model_path, 'rb') as f:
                    self.model = pickle.load(f)
                    
            if os.path.exists(cols_path):
                with open(cols_path, 'rb') as f:
                    self.feature_cols = pickle.load(f)
                    
            if os.path.exists(version_path):
                with open(version_path, 'r') as f:
                    self.version = f.read().strip()
            
            print(f"Loaded offer model version: {self.version}")
            
        except Exception as e:
            print(f"Error loading model: {e}")

    def predict(self, offer_data, request_data, worker_data):
        if not self.model or not self.feature_cols:
            return {"error": "Model not loaded"}
            
        # Extract features
        features = OfferFeatureExtractor.compute_features(
            offer_data, request_data, worker_data
        )
        
        if not features:
            return {"error": "Feature extraction failed"}
            
        # Convert to DataFrame
        df = pd.DataFrame([features])
        
        # Ensure column order matches training
        X = df[self.feature_cols].fillna(0)
        
        # Predict
        try:
            prediction = self.model.predict(X)[0]
            probability = self.model.predict_proba(X)[0][1] # Prob of class 1 (Accepted)
            
            reasons = self.generate_reasons(features)
            
            return {
                "likely_accepted": int(prediction),
                "probability": round(float(probability), 4),
                "reasons": reasons,
                "model_version": self.version
            }
        except Exception as e:
            return {"error": str(e)}

    def generate_reasons(self, f):
        """
        Dynamically generates reasons based on model feature importances
        and the specific values for this offer.
        """
        reasons = []
        
        # Get feature importances from the model
        if hasattr(self.model, 'feature_importances_'):
            importances = dict(zip(self.feature_cols, self.model.feature_importances_))
        else:
            # Fallback to neutral weights if model doesn't support importances
            importances = {col: 1.0 for col in self.feature_cols}
        
        # 1. Price vs Budget
        p_weight = importances.get('price_ratio', 0) + importances.get('budget_position', 0)
        p_ratio = f.get('price_ratio', 1.0)
        if p_ratio <= 0.85:
            reasons.append({"type": "success", "text": "Highly competitive pricing", "weight": p_weight})
        elif p_ratio <= 1.0:
            reasons.append({"type": "success", "text": "Price within budget", "weight": p_weight})
        elif p_ratio <= 1.15:
            reasons.append({"type": "info", "text": "Price slightly over budget", "weight": p_weight})
        else:
            reasons.append({"type": "warning", "text": "Premium price point (High Risk)", "weight": p_weight})

        # 2. Worker Reputation
        w_weight = importances.get('worker_rating_avg', 0) + importances.get('total_reviews', 0)
        rating = f.get('worker_rating_avg', 0)
        if rating >= 4.5:
            reasons.append({"type": "success", "text": "Elite professional rating", "weight": w_weight})
        elif rating >= 3.5:
            reasons.append({"type": "info", "text": "Established performance history", "weight": w_weight})
        else:
            reasons.append({"type": "warning", "text": "Below average rating history", "weight": w_weight})

        # 3. Timeline & Urgency
        t_weight = importances.get('timeline_ratio', 0) + importances.get('is_urgent', 0)
        t_ratio = f.get('timeline_ratio', 1.0)
        if t_ratio <= 0.7:
            reasons.append({"type": "success", "text": "Accelerated delivery timeline", "weight": t_weight})
        elif t_ratio > 1.2:
            reasons.append({"type": "warning", "text": "Extended delivery estimate", "weight": t_weight})
        
        if f.get('is_urgent', 0):
            reasons.append({"type": "info", "text": "Express delivery priority", "weight": t_weight})

        # 4. Message & Deliverables
        d_weight = importances.get('message_length', 0) + importances.get('has_deliverables', 0)
        if f.get('has_deliverables', 0):
            reasons.append({"type": "success", "text": "Very clear project deliverables", "weight": d_weight})
        
        msg_len = f.get('message_length', 0)
        if msg_len > 300:
            reasons.append({"type": "success", "text": "Highly detailed proposal", "weight": d_weight})
        elif msg_len < 100:
            reasons.append({"type": "warning", "text": "Brief proposal (Needs more detail)", "weight": d_weight})

        # Sort reasons by their model importance weight
        reasons.sort(key=lambda x: x.get('weight', 0), reverse=True)
        
        # Return top 4 but strip the weight internal field for the API
        return [{"type": r["type"], "text": r["text"]} for r in reasons[:4]]
