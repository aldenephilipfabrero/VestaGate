"""Test detection server with a simple image"""
import requests
import base64
import numpy as np
import cv2
import json

# Create a simple test image (640x480 with a colored rectangle simulating a person)
img = np.zeros((480, 640, 3), dtype=np.uint8)
img[:, :] = (100, 100, 100)  # Gray background

# Draw a "person-like" shape
cv2.rectangle(img, (200, 50), (440, 450), (150, 100, 50), -1)  # Body
cv2.rectangle(img, (260, 10), (380, 80), (180, 140, 100), -1)  # Head

# Encode to base64
_, buffer = cv2.imencode('.jpg', img)
img_base64 = base64.b64encode(buffer).decode('utf-8')

# Send to detection server
url = 'http://127.0.0.1:5000/detect'
data = {'image': img_base64}

print("Sending test image to detection server...")
print(f"Image size: {img.shape}")
print(f"Base64 length: {len(img_base64)}")

try:
    response = requests.post(url, json=data, timeout=15)
    print(f"\nStatus code: {response.status_code}")
    print(f"\nResponse:")
    print(json.dumps(response.json(), indent=2))
except Exception as e:
    print(f"Error: {e}")
