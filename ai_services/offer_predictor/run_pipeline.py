import subprocess
import sys
import os

def run_script(script_name):
    print(f"--- Running {script_name} ---")
    result = subprocess.run([sys.executable, script_name], capture_output=True, text=True)
    if result.returncode != 0:
        print(f"Error running {script_name}:")
        print(result.stderr)
        return False
    print(result.stdout)
    print(f"--- {script_name} completed ---")
    return True

def main():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(base_dir)
    
    steps = [
        'seed_marketplace.py',
        'generate_ml_data.py',
        'train_model.py'
    ]
    
    for step in steps:
        if not run_script(step):
            print(f"Pipeline failed at step: {step}")
            break
            
    print("Pipeline execution finished.")

if __name__ == "__main__":
    main()
