from flask import Flask, request, make_response
import logging
import sys

app = Flask(__name__)

# Existing logging configuration from previous steps
# This ensures app.logger.info() attempts to log as configured
# Moved this block to be processed when the Flask 'app' object is created,
# rather than only when __name__ == '__main__'. This is better practice
# if the app might be imported by a WSGI server in other contexts.
for handler in app.logger.handlers[:]:
    app.logger.removeHandler(handler)
stream_handler = logging.StreamHandler(sys.stderr)
stream_handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
stream_handler.setFormatter(formatter)
app.logger.addHandler(stream_handler)
app.logger.setLevel(logging.INFO)

@app.route('/')
def hello_albert():
    return "Hello, Albert!"

@app.route('/deviceservices/deviceActivation', methods=['POST'])
def device_activation():
    # Log raw request body (expected XML)
    raw_body = request.data
    app.logger.info(f"DeviceActivation - Raw Request Body: {raw_body.decode('utf-8')}")

    # Log User-Agent header
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DeviceActivation - User-Agent: {user_agent}")

    # Prepare XML response
    xml_response = "<Response><Status>Success</Status></Response>"
    response = make_response(xml_response)
    response.headers['Content-Type'] = 'application/xml'
    
    return response

@app.route('/deviceservices/drmHandshake', methods=['POST'])
def drm_handshake():
    # Log raw request body (expected XML)
    raw_body = request.data
    app.logger.info(f"DRMHandshake - Raw Request Body: {raw_body.decode('utf-8')}") # Using app.logger

    # Log User-Agent header
    user_agent = request.headers.get('User-Agent')
    app.logger.info(f"DRMHandshake - User-Agent: {user_agent}") # Using app.logger

    # Prepare XML response
    xml_response_drm = "<DRMHandshakeResponse><Status>Success</Status></DRMHandshakeResponse>"
    response = make_response(xml_response_drm)
    response.headers['Content-Type'] = 'application/xml'
    
    return response

if __name__ == '__main__':
    # The logger is already configured above when 'app' is instantiated.
    app.run(debug=False, port=5001, ssl_context=('cert.pem', 'key.pem'))
