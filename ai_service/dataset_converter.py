import os
import json
from dotenv import load_dotenv
from cv_parser import CvParser

# Load environment variables
load_dotenv()

def convert_to_gemini_finetune(dataset_dir="dataset", output_file="gemini_finetune.jsonl"):
    """
    Convert collected CV dataset to Gemini 1.5/2.0 fine-tuning format.
    Format: {"contents": [{"role": "user", "parts": [{"text": "input"}]}, {"role": "model", "parts": [{"text": "output"}]}]}
    """
    parser = CvParser()
    items_file = os.path.join(dataset_dir, "items.jsonl")
    
    if not os.path.exists(items_file):
        print(f"Error: {items_file} not found.")
        return

    finetune_data = []
    
    with open(items_file, 'r', encoding='utf-8') as f:
        for line in f:
            if not line.strip():
                continue
            
            entry = json.loads(line)
            item_id = entry['id']
            cv_relative_path = entry['cv_file']
            json_relative_path = entry['json_file']
            
            cv_full_path = os.path.join(dataset_dir, cv_relative_path)
            json_full_path = os.path.join(dataset_dir, json_relative_path)
            
            if not os.path.exists(cv_full_path) or not os.path.exists(json_full_path):
                print(f"Warning: Files for {item_id} missing. Skipping.")
                continue
                
            # 1. Extract text from CV (the input)
            print(f"Processing {item_id}...")
            file_type = entry.get('file_type', 'pdf')
            text = parser._extract_text(cv_full_path, file_type)
            
            # Fallback for images: If local OCR fails but Gemini is available, use Gemini Vision to get the text
            if (not text or len(text.strip()) < 50) and file_type in ['jpg', 'jpeg', 'png', 'webp'] and parser.gemini_client:
                print(f"👁️ Local OCR failed for {item_id}, attempting Gemini Vision extraction...")
                vision_result = parser._extract_with_gemini_vision(cv_full_path, file_type)
                if vision_result:
                    # Construct a simulated source text or use the extracted bio/skills as context
                    text = f"NAME: {vision_result.get('name')}\nTITLE: {vision_result.get('title')}\nBIO: {vision_result.get('bio')}\nSKILLS: {', '.join(vision_result.get('skills', []))}"
            
            if not text or len(text.strip()) < 20:
                print(f"Warning: No text extracted for {item_id}. Skipping.")
                continue
            
            # 2. Get the ground truth labels (the expected output)
            with open(json_full_path, 'r', encoding='utf-8') as jf:
                ground_truth = json.load(jf)
                # Clean up metadata if present
                output_json = {k: v for k, v in ground_truth.items() if k != 'confidence'}
            
            # 3. Create the fine-tuning entry
            prompt = parser._build_extraction_prompt(text)
            
            finetune_entry = {
                "contents": [
                    {
                        "role": "user",
                        "parts": [{"text": prompt}]
                    },
                    {
                        "role": "model",
                        "parts": [{"text": json.dumps(output_json, ensure_ascii=False)}]
                    }
                ]
            }
            finetune_data.append(finetune_entry)
            
    # Write the output file
    with open(output_file, 'w', encoding='utf-8') as out:
        for item in finetune_data:
            out.write(json.dumps(item, ensure_ascii=False) + "\n")
            
    print(f"Success! Converted {len(finetune_data)} items to {output_file}")

if __name__ == "__main__":
    convert_to_gemini_finetune(
        dataset_dir=os.path.join(os.path.dirname(__file__), "dataset"),
        output_file=os.path.join(os.path.dirname(__file__), "gemini_finetune.jsonl")
    )
