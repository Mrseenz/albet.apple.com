import requests
import sys

# Suppress InsecureRequestWarning for self-signed certificates
from requests.packages.urllib3.exceptions import InsecureRequestWarning
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

BASE_URL = "https://localhost:5001"

def test_root():
    print("Testing root (/)..")
    url = f"{BASE_URL}/"
    response = requests.get(url, verify=False)
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.text == "Hello, Albert!", f"Expected 'Hello, Albert!', got '{response.text}'"
    print("Root test PASSED")

def test_device_activation():
    print("Testing device activation (/deviceservices/deviceActivation)...")
    url = f"{BASE_URL}/deviceservices/deviceActivation"
    headers = {
        'Content-Type': 'application/xml',
        'User-Agent': 'TestClient/1.0'
    }
    data = "<Request><Data>Test Activation</Data></Request>"
    response = requests.post(url, headers=headers, data=data, verify=False)
    
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    assert "<Status>Success</Status>" in response.text, \
        f"Expected '<Status>Success</Status>' in response body, got '{response.text}'"
    print("Device activation test PASSED")

def test_drm_handshake():
    print("Testing DRM handshake (/deviceservices/drmHandshake)...")
    url = f"{BASE_URL}/deviceservices/drmHandshake"
    headers = {
        'Content-Type': 'application/xml',
        'User-Agent': 'TestClient/1.0'
    }
    data = "<DRMHandshakeRequest><Data>Test DRM</Data></DRMHandshakeRequest>"
    response = requests.post(url, headers=headers, data=data, verify=False)
    
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    assert "<Status>Success</Status>" in response.text, \
        f"Expected '<Status>Success</Status>' in response body, got '{response.text}'"
    print("DRM handshake test PASSED")

if __name__ == "__main__":
    try:
        test_root()
        test_device_activation()
        test_drm_handshake()
        print("All tests passed!")
    except AssertionError as e:
        print(f"Test FAILED: {e}", file=sys.stderr)
        sys.exit(1)
    except requests.exceptions.ConnectionError as e:
        print(f"Connection FAILED: {e}", file=sys.stderr)
        print("Please ensure the Flask server (app.py) is running and accessible at https://localhost:5001.", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"An unexpected error occurred: {e}", file=sys.stderr)
        sys.exit(1)
