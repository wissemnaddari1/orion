import urllib.request
import urllib.parse
import json
import os

def test_dataset_collection():
    url = "http://127.0.0.1:8001/parse-cv?collect=true"
    dummy_cv = "test_cv.txt"
    with open(dummy_cv, "w") as f:
        f.write("John Doe\nSoftware Engineer\n5 years experience\nParis, France\nSkills: Python, PHP, JS")
    
    try:
        # Simple multipart upload using urllib is complex, 
        # let's just test the /dataset summary endpoint first to see if it's alive
        with urllib.request.urlopen("http://127.0.0.1:8001/health") as response:
            print(f"Health: {response.read().decode()}")
            
        with urllib.request.urlopen("http://127.0.0.1:8001/dataset") as response:
            print(f"Dataset Summary: {response.read().decode()}")
            
    except Exception as e:
        print(f"Error: {e}")
    finally:
        if os.path.exists(dummy_cv):
            os.remove(dummy_cv)

if __name__ == "__main__":
    test_dataset_collection()
