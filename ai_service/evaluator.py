import os
import json
import time
from dotenv import load_dotenv
from cv_parser import CvParser

# Load environment variables
load_dotenv()

def evaluate_performance(dataset_dir="dataset"):
    """
    Evaluate current CvParser accuracy against collected dataset.
    """
    parser = CvParser()
    items_file = os.path.join(dataset_dir, "items.jsonl")
    
    if not os.path.exists(items_file):
        print(f"Error: {items_file} not found.")
        return

    results = []
    
    with open(items_file, 'r', encoding='utf-8') as f:
        for line in f:
            if not line.strip():
                continue
            
            entry = json.loads(line)
            item_id = entry['id']
            cv_path = os.path.join(dataset_dir, entry['cv_file'])
            json_path = os.path.join(dataset_dir, entry['json_file'])
            
            if not os.path.exists(cv_path) or not os.path.exists(json_path):
                continue
            
            print(f"Evaluating {item_id}...")
            
            # 1. Get ground truth
            with open(json_path, 'r', encoding='utf-8') as jf:
                ground_truth = json.load(jf)
            
            # 2. Run current parser
            start_time = time.time()
            prediction = parser.parse(cv_path, entry.get('file_type', 'pdf'))
            latency = time.time() - start_time
            
            # 3. Compare fields
            comparison = {
                "id": item_id,
                "latency": latency,
                "confidence_diff": prediction.get('confidence', 0) - ground_truth.get('confidence', 0),
                "field_matches": {}
            }
            
            fields = ['title', 'bio', 'location', 'hourly_rate', 'experience_years']
            for field in fields:
                pred_val = str(prediction.get(field, '')).strip().lower()
                gt_val = str(ground_truth.get(field, '')).strip().lower()
                comparison["field_matches"][field] = (pred_val == gt_val)
            
            results.append(comparison)
            
    # Calculate aggregate stats
    total = len(results)
    if total == 0:
        print("No items to evaluate.")
        return
        
    avg_latency = sum(r['latency'] for r in results) / total
    field_acc = {}
    for field in ['title', 'bio', 'location', 'hourly_rate', 'experience_years']:
        matches = sum(1 for r in results if r['field_matches'][field])
        field_acc[field] = (matches / total) * 100
        
    print("\n--- EVALUATION REPORT ---")
    print(f"Total Items: {total}")
    print(f"Avg Latency: {avg_latency:.2f}s")
    print("\nField Accuracy:")
    for field, acc in field_acc.items():
        print(f"  {field:16}: {acc:6.2f}%")
    print("-------------------------\n")

if __name__ == "__main__":
    evaluate_performance(os.path.join(os.path.dirname(__file__), "dataset"))
