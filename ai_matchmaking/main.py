"""
Main entry point for the Matchmaking AI service.
Run this file to start the FastAPI server on port 8003.
"""

if __name__ == "__main__":
    import sys
    from pathlib import Path
    
    # Add project root to path if running from ai_matchmaking directory
    current_dir = Path(__file__).resolve().parent
    project_root = current_dir.parent
    if str(project_root) not in sys.path:
        sys.path.insert(0, str(project_root))
    
    import uvicorn
    # Use import string format for reload to work properly
    uvicorn.run("ai_matchmaking.api:app", host="127.0.0.1", port=8003, reload=True)
