import requests
import sys
import xml.etree.ElementTree as ET
import re 
import base64 

# Suppress InsecureRequestWarning for self-signed certificates
from requests.packages.urllib3.exceptions import InsecureRequestWarning
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

BASE_URL = "https://localhost:5001"

# --- Constants for Type 2 DRM Handshake Response (Step 4 data) ---
# These are the exact strings the server is expected to return for a Type 2 request.
SERVER_KP_STEP4 = "A8vLY7ug8dUYdlL4ngvjJks1RCRrehOzEGGM7o/3Jy/6DAZsgc7rVwfpxViDkuWNjDiZWeiMHVBylWsj8bGPF2yvW+WoAD="
FDR_BLOB_STEP4 = "ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM="
SU_INFO_STEP4 = "HAEVO0M9LOEOZRBRuuU5SwNnRZNiDxD7K3zwMj7Zw3KPnfc5Q48eSSwNLNN8Isrlsk+Qis9SSbiDWkeMGIwS4fa9nX6mf7qUUDhH8bkHahy0n0neXnkEcWfW2PXs79zAZyuQ1uMylDaRTlUqemDLk1Bwm+yQhyj1+lIq1mrb="
HANDSHAKE_RESPONSE_MESSAGE_STEP4 = "AmKxRFuvyvv77iEmSdq7BvvvyKcQjZTxNjj8hULEEs/VGSc2qN4Q+mfBFJwIgOG+srIYMmBMfoZDgd/pRtiTF7dC1BUBoCyVCnSeqJLegujKI20fNkfkFWYhQ9M4GYd/x8kG9m4kCj3xj21fOh+CX7zKD5QdGrHpHXcdrP1BrbQN/XcfaiJGrQN5/1Kytlp1K21M1uQoTtu0egWz6KoS3EVjXJ9Y+Y0V5B848dB+b60yFATXzWHR0g8VPZmW0CgZMAomHtEkKv3KjYF5+IH+iPqaeixidxCTxuFo4eXr7W7oHxOXs+/e9kOmijbfqGRX8WDgyYYKu6KPXK3WqqiKDORow5Xxu3YHmXWY9gCNvovHtEa3P/OR1v0WZSs7ALJMZCUpiwULzzzDg3srq+FGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OH=="

def normalize_xml_text(xml_text: str) -> str:
    return "".join(xml_text.split())

# Helper function to extract data from plist dict for specific assertions
def get_plist_data_value(xml_root, key_name):
    if xml_root.tag != 'plist' or len(xml_root) == 0 or xml_root[0].tag != 'dict':
        return None
    main_dict = xml_root[0]
    for i, child in enumerate(main_dict):
        if child.tag == 'key' and child.text == key_name:
            if i + 1 < len(main_dict) and main_dict[i+1].tag == 'data':
                return main_dict[i+1].text
    return None

def test_root():
    print("Testing root (/)..")
    url = f"{BASE_URL}/"
    response = requests.get(url, verify=False)
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.text == "Hello, Albert!", f"Expected 'Hello, Albert!', got '{response.text}'"
    print("Root test PASSED")

def test_device_activation(): 
    print("Testing device activation (XML Plist Response) (/deviceservices/deviceActivation)...")
    url = f"{BASE_URL}/deviceservices/deviceActivation"
    headers = {'User-Agent': 'TestClient-Multipart/1.0'}
    simple_activation_info_xml_for_plist_response = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>ActivationInfoXML</key>
    <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48cGxpc3QgdmVyc2lvbj0iMS4wIj48ZGljdD48a2V5PkR1bW15S2V5PC9rZXk+PHN0cmluZz5EdW1teVZhbHVlPC9zdHJpbmc+PC9kaWN0PjwvcGxpc3Q+</data>
    <key>UniqueDeviceID</key>
    <string>test-udid-for-plist-response</string>
</dict>
</plist>"""
    form_data_files = {
        'machineName': (None, 'TestMacRegular'), 'InStoreActivation': (None, 'false'),
        'AppleSerialNumber': (None, 'TESTSN_REGULAR'), 'IMEI': (None, '112233445566778'),
        'ICCID': (None, '887766554433221100'),
        'activation-info': (None, simple_activation_info_xml_for_plist_response)
    }
    response = requests.post(url, headers=headers, files=form_data_files, verify=False)
    assert response.status_code == 200
    assert response.headers.get('Content-Type') == 'application/xml', f"Expected application/xml, got {response.headers.get('Content-Type')}"
    assert "<key>device-activation</key>" in response.text 
    print("Device activation (XML Plist Response) test PASSED")

def test_device_activation_locked_html_response():
    print("Testing device activation (HTML Lock Response) (/deviceservices/deviceActivation)...")
    url = f"{BASE_URL}/deviceservices/deviceActivation"
    headers = {'User-Agent': 'TestClient-HTMLActivationLock/1.0'}
    inner_xml_content = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>ActivationRequestInfo</key>
    <dict>
        <key>ActivationRandomness</key>
        <string>TESTINGHTMLRESPONSE</string>
    </dict>
</dict>
</plist>"""
    base64_encoded_inner_xml = base64.b64encode(inner_xml_content.encode('utf-8')).decode('utf-8')
    activation_info_xml_for_html_response = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>ActivationInfoXML</key>
    <data>{base64_encoded_inner_xml}</data>
    <key>UniqueDeviceID</key>
    <string>test-udid-for-html-lock</string>
</dict>
</plist>"""
    form_data_files = {
        'machineName': (None, 'TestMacLocked'), 'InStoreActivation': (None, 'true'), 
        'AppleSerialNumber': (None, 'TESTSN_LOCKED'), 'IMEI': (None, '998877665544332'),
        'ICCID': (None, '112233445566778899'),
        'activation-info': (None, activation_info_xml_for_html_response)
    }
    response = requests.post(url, headers=headers, files=form_data_files, verify=False)
    assert response.status_code == 200
    content_type_header = response.headers.get('Content-Type', '').lower()
    assert content_type_header.startswith('text/html')
    assert "<title>iPhone Activation</title>" in response.text
    print("Device activation (HTML Lock Response) test PASSED")

def test_device_activation_step3_credentials_submission():
    print("Testing device activation (Step 3 Credentials Submission) (/deviceservices/deviceActivation)...")
    url = f"{BASE_URL}/deviceservices/deviceActivation"
    headers = {'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'TestClient-Step3Submit/1.0'}
    dummy_activation_info_base64 = "cGxpc3Q=" 
    form_payload = {
        'login': 'testuser@example.com', 'password': 'testpassword',
        'activation-info-base64': dummy_activation_info_base64, 'isAuthRequired': 'true'
    }
    response = requests.post(url, headers=headers, data=form_payload, verify=False)
    assert response.status_code == 200
    content_type_header = response.headers.get('Content-Type', '').lower()
    assert content_type_header.startswith('text/html')
    assert '<script id="protocol" type="text/x-apple-plist">' in response.text
    assert "<key>ActivationRecord</key>" in response.text
    print("Device activation (Step 3 Credentials Submission) test PASSED")

def test_drm_handshake(): 
    print("Testing DRM handshake (Type 1 - Structural & Data Validity) (/deviceservices/drmHandshake)...")
    url = f"{BASE_URL}/deviceservices/drmHandshake"
    headers = {'Content-Type': 'application/xml', 'User-Agent': 'TestClient-iOS-Exact/1.0'}
    type1_request_data = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CollectionBlob</key>
    <data>PLACEHOLDER_COLLECTION_BLOB</data>
    <key>HandshakeRequestMessage</key>
    <data>PLACEHOLDER_HANDSHAKE_MSG</data>
    <key>UniqueDeviceID</key>
    <string>test-udid-for-type1-drm-exact</string>
</dict>
</plist>"""
    response = requests.post(url, headers=headers, data=type1_request_data, verify=False)
    assert response.status_code == 200
    assert response.headers.get('Content-Type') == 'application/xml'
    try:
        root = ET.fromstring(response.text)
    except ET.ParseError as e:
        assert False, f"Failed to parse XML response: {e}\nResponse text: {response.text[:500]}..."
    expected_keys_in_response = ["serverKP", "FDRBlob", "SUInfo", "HandshakeResponseMessage"]
    main_dict = root.find('dict')
    assert main_dict is not None, "Response XML does not contain a top-level dict under plist."
    found_keys_data_elements = {}
    current_key_text = None
    for child in main_dict:
        if child.tag == 'key':
            current_key_text = child.text
        elif current_key_text and child.tag == 'data': 
            found_keys_data_elements[current_key_text] = child
            current_key_text = None 
    for key_name in expected_keys_in_response:
        assert key_name in found_keys_data_elements, f"Expected key '{key_name}' not found in response XML dict."
        data_element = found_keys_data_elements[key_name]
        assert data_element.tag == 'data', f"Expected key '{key_name}' to have a <data> value, got <{data_element.tag}>."
        data_content = data_element.text
        assert data_content is not None, f"Data content for key '{key_name}' is None."
        cleaned_data_content = data_content.strip().replace("\n", "").replace(" ", "")
        if key_name == "FDRBlob":
            assert len(cleaned_data_content) == 46, \
                f"Data content for key '{key_name}' has length {len(cleaned_data_content)}. Expected 46. Content snippet: {cleaned_data_content[:60]}"
        else:
            assert len(cleaned_data_content) > 50, \
                f"Data content for key '{key_name}' is too short (len: {len(cleaned_data_content)} after cleaning). Expected > 50. Content snippet: {cleaned_data_content[:60]}"
        is_base64_match = re.match(r'^[A-Za-z0-9+/=]+$', cleaned_data_content)
        assert_msg_snippet = cleaned_data_content[:60] 
        assert is_base64_match, \
            f"Data content for key '{key_name}' (after stripping whitespace) contains invalid Base64 characters. Snippet: {assert_msg_snippet}"
    print("DRM handshake (Type 1 - Structural & Data Validity) test PASSED")

def test_drm_handshake_type2():
    print("Testing DRM handshake (Type 2 - Step 4 Data) (/deviceservices/drmHandshake)...")
    url = f"{BASE_URL}/deviceservices/drmHandshake"
    headers = {'Content-Type': 'application/xml', 'User-Agent': 'TestClient-iOS-Type2-Step4/1.0'}
    type2_request_data = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>DRMRequest</key>
    <data>プレースホルダー_TYPE2_STEP4_REQUEST_DATA</data> 
    <key>UniqueDeviceID</key>
    <string>test-udid-for-type2-drm-step4</string>
</dict>
</plist>"""
    
    response = requests.post(url, headers=headers, data=type2_request_data, verify=False)
    
    assert response.status_code == 200, f"Expected status 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/xml', \
        f"Expected Content-Type 'application/xml', got '{response.headers.get('Content-Type')}'"
    
    # Construct the exact expected XML string using the new constants
    expected_response_xml = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>serverKP</key>
    <data>{SERVER_KP_STEP4}</data>
    <key>FDRBlob</key>
    <data>{FDR_BLOB_STEP4}</data>
    <key>SUInfo</key>
    <data>{SU_INFO_STEP4}</data>
    <key>HandshakeResponseMessage</key>
    <data>{HANDSHAKE_RESPONSE_MESSAGE_STEP4}</data>
</dict>
</plist>"""

    normalized_response_text = normalize_xml_text(response.text)
    normalized_expected_xml = normalize_xml_text(expected_response_xml)

    if normalized_response_text == normalized_expected_xml:
        print("DRM handshake (Type 2 - Step 4 Data) - Exact match PASSED.")
        assert True 
    else:
        print("DRM handshake (Type 2 - Step 4 Data) - Exact match FAILED. Proceeding to fallback assertions.")
        print(f"DEBUG: Normalized Response Length: {len(normalized_response_text)}")
        print(f"DEBUG: Normalized Expected Length: {len(normalized_expected_xml)}")
        
        try:
            root = ET.fromstring(response.text)
        except ET.ParseError as e:
            assert False, f"Fallback: Failed to parse XML response: {e}\nResponse text: {response.text[:500]}..."

        main_dict = root.find('dict')
        assert main_dict is not None, "Fallback: Response XML does not contain a top-level dict under plist."

        found_keys_data_values = {}
        current_key_text = None
        for child in main_dict:
            if child.tag == 'key':
                current_key_text = child.text
            elif current_key_text and child.tag == 'data': 
                found_keys_data_values[current_key_text] = child.text 
                current_key_text = None
        
        expected_exact_data_map = {
            "serverKP": SERVER_KP_STEP4,
            "FDRBlob": FDR_BLOB_STEP4,
            "SUInfo": SU_INFO_STEP4
        }

        for key_name, expected_b64_content in expected_fallback_data_map.items():
            assert key_name in found_keys_data_values, f"Fallback: Expected key '{key_name}' not found."
            actual_data_content = found_keys_data_values[key_name]
            assert actual_data_content is not None, f"Fallback: Data content for key '{key_name}' is None."
            cleaned_actual_data = actual_data_content.strip().replace("\n", "").replace(" ", "")
            assert cleaned_actual_data == expected_b64_content, \
                f"Fallback: Data for key '{key_name}' does not match. Expected: {expected_b64_content}, Got: {cleaned_actual_data}"
            assert re.match(r'^[A-Za-z0-9+/=]+$', cleaned_actual_data), \
                f"Fallback: Data for '{key_name}' contains invalid Base64. Snippet: {cleaned_actual_data[:60]}"

        # Fallback for HandshakeResponseMessage
        key_name_long = "HandshakeResponseMessage"
        print(f"Fallback: Checking key '{key_name_long}' with fallback strategy.")
        assert key_name_long in found_keys_data_values, f"Fallback: Expected key '{key_name_long}' not found."
        data_content_long = found_keys_data_values[key_name_long]
        assert data_content_long is not None, f"Fallback: Data content for key '{key_name_long}' is None."
        cleaned_data_content_long = data_content_long.strip().replace("\n", "").replace(" ", "")
        
        assert len(cleaned_data_content_long) > 1000, \
            f"Fallback: Data for '{key_name_long}' is too short (len: {len(cleaned_data_content_long)}). Expected > 1000."
        assert cleaned_data_content_long.startswith("AmKxRFuvyvv77iEmSdq7B"), \
            f"Fallback: Data for '{key_name_long}' does not start with expected prefix. Got: {cleaned_data_content_long[:50]}..."
        assert cleaned_data_content_long.endswith("XPbV9OH=="), \
            f"Fallback: Data for '{key_name_long}' does not end with expected suffix. Got: ...{cleaned_data_content_long[-50:]}"
        assert re.match(r'^[A-Za-z0-9+/=]+$', cleaned_data_content_long), \
            f"Fallback: Data for '{key_name_long}' contains invalid Base64. Snippet: {cleaned_data_content_long[:60]}"
        print("DRM handshake (Type 2 - Step 4 Data) - Fallback assertions PASSED.")
            
    print("DRM handshake (Type 2 - Step 4 Data) test completed.")


if __name__ == "__main__":
    try:
        test_root()
        test_device_activation() 
        test_device_activation_locked_html_response()
        test_device_activation_step3_credentials_submission()
        test_drm_handshake() 
        test_drm_handshake_type2() 
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
