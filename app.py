from flask import Flask, request, make_response
import logging
import sys
import xml.etree.ElementTree as ET
import base64
# ---- ADD THIS LINE ----
from database import init_sqlite_db

# ---- ADD THIS LINE ----
init_sqlite_db()

app = Flask(__name__)

# --- Constants for DRM Handshake Responses ---
# For Type 1 (already in place and verified)
SERVER_KP_TYPE1 = "A05R4OoqHhIV/gKxjX8CMU5lCPwJzgibztKpyvjM7n/k0/h48wWqrG74RgGXz9nQN6SsLYf1c+0HQsbyq1u3ecIXY55IFU="
FDR_BLOB_TYPE1 = "ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM="
SU_INFO_TYPE1 = "HAEQTDJ6Q7ow3quewewJxoialDXqkVT2dggyY8suwYbRSlOiYzE/kLCXYoBAxQpgP+C8Tc6NEGMy6NiFCoaU267H2x5yRKcerLCVGbYl7FMDSCHwtyIJZGPPMWFERF41OxR6My2BL1hDa1/7/ca/xRUAjEjpJaAE=="
HANDSHAKE_RESPONSE_MESSAGE_TYPE1 = "AtrGIpIDIv75QOgi5ay3MDTXkjMhCcRVo/dF8hdxbIV1aLy9RaZAVjnMJ2rX3nWCxFejb9ih1hH+1L/pFUCDRhQBoN+aA4UjAUIv7W5m+ejQ6a3m0DCjfkERoARl42s2Y5Mc9pVRnDWU5U1fOh+CX7zKD5QdGrHpHXcdrP1BrbQN/XcfaiJGrQN5/1Kytlp1K21M1uQoTtu0egWz6KoS3EVjXJ9Y+Y0V5B848dB+b60yFATXzWHR0g8VPZmW0CgZMAomHtEkKv3KjYF5+IH+iPqaeixidxCTxuFo4eXr7W7oHxOXs+/e9kOmijbfqGRX8WDgyYYKu6KPXK3WqqiKDORow5Xxu3YHmXWY9gCNvovHtEa3P/OR1v0WZSs7ALJMZCUpiwULzzzDg3srq+FGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OH=="

# For Type 2 (Step 4 data from prompt)
SERVER_KP_STEP4 = "A8vLY7ug8dUYdlL4ngvjJks1RCRrehOzEGGM7o/3Jy/6DAZsgc7rVwfpxViDkuWNjDiZWeiMHVBylWsj8bGPF2yvW+WoAD="
FDR_BLOB_STEP4 = "ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM="
SU_INFO_STEP4 = "HAEVO0M9LOEOZRBRuuU5SwNnRZNiDxD7K3zwMj7Zw3KPnfc5Q48eSSwNLNN8Isrlsk+Qis9SSbiDWkeMGIwS4fa9nX6mf7qUUDhH8bkHahy0n0neXnkEcWfW2PXs79zAZyuQ1uMylDaRTlUqemDLk1Bwm+yQhyj1+lIq1mrb="
HANDSHAKE_RESPONSE_MESSAGE_STEP4 = "AmKxRFuvyvv77iEmSdq7BvvvyKcQjZTxNjj8hULEEs/VGSc2qN4Q+mfBFJwIgOG+srIYMmBMfoZDgd/pRtiTF7dC1BUBoCyVCnSeqJLegujKI20fNkfkFWYhQ9M4GYd/x8kG9m4kCj3xj21fOh+CX7zKD5QdGrHpHXcdrP1BrbQN/XcfaiJGrQN5/1Kytlp1K21M1uQoTtu0egWz6KoS3EVjXJ9Y+Y0V5B848dB+b60yFATXzWHR0g8VPZmW0CgZMAomHtEkKv3KjYF5+IH+iPqaeixidxCTxuFo4eXr7W7oHxOXs+/e9kOmijbfqGRX8WDgyYYKu6KPXK3WqqiKDORow5Xxu3YHmXWY9gCNvovHtEa3P/OR1v0WZSs7ALJMZCUpiwULzzzDg3srq+FGHI1SwVSyAX0Uuj+zFExg9uC+eBb3vt+7LrE9F+969TzHXe6ED3stHnc8Cc60CzXtXhlewikqfbK2Nur5xLeKUnfVUPLYVI1hnAUTAYTlLsM4JX8r6QQZxE1pDdkvC2v23lS9j1npvCGpLjBTtu0nuuMls16KGcv724lk9w66J7wE68XPbV9OH=="

# Setup logging (ensure this is done only once)
if not app.logger.handlers:
    stream_handler = logging.StreamHandler(sys.stderr)
    stream_handler.setLevel(logging.INFO)
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    stream_handler.setFormatter(formatter)
    app.logger.addHandler(stream_handler)
    app.logger.setLevel(logging.INFO)

ACTIVATION_LOCK_HTML = """<!DOCTYPE html>
<html><head><title>iPhone Activation</title></head><body>Activation Lock</body></html>"""

# Step 3 HTML Response (with ActivationRecord embedded)
STEP3_ACTIVATION_RECORD_HTML_RESPONSE = """<!DOCTYPE html>
<html><head><title>iPhone Activation Step 3</title></head><body><script id="protocol" type="text/x-apple-plist">
<plist version="1.0"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>
</script></body></html>"""

# Step 5 XML Response (Final Activation Success)
STEP5_ACTIVATION_SUCCESS_XML = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>iphone-activation</key>
    <dict>
        <key>ack-received</key>
        <true/>
        <key>show-settings</key>
        <true/>
        <key>activation-record</key>
        <dict>
            <key>placeholder</key><string>Step 5 Full ActivationRecord Data Placeholder</string>
        </dict>
    </dict>
</dict>
</plist>"""

def get_plist_key_value_element(xml_string_or_root, key_name):
    root = None
    if isinstance(xml_string_or_root, str):
        try:
            root = ET.fromstring(xml_string_or_root)
        except ET.ParseError:
            return None
    else:
        root = xml_string_or_root
    if root is None or root.tag != "plist" or len(root) == 0 or root[0].tag != "dict":
        return None
    main_dict = root[0]
    for i, child in enumerate(main_dict):
        if child.tag == "key" and child.text == key_name:
            if i + 1 < len(main_dict):
                return main_dict[i+1]
    return None

@app.route('/')
def hello_albert():
    return "Hello, Albert!"

@app.route('/deviceservices/deviceActivation', methods=['POST'])
def device_activation():
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DeviceActivation - User-Agent: {user_agent}")

    if request.content_type.startswith('application/x-www-form-urlencoded'):
        login = request.form.get('login')
        password = request.form.get('password')
        activation_info_base64_from_form = request.form.get('activation-info-base64')
        if login is not None and password is not None and activation_info_base64_from_form is not None:
            app.logger.info("DeviceActivation - Step 3 (Credentials Submission) request detected.")
            app.logger.info(f"DeviceActivation - Login: {login}")
            response = make_response(STEP3_ACTIVATION_RECORD_HTML_RESPONSE)
            response.headers['Content-Type'] = 'text/html'
            return response

    activation_info_xml_str = request.form.get('activation-info')
    if not activation_info_xml_str:
        app.logger.error("DeviceActivation - Missing 'activation-info' in form-data.")
        return make_response("Bad Request: Missing activation-info in form-data", 400)

    app.logger.info(f"DeviceActivation - Received activation-info (multipart), length: {len(activation_info_xml_str)}")

    try:
        outer_xml_root = ET.fromstring(activation_info_xml_str)
        activation_info_xml_data_node = get_plist_key_value_element(outer_xml_root, 'ActivationInfoXML')

        if activation_info_xml_data_node is not None and activation_info_xml_data_node.tag == 'data' and activation_info_xml_data_node.text:
            decoded_inner_xml_str = base64.b64decode(activation_info_xml_data_node.text).decode('utf-8')
            inner_xml_root = ET.fromstring(decoded_inner_xml_str)

            activation_request_info_dict_node = get_plist_key_value_element(inner_xml_root, 'ActivationRequestInfo')
            if activation_request_info_dict_node is not None and activation_request_info_dict_node.tag == 'dict':
                activation_state_node_in_ari = get_plist_key_value_element(activation_request_info_dict_node, 'ActivationState')
                if activation_state_node_in_ari is not None and activation_state_node_in_ari.text == "Unactivated":
                    app.logger.info("DeviceActivation - Step 2 (Activation Lock HTML) scenario detected.")
                    response = make_response(ACTIVATION_LOCK_HTML)
                    response.headers['Content-Type'] = 'text/html'
                    return response

            activation_state_node = get_plist_key_value_element(inner_xml_root, 'ActivationState')
            if activation_state_node is not None and activation_state_node.text == "Activated":
                if not (activation_request_info_dict_node is not None and activation_request_info_dict_node.tag == 'dict'):
                    app.logger.info("DeviceActivation - Step 5 (Final Activation Record XML) scenario detected.")
                    response = make_response(STEP5_ACTIVATION_SUCCESS_XML)
                    response.headers['Content-Type'] = 'application/xml'
                    return response
        else:
            app.logger.info("DeviceActivation - 'ActivationInfoXML' data not found/empty. Treating as original ideviceactivate-style request.")

    except ET.ParseError as e:
        app.logger.error(f"DeviceActivation - XML ParseError in 'activation-info': {e}")
    except base64.binascii.Error as e:
        app.logger.error(f"DeviceActivation - Base64 DecodeError for inner XML: {e}")
    except Exception as e:
        app.logger.error(f"DeviceActivation - General error processing 'activation-info': {e}")

    app.logger.info("DeviceActivation - Proceeding with default XML plist response (ideviceactivate style).")
    default_plist_response = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict><key>device-activation</key><dict>
<key>activation-record</key><dict><key>FairPlayKeyData</key><data>PLACEHOLDER_FAIRPLAY_KEY_DATA</data>
<key>DevicePublicKey</key><string>PLACEHOLDER_DEVICE_PUBLIC_KEY</string>
<key>AccountToken</key><string>dummy-account-token</string>
<key>AccountTokenCertificate</key><data>PLACEHOLDER_ACCOUNT_TOKEN_CERTIFICATE_DATA</data></dict>
<key>activation-info-signature</key><data>PLACEHOLDER_ACTIVATION_INFO_SIGNATURE_DATA</data>
</dict></dict></plist>"""
    response = make_response(default_plist_response)
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

    unique_device_id_node = get_plist_key_value_element(root, 'UniqueDeviceID')
    unique_device_id = unique_device_id_node.text if unique_device_id_node is not None and unique_device_id_node.tag == 'string' else None
    app.logger.info(f"DRMHandshake - UniqueDeviceID: {unique_device_id}")

    drm_request_node = get_plist_key_value_element(root, 'DRMRequest')
    drm_request_data = drm_request_node.text if drm_request_node is not None and drm_request_node.tag == 'data' else None

    collection_blob_node = get_plist_key_value_element(root, 'CollectionBlob')
    collection_blob_data = collection_blob_node.text if collection_blob_node is not None and collection_blob_node.tag == 'data' else None

    handshake_message_node = get_plist_key_value_element(root, 'HandshakeRequestMessage')
    handshake_message_data = handshake_message_node.text if handshake_message_node is not None and handshake_message_node.tag == 'data' else None

    response_plist_str = ""

    if collection_blob_data and handshake_message_data: # Type 1 (Step 1 DRM)
        app.logger.info("DRMHandshake - Type 1 request. Using Step 1 exact response.")
        response_plist_str = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n'
            '<plist version="1.0">\n<dict>\n'
            '    <key>serverKP</key>\n    <data>' + SERVER_KP_TYPE1 + '</data>\n'
            '    <key>FDRBlob</key>\n    <data>' + FDR_BLOB_TYPE1 + '</data>\n'
            '    <key>SUInfo</key>\n    <data>' + SU_INFO_TYPE1 + '</data>\n'
            '    <key>HandshakeResponseMessage</key>\n    <data>' + HANDSHAKE_RESPONSE_MESSAGE_TYPE1 + '</data>\n'
            '</dict>\n</plist>'
        )
    elif drm_request_data: # Type 2 (Step 4 DRM)
        app.logger.info("DRMHandshake - Type 2 request. Using Step 4 exact response data.")
        response_plist_str = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n'
            '<plist version="1.0">\n<dict>\n'
            '    <key>serverKP</key>\n    <data>' + SERVER_KP_STEP4 + '</data>\n'
            '    <key>FDRBlob</key>\n    <data>' + FDR_BLOB_STEP4 + '</data>\n'
            '    <key>SUInfo</key>\n    <data>' + SU_INFO_STEP4 + '</data>\n'
            '    <key>HandshakeResponseMessage</key>\n    <data>' + HANDSHAKE_RESPONSE_MESSAGE_STEP4 + '</data>\n'
            '</dict>\n</plist>'
        )
    else:
        app.logger.warning("DRMHandshake - Unknown request type. Defaulting to Type 1 response structure for safety.")
        response_plist_str = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n'
            '<plist version="1.0">\n<dict>\n'
            '    <key>serverKP</key><data>' + SERVER_KP_TYPE1 + '</data>\n'
            '    <key>FDRBlob</key><data>' + FDR_BLOB_TYPE1 + '</data>\n'
            '    <key>SUInfo</key><data>' + SU_INFO_TYPE1 + '</data>\n'
            '    <key>HandshakeResponseMessage</key><data>' + HANDSHAKE_RESPONSE_MESSAGE_TYPE1 + '</data>\n'
            '</dict>\n</plist>'
        )

    response = make_response(response_plist_str)
    response.headers['Content-Type'] = 'application/xml'
    return response

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, ssl_context=('cert.pem', 'key.pem'), debug=False)
