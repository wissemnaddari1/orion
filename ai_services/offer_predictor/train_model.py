import pandas as pd
import pickle
import os
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.metrics import accuracy_score, classification_report, f1_score
from db_config import get_sqlalchemy_engine

def train():
    try:
        engine = get_sqlalchemy_engine()
        query = "SELECT * FROM ml_offer_training"
        df = pd.read_sql(query, engine)
    except Exception as e:
        print(f"DB Connection failed: {e}")
        return
    
    if df.empty:
        print("No training data available.")
        return

    # Expanded Features
    feature_cols = [
        'price_ratio', 'budget_position', 'message_length', 
        'deliverables_length', 'has_deliverables', 'timeline_ratio',
        'included_revisions', 'worker_rating_avg', 'total_reviews',
        'category_id', 'is_urgent', 'priority_level'
    ]
    
    X = df[feature_cols].fillna(0)
    y = df['is_accepted']
    
    # Train/Test Split
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    
    # Hyperparameter Optimization
    param_grid = {
        'n_estimators': [100, 200],
        'max_depth': [None, 10, 20],
        'min_samples_split': [2, 5, 10],
        'class_weight': ['balanced']
    }
    
    print("Training Optimized RandomForest Classifier via GridSearchCV...")
    rf = RandomForestClassifier(random_state=42)
    grid_search = GridSearchCV(rf, param_grid, cv=5, scoring='f1', n_jobs=-1)
    grid_search.fit(X_train, y_train)
    
    model = grid_search.best_estimator_
    print(f"Best parameters: {grid_search.best_params_}")
    
    # Evaluate
    y_pred = model.predict(X_test)
    print("\nModel Evaluation:")
    print("Accuracy:", accuracy_score(y_test, y_pred))
    print("F1-Score:", f1_score(y_test, y_pred))
    print("\nClassification Report:")
    print(classification_report(y_test, y_pred))
    
    # Save artifacts
    model_dir = os.path.join(os.path.dirname(__file__), 'model')
    os.makedirs(model_dir, exist_ok=True)
    
    with open(os.path.join(model_dir, 'offer_model.pkl'), 'wb') as f:
        pickle.dump(model, f)
        
    with open(os.path.join(model_dir, 'feature_columns.pkl'), 'wb') as f:
        pickle.dump(feature_cols, f)
        
    with open(os.path.join(model_dir, 'version.txt'), 'w') as f:
        f.write("v1.1-optimized")

    print(f"Model saved to {model_dir}")

if __name__ == "__main__":
    train()
