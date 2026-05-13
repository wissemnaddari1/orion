import requests
import json

url = "http://localhost:8002/predict-offer"
data = {
    "offer": {
        "price": 600,
        "message": "I have reviewed your project requirements in detail and I am confident I can deliver a world-class solution. My approach involves a robust architecture using modern tech stacks. I provide full documentation, unit testing, and post-deployment support. I have handled over 50 similar projects with 100% client satisfaction. I am ready to start immediately.",
        "estimated_time_days": 4,
        "included_revisions": 10,
        "deliverables": "1. Wireframes, 2. Interactive Prototype, 3. Frontend Implementation, 4. API Integration, 5. Deployment & Docs, 6. 3 Months Support",
        "is_urgent": 1,
        "priority_level": "HIGH"
    },
    "service_request": {
        "budget_min": 500,
        "budget_max": 1500,
        "duration": 10,
        "category_id": 1
    },
    "worker": {
        "rating_avg": 5.0,
        "total_reviews": 150
    }
}

try:
    response = requests.post(url, json=data)
    print(f"Status: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
except Exception as e:
    print(f"Error: {e}")
