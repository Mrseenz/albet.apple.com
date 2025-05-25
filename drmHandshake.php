<?php

// 1. Check if the REQUEST_METHOD is 'POST'
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    error_log("DRMHandshake - Error: Only POST requests are allowed. Received: " . $_SERVER['REQUEST_METHOD']);
    echo "Only POST requests are allowed.";
    exit;
}

// Log User-Agent
error_log("DRMHandshake - User-Agent: " . $_SERVER['HTTP_USER_AGENT']);

// 2. Retrieve the raw POST data
$rawPostData = file_get_contents('php://input');
if ($rawPostData === false || empty($rawPostData)) {
    http_response_code(400); // Bad Request
    error_log("DRMHandshake - Error: Failed to retrieve raw POST data or data is empty.");
    echo "Error: No data received.";
    exit;
}

// 3. Attempt to parse this raw data into a SimpleXMLElement object
$xmlObject = null;
try {
    $xmlObject = new SimpleXMLElement($rawPostData);
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    error_log("DRMHandshake - XML ParseError: " . $e->getMessage());
    echo "Error: Could not parse XML data.";
    exit;
}

error_log("DRMHandshake - Successfully parsed input XML.");

// 4. Initialize variables to store extracted data
$uniqueDeviceID = null;
$drmRequestData = null;
$collectionBlobData = null;
$handshakeMessageData = null;

// 5. Extract data from the parsed XML
if ($xmlObject && isset($xmlObject->dict->key)) {
    $keys = [];
    $values = [];

    // First, collect all keys and their corresponding next sibling values (string or data)
    foreach ($xmlObject->dict->key as $index => $keyNode) {
        $keyName = (string)$keyNode;
        $nextSibling = $keyNode->xpath('following-sibling::*[1]');
        
        if (!empty($nextSibling)) {
            $valueNode = $nextSibling[0];
            if ($valueNode->getName() === 'string' || $valueNode->getName() === 'data') {
                 $keys[] = $keyName;
                 $values[] = (string)$valueNode;
            }
        }
    }

    // Now assign to variables based on found keys
    for ($i = 0; $i < count($keys); $i++) {
        $keyName = $keys[$i];
        $valueContent = $values[$i];

        switch ($keyName) {
            case 'UniqueDeviceID':
                $uniqueDeviceID = $valueContent;
                break;
            case 'DRMRequest':
                $drmRequestData = $valueContent; // This will be base64 encoded data
                break;
            case 'CollectionBlob':
                $collectionBlobData = $valueContent; // This will be base64 encoded data
                break;
            case 'HandshakeRequestMessage':
                $handshakeMessageData = $valueContent; // This will be base64 encoded data
                break;
        }
    }
} else {
    error_log("DRMHandshake - XML structure not as expected (missing dict or key elements).");
    // Depending on strictness, you might want to exit here or allow fallback logic
}

// Log extracted data (optional, for debugging)
error_log("DRMHandshake - UniqueDeviceID: " . ($uniqueDeviceID ? $uniqueDeviceID : 'Not found'));
error_log("DRMHandshake - DRMRequestData: " . ($drmRequestData ? 'Present (length ' . strlen($drmRequestData) . ')' : 'Not found'));
error_log("DRMHandshake - CollectionBlobData: " . ($collectionBlobData ? 'Present (length ' . strlen($collectionBlobData) . ')' : 'Not found'));
error_log("DRMHandshake - HandshakeMessageData: " . ($handshakeMessageData ? 'Present (length ' . strlen($handshakeMessageData) . ')' : 'Not found'));

// Initialize $xmlResponseString to hold the XML Plist string
$xmlResponseString = '';

// 6. Add PHP comments as placeholders for the next stages

// Define DRM Response Constants
define('SERVER_KP_TYPE1', "A05R4OoqHhIV/gKxjX8CMU5lCPwJzgibztKpyvjM7n/k0/h48wWqrG74RgGXz9nQN6SsLYf1c+0HQsbyq1u3ecIXY55IFU=");
define('FDR_BLOB_TYPE1', "AAAABQAAABhDb2xsZWN0aW9uQmxvYjEAAABRAAAAE0ludGVncml0eVZhbHVlMQAAAAEAAAAETWVkaWFQcm90b2NvbAAAAAEAAAAFS2V5VHlwZQAAAAEAAABVQ29udGVudEtleUR1cmF0aW9uAAAAAQAAAFdDb250ZW50S2V5VHJhbnNmZXJLZXlQdWJsaWNLZXkAAAABAAAAU0NvbnRlbnRLZXlUcmFuc2ZlcktleURhdGEAAAABAAAAFlNlc3Npb25LZXlDcnlwdG9rZXkAAAABAAAAFVNlc3Npb25LZXlFbmNyeXB0ZWQAAAABAAAAEkhhbmRzaGFrZU1lc3NhZ2UAAAAB");
define('SU_INFO_TYPE1', "RkNvbnN0YW50cyBTVUlORk8gUExBQ0VIT0xERVI=");
define('HANDSHAKE_RESPONSE_MESSAGE_TYPE1', "SGFuZHNoYWtlUmVzcG9uc2VNZXNzYWdlIFR5cGUgMSBQTEFDRUhPTERFUg==");
define('SERVER_KP_STEP4', "A1DSE5XkG7U9L1OBCe57jPzYx8Psw4Y8DQjGw9HjP7r6e9bS2bY9cZ7gHjK5dFvGhJkLpPqRsUvWxYzAbCdEfGhIjKlM=");
define('FDR_BLOB_STEP4', "AAAABQAAABhDb2xsZWN0aW9uQmxvYjQAAABRAAAAE0ludGVncml0eVZhbHVlNAAAAAEAAAAETWVkaWFQcm90b2NvbAAAAAEAAAAFS2V5VHlwZQAAAAEAAABVQ29udGVudEtleUR1cmF0aW9uAAAAAQAAAFdDb250ZW50S2V5VHJhbnNmZXJLZXlQdWJsaWNLZXkAAAABAAAAU0NvbnRlbnRLZXlUcmFuc2ZlcktleURhdGEAAAABAAAAFlNlc3Npb25LZXlDcnlwdG9rZXkAAAABAAAAFVNlc3Npb25LZXlFbmNyeXB0ZWQAAAABAAAAEkhhbmRzaGFrZU1lc3NhZ2UAAAAB");
define('SU_INFO_STEP4', "RkNvbnN0YW50cyBTdGVwIDQgU1VJTlJPIGZvciBEUk1SZXF1ZXN0");
define('HANDSHAKE_RESPONSE_MESSAGE_STEP4', "SGFuZHNoYWtlUmVzcG9uc2VNZXNzYWdlIFN0ZXAgNCBQTEFDRUhPTERFUg==");

/**
 * Converts an XML string into a JSON string representing an object/array of its characters' ASCII values.
 * Keys are stringified numerical indices.
 *
 * @param string $xmlString The input XML string.
 * @return string JSON string of an object/array of ASCII values.
 */
function convertXmlStringToAsciiJson(string $xmlString): string {
    $asciiArray = [];
    foreach (str_split($xmlString) as $index => $char) {
        $asciiArray[(string)$index] = ord($char);
    }
    return json_encode($asciiArray); // The user's example used JSON_PRETTY_PRINT,
                                     // but for device communication, compact is usually preferred.
                                     // Let's stick to compact for now unless specified otherwise.
}

// Type 1 DRM Handshake Logic (CollectionBlob & HandshakeRequestMessage)
if (!empty($collectionBlobData) && !empty($handshakeMessageData)) {
    error_log("DRMHandshake - Type 1 request (CollectionBlob & HandshakeMessage) detected.");
    $xmlResponseString = '<?xml version="1.0" encoding="UTF-8"?>' .
                         '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' .
                         '<plist version="1.0"><dict>' .
                         '    <key>serverKP</key><data>' . SERVER_KP_TYPE1 . '</data>' .
                         '    <key>FDRBlob</key><data>' . FDR_BLOB_TYPE1 . '</data>' .
                         '    <key>SUInfo</key><data>' . SU_INFO_TYPE1 . '</data>' .
                         '    <key>HandshakeResponseMessage</key><data>' . HANDSHAKE_RESPONSE_MESSAGE_TYPE1 . '</data>' .
                         '</dict></plist>';
    error_log("DRMHandshake - Internal XML for Type 1 response generated. Converting to JSON of ASCII codes.");
}

// Type 2 DRM Handshake Logic (DRMRequest)
elseif (!empty($drmRequestData)) {
    error_log("DRMHandshake - Type 2 request (DRMRequest) detected.");
    $xmlResponseString = '<?xml version="1.0" encoding="UTF-8"?>' .
                         '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' .
                         '<plist version="1.0"><dict>' .
                         '    <key>serverKP</key><data>' . SERVER_KP_STEP4 . '</data>' .
                         '    <key>FDRBlob</key><data>' . FDR_BLOB_STEP4 . '</data>' .
                         '    <key>SUInfo</key><data>' . SU_INFO_STEP4 . '</data>' .
                         '    <key>HandshakeResponseMessage</key><data>' . HANDSHAKE_RESPONSE_MESSAGE_STEP4 . '</data>' .
                         '</dict></plist>';
    error_log("DRMHandshake - Internal XML for Type 2 response generated. Converting to JSON of ASCII codes.");
}

// Default/Fallback DRM Handshake Logic
// This executes if neither Type 1 nor Type 2 conditions were met.
else {
    error_log("DRMHandshake - Unknown request type or missing key fields. Generating default Type 1 XML internally.");
    $xmlResponseString = '<?xml version="1.0" encoding="UTF-8"?>' .
                         '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' .
                         '<plist version="1.0"><dict>' .
                         '    <key>serverKP</key><data>' . SERVER_KP_TYPE1 . '</data>' .
                         '    <key>FDRBlob</key><data>' . FDR_BLOB_TYPE1 . '</data>' .
                         '    <key>SUInfo</key><data>' . SU_INFO_TYPE1 . '</data>' .
                         '    <key>HandshakeResponseMessage</key><data>' . HANDSHAKE_RESPONSE_MESSAGE_TYPE1 . '</data>' .
                         '</dict></plist>';
    error_log("DRMHandshake - Internal XML for Default (Type 1 structure) response generated. Converting to JSON of ASCII codes.");
}

// Convert the generated XML string to a JSON array of ASCII codes
$finalJsonResponse = convertXmlStringToAsciiJson($xmlResponseString);

// Set the Content-Type header for JSON
header('Content-Type: application/json');

// Echo the final JSON response (array of ASCII codes)
error_log("DRMHandshake - Sending JSON response (array of XML ASCII codes).");
echo $finalJsonResponse;

// Terminate script execution
exit;

// For now, no specific output beyond errors.
// If we reach here, it means parsing was successful.
// echo "DRM Handshake data received and parsed."; // Example positive output for testing

?>
