import pandas as pd
import numpy as np

class OfferFeatureExtractor:
    """
    Centralized logic for extracting ML features from raw entity data.
    Used by:
    1. Synthetic data generator (to prepare training data)
    2. Training pipeline (to process raw data if needed)
    3. Prediction endpoint (to process incoming requests)
    """

    @staticmethod
    def compute_features(offer, request, worker):
        """
        Compute features for a single record (dict or object).
        
        Args:
            offer: dict containing price, message, deliverables, included_revisions, estimated_time_days
            request: dict containing budget_min, budget_max, duration
            worker: dict containing rating_avg, total_reviews
            
        Returns:
            dict: Feature vector
        """
        try:
            price = float(offer.get('price', 0))
            budget_max = float(request.get('budget_max', 0))
            budget_min = float(request.get('budget_min', 0))
            duration = float(request.get('duration', 1)) # Avoid div by zero
            estimated_time = float(offer.get('estimated_time_days', 0))
            
            # 1. Price Ratio: offer_price / budget_max
            price_ratio = price / budget_max if budget_max > 0 else 1.0
            
            # 2. Budget Position: (price - min) / (max - min)
            # 0 = at min budget, 1 = at max budget, >1 = over budget, <0 = under budget
            budget_range = budget_max - budget_min
            budget_position = (price - budget_min) / budget_range if budget_range > 0 else 0.5
            
            # 3. Message Length
            message = offer.get('message', '') or ''
            message_length = len(message)
            
            # 4. Deliverables Stats
            deliverables = offer.get('deliverables', '') or ''
            deliverables_length = len(deliverables)
            has_deliverables = 1 if deliverables_length > 0 else 0
            
            # 5. Timeline Ratio: estimated / requested duration
            timeline_ratio = estimated_time / duration if duration > 0 else 1.0
            
            # 6. Revisions
            included_revisions = int(offer.get('included_revisions', 0))
            
            # 7. Worker Stats
            t_rating = worker.get('rating_avg', 0)
            worker_rating_avg = float(t_rating) if t_rating is not None else 0.0
            
            t_reviews = worker.get('total_reviews', 0)
            total_reviews = int(t_reviews) if t_reviews is not None else 0

            # 8. Categorical & Contextual Features
            category_id = int(request.get('category_id', 0))
            is_urgent = int(offer.get('is_urgent', 0))
            
            # Map priority level to numeric (LOW=1, MEDIUM=2, HIGH=3)
            p_map = {'LOW': 1, 'MEDIUM': 2, 'HIGH': 3}
            priority_val = p_map.get(str(offer.get('priority_level', 'MEDIUM')).upper(), 2)

            return {
                'price_ratio': round(price_ratio, 4),
                'budget_position': round(budget_position, 4),
                'message_length': message_length,
                'deliverables_length': deliverables_length,
                'has_deliverables': has_deliverables,
                'timeline_ratio': round(timeline_ratio, 4),
                'included_revisions': included_revisions,
                'worker_rating_avg': round(worker_rating_avg, 2),
                'total_reviews': total_reviews,
                'category_id': category_id,
                'is_urgent': is_urgent,
                'priority_level': priority_val
            }
        except Exception as e:
            print(f"Error extracting features: {e}")
            return None

    @staticmethod
    def prepare_dataframe(df):
        """
        Prepares a pandas DataFrame for training/prediction.
        Expects processed columns from compute_features to be present
        or computes them if raw columns exist.
        """
        # For now, assume df already has the feature columns from SQL or previous processing
        # Validation or scaling could happen here
        return df
