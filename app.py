from flask import Flask, request, make_response
from database import init_sqlite_db
import logging
import sys
import xml.etree.ElementTree as ET
import base64 

app = Flask(__name__)

# --- Constants for DRM Handshake Responses ---
# For Type 1 (already in place and verified)
SERVER_KP_TYPE1 = "A05R4OoqHhIV/gKxjX8CMU5lCPwJzgibztKpyvjM7n/k0/h48wWqrG74RgGXz9nQN6SsLYf1c+0HQsbyq1u3ecIXY55IFU="
FDR_BLOB_TYPE1 = "ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM="
SU_INFO_TYPE1 = "HAEQTDJ6Q7ow3quewewJxoialDXqkVT2dggyY8suwYbRSlOiYzE/kLCXYoBAxQpgP+C8Tc6NEGMy6NiFCoaU267H2x5yRKcerLCVGbYl7FMDSCHwtyIJZGPPMWFERF41OxR6My2BL1hDa1/7/ca/xRUAjEjpJaAE=="
HANDSHAKE_RESPONSE_MESSAGE_TYPE1 = "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"

# For Type 2 (Step 4 data from prompt)
SERVER_KP_STEP4 = "A8vLY7ug8dUYdlL4ngvjJks1RCRrehOzEGGM7o/3Jy/6DAZsgc7rVwfpxViDkuWNjDiZWeiMHVBylWsj8bGPF2yvW+WoAD="
FDR_BLOB_STEP4 = "ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM=" 
SU_INFO_STEP4 = "HAEVO0M9LOEOZRBRuuU5SwNnRZNiDxD7K3zwMj7Zw3KPnfc5Q48eSSwNLNN8Isrlsk+Qis9SSbiDWkeMGIwS4fa9nX6mf7qUUDhH8bkHahy0n0neXnkEcWfW2PXs79zAZyuQ1uMylDaRTlUqemDLk1Bwm+yQhyj1+lIq1mrb="
HANDSHAKE_RESPONSE_MESSAGE_STEP4 = "AmKxRFuvyvv77iEmSdq7BvvvyKcQjZTxNjj8hULEEs/VGSc2qN4Q+mfBFJwIgOG+srIYMmBMfoZDgd/pRtiTF7dC1BUBoCyVCnSeqJLegujKI20fNkfkFWYhQ9M4GYd/x8kG9m4kCj3xj21fOh+CX7zKD5QdGrHpHXcdrP1BrbQN/XcfaiJGrQN5/1Kytlp1K21M1uQoTtu0egWz6KoS3EVjXJ9Y+Y0V5B848dB+b60yFATXzWHR0g8VPZmW0CgZMAomHtEkKv3KjYF5+IH+iPqaeixidxCTxuFo4eXr7W7oHxOXs+/e9kOmijbfqGRX8WDgyYYKu6KPXK3WqqiKDORow5Xxu3YHmXWY9gCNvovHtEa3P/OR1v0WZSs7ALJMZCUpiwULzzzDg3srq+FGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OH=="


for handler in app.logger.handlers[:]:
    app.logger.removeHandler(handler)
stream_handler = logging.StreamHandler(sys.stderr)
stream_handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
stream_handler.setFormatter(formatter)
app.logger.addHandler(stream_handler)
app.logger.setLevel(logging.INFO)

ACTIVATION_LOCK_HTML = """<!DOCTYPE html>
<html><head><title>iPhone Activation</title></head><body>Activation Lock</body></html>"""
STEP3_ACTIVATION_RECORD_HTML_RESPONSE = """<!DOCTYPE html>
<html><head><title>iPhone Activation Step 3</title></head><body><script id="protocol" type="text/x-apple-plist">
<plist version="1.0"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>
</script></body></html>"""

def get_plist_value(xml_root, key_name):
    if xml_root is None or xml_root.tag != 'plist' or len(xml_root) == 0 or xml_root[0].tag != 'dict':
        return None
    main_dict = xml_root[0]
    for i, child in enumerate(main_dict):
        if child.tag == 'key' and child.text == key_name:
            if i + 1 < len(main_dict):
                return main_dict[i+1].text
    return None

@app.route('/')
def hello_albert():
    return "Hello, Albert!"

@app.route('/deviceservices/deviceActivation', methods=['POST'])
def device_activation():
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DeviceActivation - User-Agent: {user_agent}")
    login = request.form.get('login')
    password = request.form.get('password') 
    activation_info_base64_from_form = request.form.get('activation-info-base64')

    if login is not None and password is not None and activation_info_base64_from_form is not None:
        app.logger.info("DeviceActivation - Step 3 (Credentials Submission) request received.")
        app.logger.info(f"DeviceActivation - Login attempt for: {login}")
        response = make_response(STEP3_ACTIVATION_RECORD_HTML_RESPONSE)
        response.headers['Content-Type'] = 'text/html'
        return response

    activation_info_xml_str = request.form.get('activation-info') 
    app.logger.info(f"DeviceActivation - activation-info (Outer XML String snippet): {activation_info_xml_str[:300] if activation_info_xml_str else 'N/A'}...")
    try:
        if activation_info_xml_str: 
            outer_xml_root = ET.fromstring(activation_info_xml_str)
            activation_info_data_element = None
            outer_main_dict = outer_xml_root.find('dict')
            if outer_main_dict is not None:
                current_key_text = None
                for child_el in outer_main_dict: 
                    if child_el.tag == 'key':
                        current_key_text = child_el.text
                    elif current_key_text == 'ActivationInfoXML' and child_el.tag == 'data':
                        activation_info_data_element = child_el
                        break 
                    else:
                        current_key_text = None 
            if activation_info_data_element is not None and activation_info_data_element.text:
                decoded_inner_xml_str = base64.b64decode(activation_info_data_element.text).decode('utf-8')
                inner_xml_root = ET.fromstring(decoded_inner_xml_str)
                activation_request_info_found = False
                inner_main_dict = inner_xml_root.find('dict')
                if inner_main_dict is not None:
                    current_key_text_inner = None
                    for child_inner in inner_main_dict: 
                        if child_inner.tag == 'key':
                            current_key_text_inner = child_inner.text
                        if current_key_text_inner == 'ActivationRequestInfo': 
                            activation_request_info_found = True
                            break 
                        if child_inner.tag != 'key':
                             current_key_text_inner = None
                if activation_request_info_found:
                    app.logger.info("DeviceActivation - Activation Lock scenario detected. Returning HTML.")
                    response = make_response(ACTIVATION_LOCK_HTML)
                    response.headers['Content-Type'] = 'text/html'
                    return response
    except Exception as e:
        app.logger.error(f"DeviceActivation - Error processing for HTML lock: {e}")

    app.logger.info("DeviceActivation - Proceeding with default XML plist response.")
    plist_response = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict><key>device-activation</key><dict>
<key>activation-record</key><dict><key>FairPlayKeyData</key><data>PLACEHOLDER_FAIRPLAY_KEY_DATA</data>
<key>DevicePublicKey</key><string>PLACEHOLDER_DEVICE_PUBLIC_KEY</string>
<key>AccountToken</key><string>dummy-account-token</string>
<key>AccountTokenCertificate</key><data>PLACEHOLDER_ACCOUNT_TOKEN_CERTIFICATE_DATA</data></dict>
<key>activation-info-signature</key><data>PLACEHOLDER_ACTIVATION_INFO_SIGNATURE_DATA</data>
</dict></dict></plist>"""
    response = make_response(plist_response)
    response.headers['Content-Type'] = 'application/xml'
    return response

@app.route('/deviceservices/drmHandshake', methods=['POST'])
def drm_handshake():
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DRMHandshake - User-Agent: {user_agent}")
    xml_data_str = request.data.decode('utf-8')
    if not xml_data_str:
        app.logger.error("DRMHandshake - Empty request body")
        return make_response("Bad Request: Empty XML data", 400)
    try:
        root = ET.fromstring(xml_data_str)
    except ET.ParseError as e:
        app.logger.error(f"DRMHandshake - XML ParseError: {e}")
        return make_response("Bad Request: Invalid XML", 400)

    unique_device_id = get_plist_value(root, 'UniqueDeviceID')
    app.logger.info(f"DRMHandshake - UniqueDeviceID: {unique_device_id}")
    drm_request = get_plist_value(root, 'DRMRequest')
    response_plist_str = ""

    if get_plist_value(root, 'CollectionBlob') and get_plist_value(root, 'HandshakeRequestMessage'): # Type 1
        app.logger.info("DRMHandshake - Type 1 request. Using existing exact response.")
        response_plist_str = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>serverKP</key>
    <data>{SERVER_KP_TYPE1}</data>
    <key>FDRBlob</key>
    <data>{FDR_BLOB_TYPE1}</data>
    <key>SUInfo</key>
    <data>{SU_INFO_TYPE1}</data>
    <key>HandshakeResponseMessage</key>
    <data>{HANDSHAKE_RESPONSE_MESSAGE_TYPE1}</data>
</dict>
</plist>"""
    elif drm_request: # Type 2 request
        app.logger.info("DRMHandshake - Type 2 request. Using Step 4 exact response data.")
        app.logger.info(f"DRMHandshake - DRMRequest (first 30): {drm_request[:30] if drm_request else 'N/A'}")
        response_plist_str = f"""<?xml version="1.0" encoding="UTF-8"?>
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
    else: 
        app.logger.warning("DRMHandshake - Unknown request type. Defaulting to Type 1 response structure.")
        response_plist_str = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>serverKP</key><data>{SERVER_KP_TYPE1}</data>
    <key>FDRBlob</key><data>{FDR_BLOB_TYPE1}</data>
    <key>SUInfo</key><data>{SU_INFO_TYPE1}</data>
    <key>HandshakeResponseMessage</key><data>{HANDSHAKE_RESPONSE_MESSAGE_TYPE1}</data>
</dict>
</plist>"""

    response = make_response(response_plist_str)
    response.headers['Content-Type'] = 'application/xml'
    return response

if __name__ == '__main__':
    app.run(debug=False, port=5001, ssl_context=('cert.pem', 'key.pem'))
