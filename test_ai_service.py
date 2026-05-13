
import http.client
import json

url = "127.0.0.1:8001"
data = {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'.",
    "category": "Authentication"
}

try:
    conn = http.client.HTTPConnection(url)
    headers = {'Content-type': 'application/json'}
    json_data = json.dumps(data)
    conn.request("POST", "/ai/solve", json_data, headers)
    response = conn.getresponse()
    print(f"Status Code: {response.status}")
    print(f"Content-Type: {response.getheader('Content-Type')}")
    print(f"Response: {response.read().decode('utf-8')}")
    conn.close()
except Exception as e:
    print(f"Error: {e}")
