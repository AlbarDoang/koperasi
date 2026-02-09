#!/usr/bin/env python3
import requests
import json

url = "http://localhost/gas/gas_web/flutter_api/login.php"

# Test dengan nomor HP dari screenshot: 08782245160  
payload = {
    "no_hp": "08782245160",  
    "password": "ewe777"
}

try:
    response = requests.post(url, json=payload, timeout=10)
    print("Status Code:", response.status_code)
    print("Raw Response:", repr(response.text[:500]))
    print("\nResponse Headers:")
    for key, val in response.headers.items():
        print(f"  {key}: {val}")
    
    try:
        data = response.json()
        print("\nJSON Response:")
        print(json.dumps(data, indent=2))
    except json.JSONDecodeError as e:
        print(f"\nJSON Decode Error: {e}")
        print("Raw text:", response.text)
except Exception as e:
    print(f"Error: {e}")
