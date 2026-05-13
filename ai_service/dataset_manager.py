import os
import json
import uuid
import shutil
from typing import Dict, Any, List
from datetime import datetime

class DatasetManager:
    def __init__(self, base_dir: str = "dataset"):
        self.base_dir = base_dir
        self.files_dir = os.path.join(base_dir, "files")
        self.index_file = os.path.join(base_dir, "items.jsonl")
        
        # Create directories if they don't exist
        os.makedirs(self.files_dir, exist_ok=True)
        if not os.path.exists(self.index_file):
            with open(self.index_file, 'w') as f:
                pass

    def add_item(self, file_path: str, data: Dict[str, Any]) -> str:
        """Add a CV file and its parsed data to the dataset"""
        item_id = str(uuid.uuid4())
        file_ext = os.path.splitext(file_path)[1].lower()
        
        # Destination paths
        dest_cv_path = os.path.join(self.files_dir, f"{item_id}{file_ext}")
        dest_json_path = os.path.join(self.files_dir, f"{item_id}.json")
        
        try:
            # Copy CV file
            shutil.copy2(file_path, dest_cv_path)
            
            # Save JSON data
            with open(dest_json_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
            
            # Update index
            entry = {
                "id": item_id,
                "timestamp": datetime.now().isoformat(),
                "file_type": file_ext.lstrip('.'),
                "cv_file": f"files/{item_id}{file_ext}",
                "json_file": f"files/{item_id}.json",
                "title": data.get('title', ''),
                "confidence": data.get('confidence', 0)
            }
            
            with open(self.index_file, 'a', encoding='utf-8') as f:
                f.write(json.dumps(entry) + "\n")
                
            return item_id
        except Exception as e:
            print(f"Dataset Error: {e}")
            return None

    def get_summary(self) -> Dict[str, Any]:
        """Get a summary of the dataset"""
        items = []
        if os.path.exists(self.index_file):
            with open(self.index_file, 'r', encoding='utf-8') as f:
                for line in f:
                    if line.strip():
                        items.append(json.loads(line))
        
        return {
            "total_items": len(items),
            "recent_items": items[-10:][::-1], # Last 10, newest first
            "stats": {
                "pdf": len([i for i in items if i['file_type'] == 'pdf']),
                "image": len([i for i in items if i['file_type'] in ['jpg', 'jpeg', 'png', 'webp']]),
                "avg_confidence": sum([i.get('confidence', 0) for i in items]) / len(items) if items else 0
            }
        }

    def export_dataset(self) -> str:
        """Prepare dataset for export (returns path to metadata)"""
        return self.index_file
