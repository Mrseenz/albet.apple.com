from flask import Flask, request, make_response
import logging
import sys
import xml.etree.ElementTree as ET # Added for XML parsing

app = Flask(__name__)

# Existing logging configuration
for handler in app.logger.handlers[:]:
    app.logger.removeHandler(handler)
stream_handler = logging.StreamHandler(sys.stderr)
stream_handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
stream_handler.setFormatter(formatter)
app.logger.addHandler(stream_handler)
app.logger.setLevel(logging.INFO)

# Helper function to extract values from plist dict
def get_plist_value(xml_root, key_name):
    """
    Finds a <key> with text key_name in a plist <dict> and returns the text of the next sibling element (e.g., <string>, <data>).
    """
    if xml_root is None or xml_root.tag != 'plist' or len(xml_root) == 0 or xml_root[0].tag != 'dict':
        return None
    
    main_dict = xml_root[0]
    for i, child in enumerate(main_dict):
        if child.tag == 'key' and child.text == key_name:
            if i + 1 < len(main_dict):
                return main_dict[i+1].text # Works for <string>, <data> will also return its text content
    return None

@app.route('/')
def hello_albert():
    return "Hello, Albert!"

@app.route('/deviceservices/deviceActivation', methods=['POST'])
def device_activation():
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DeviceActivation - User-Agent: {user_agent}")
    machine_name = request.form.get('machineName')
    in_store_activation = request.form.get('InStoreActivation')
    apple_serial_number = request.form.get('AppleSerialNumber')
    imei = request.form.get('IMEI')
    iccid = request.form.get('ICCID')
    activation_info_xml = request.form.get('activation-info')
    app.logger.info(f"DeviceActivation - machineName: {machine_name}")
    app.logger.info(f"DeviceActivation - InStoreActivation: {in_store_activation}")
    app.logger.info(f"DeviceActivation - AppleSerialNumber: {apple_serial_number}")
    app.logger.info(f"DeviceActivation - IMEI: {imei}")
    app.logger.info(f"DeviceActivation - ICCID: {iccid}")
    app.logger.info(f"DeviceActivation - activation-info (XML): {activation_info_xml}")
    plist_response = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>device-activation</key>
    <dict>
        <key>activation-record</key>
        <dict>
            <key>FairPlayKeyData</key>
            <data>PLACEHOLDER_FAIRPLAY_KEY_DATA</data>
            <key>DevicePublicKey</key>
            <string>PLACEHOLDER_DEVICE_PUBLIC_KEY</string>
            <key>AccountToken</key>
            <string>dummy-account-token</string>
            <key>AccountTokenCertificate</key>
            <data>PLACEHOLDER_ACCOUNT_TOKEN_CERTIFICATE_DATA</data>
        </dict>
        <key>activation-info-signature</key>
        <data>PLACEHOLDER_ACTIVATION_INFO_SIGNATURE_DATA</data>
    </dict>
</dict>
</plist>"""
    response = make_response(plist_response)
    response.headers['Content-Type'] = 'application/xml'
    return response

@app.route('/deviceservices/drmHandshake', methods=['POST'])
def drm_handshake():
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DRMHandshake - User-Agent: {user_agent}")

    try:
        xml_data = request.data.decode('utf-8')
        if not xml_data:
            app.logger.error("DRMHandshake - Empty request body")
            return make_response("Bad Request: Empty XML data", 400)
        
        root = ET.fromstring(xml_data)

        unique_device_id = get_plist_value(root, 'UniqueDeviceID')
        app.logger.info(f"DRMHandshake - UniqueDeviceID: {unique_device_id}")

        collection_blob = get_plist_value(root, 'CollectionBlob')
        handshake_request_message = get_plist_value(root, 'HandshakeRequestMessage')
        drm_request = get_plist_value(root, 'DRMRequest')

        if collection_blob and handshake_request_message:
            app.logger.info("DRMHandshake - Type 1 (Initial Handshake) request received.")
            app.logger.info(f"DRMHandshake - CollectionBlob (first 30): {collection_blob[:30] if collection_blob else 'N/A'}")
            app.logger.info(f"DRMHandshake - HandshakeRequestMessage (first 30): {handshake_request_message[:30] if handshake_request_message else 'N/A'}")
        elif drm_request:
            app.logger.info("DRMHandshake - Type 2 (Subsequent Handshake) request received.")
            app.logger.info(f"DRMHandshake - DRMRequest (first 30): {drm_request[:30] if drm_request else 'N/A'}")
        else:
            app.logger.warning("DRMHandshake - Unknown request type (neither Type 1 nor Type 2 keys found).")

    except ET.ParseError as e:
        app.logger.error(f"DRMHandshake - XML ParseError: {e}")
        return make_response("Bad Request: Invalid XML", 400)
    except Exception as e:
        app.logger.error(f"DRMHandshake - Error processing request: {e}")
        return make_response("Internal Server Error", 500)

    # Construct the new XML plist response using provided placeholders
    response_plist = """<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>serverKP</key>
    <data>A05R4OoqHhIV/gKxjX8CMU5lCPwJzgibztKpyvjM7n/k0/h48wWqrG74RgGXz9nQN6SsLYf1c+0HQsbyq1u3ecIXY55IFU=</data>
    <key>FDRBlob</key>
    <data>ukMtH9RZdSQvHzBx7FiBGr7/KcmloX/XwoWeWnWeb6IRM=</data>
    <key>SUInfo</key>
    <data>HAEQTDI6Q7ow3quewewJxoialDXqkVT2dggyY8suwYbRSlOiYzE/kLCXYoBAxQpgP+C8Tc6NEGMy6NiFCoaU267H2x5yRKcerLCVGbYl7FMDSCHwtyIJZGPPMWFERF41OxR6My2BL1hDa1/7/ca/xRUAjEjpJaAE==</data>
    <key>HandshakeResponseMessage</key>
    <data>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</data>
</dict>
</plist>"""

    response = make_response(response_plist)
    response.headers['Content-Type'] = 'application/xml'
    return response

if __name__ == '__main__':
    app.run(debug=False, port=5001, ssl_context=('cert.pem', 'key.pem'))
