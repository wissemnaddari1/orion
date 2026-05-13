import uvicorn
import sys
from pathlib import Path

if __name__ == "__main__":
    # Add project root to path if running from ai_contract_generator directory
    current_dir = Path(__file__).resolve().parent
    project_root = current_dir.parent
    if str(project_root) not in sys.path:
        sys.path.insert(0, str(project_root))
        
    uvicorn.run("ai_contract_generator.api:app", host="127.0.0.1", port=8004, reload=True)
