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
        'User-Agent': 'TestClient-Multipart/1.0'
    }
    form_data_files = {
        'machineName': (None, 'TestMac'),
        'InStoreActivation': (None, 'false'),
        'AppleSerialNumber': (None, 'TESTSN12345'),
        'IMEI': (None, '123456789012345'),
        'ICCID': (None, '12345678901234567890'),
        'activation-info': (None, '<xml><info>dummy activation info</info></xml>')
    }
    response = requests.post(url, headers=headers, files=form_data_files, verify=False)
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    expected_plist_substring = "<key>device-activation</key>"
    assert expected_plist_substring in response.text, \
        f"Expected '{expected_plist_substring}' in response body, got '{response.text}'"
    print("Device activation test PASSED")

def test_drm_handshake(): # This is the Type 1 test
    print("Testing DRM handshake (Type 1) (/deviceservices/drmHandshake)...")
    url = f"{BASE_URL}/deviceservices/drmHandshake"
    headers = {
        'Content-Type': 'application/xml',
        'User-Agent': 'TestDRMClient-XML-Type1/1.0' # Clarified User-Agent
    }
    type1_request_data = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>UniqueDeviceID</key>
    <string>TEST_UNIQUE_DEVICE_ID_TYPE1</string>
    <key>CollectionBlob</key>
    <data>TYPE1_COLLECTION_BLOB_DATA_BASE64</data>
    <key>HandshakeRequestMessage</key>
    <data>TYPE1_HANDSHAKE_REQUEST_MESSAGE_DATA_BASE64</data>
</dict>
</plist>"""
    response = requests.post(url, headers=headers, data=type1_request_data, verify=False)
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    expected_response_substring = "<key>serverKP</key>"
    assert expected_response_substring in response.text, \
        f"Expected '{expected_response_substring}' in response body for Type 1, got '{response.text}'"
    print("DRM handshake (Type 1) test PASSED")

def test_drm_handshake_type2():
    print("Testing DRM handshake (Type 2) (/deviceservices/drmHandshake)...")
    url = f"{BASE_URL}/deviceservices/drmHandshake"
    headers = {
        'Content-Type': 'application/xml',
        'User-Agent': 'TestClient-iOS/1.0' # As specified
    }
    type2_request_data = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>DRMRequest</key>
    <data>プレースホルダー</data>
    <key>UniqueDeviceID</key>
    <string>test-udid-for-type2-drm-handshake</string>
</dict>
</plist>"""
    response = requests.post(url, headers=headers, data=type2_request_data, verify=False)
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    expected_response_substring = "<key>serverKP</key>" # Server response structure is the same
    assert expected_response_substring in response.text, \
        f"Expected '{expected_response_substring}' in response body for Type 2, got '{response.text}'"
    print("DRM handshake (Type 2) test PASSED")

if __name__ == "__main__":
    try:
        test_root()
        test_device_activation()
        test_drm_handshake() # This tests Type 1
        test_drm_handshake_type2() # Newly added test for Type 2
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
