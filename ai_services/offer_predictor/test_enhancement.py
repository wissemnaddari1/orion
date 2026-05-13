import requests
import json

URL = "http://localhost:8002/offer-enhancement"

# Scenario 1: High quality offer
payload_good = {
    "price_ratio": 0.85,
    "budget_position": 0.5,
    "message_length": 300,
    "deliverables_length": 150,
    "has_deliverables": 1,
    "timeline_ratio": 0.9,
    "included_revisions": 3,
    "worker_rating_avg": 4.8,
    "total_reviews": 25
}

# Scenario 2: Poor quality offer
payload_bad = {
    "price_ratio": 1.25,
    "budget_position": 1.5,
    "message_length": 30,
    "deliverables_length": 0,
    "has_deliverables": 0,
    "timeline_ratio": 1.3,
    "included_revisions": 0,
    "worker_rating_avg": 4.0,
    "total_reviews": 5
}

def test(name, payload):
    print(f"\n--- Testing: {name} ---")
    response = requests.post(URL, json=payload)
    if response.status_code == 200:
        print(json.dumps(response.json(), indent=2))
    else:
        print(f"Error {response.status_code}: {response.text}")

if __name__ == "__main__":
    test("Good Offer", payload_good)
    test("Poor Offer", payload_bad)
