<?php
// This script (`lib_deviceactivation.php`) acts as a server-side HTTP endpoint.
// It is designed to be used with `ideviceactivation.exe -s <URL_to_this_script>`.
// It handles various stages of the iDevice activation process by receiving POST requests
// (typically containing XML/plist data) from `ideviceactivation.exe`.
// It sends back appropriate XML/plist or HTML responses to guide the activation.
// The logic is based on the behavior of typical Apple activation servers.

/*
Error Handling:
Robust error logging is implemented. Ensure the web server has write permissions
for the PHP error log. All error messages from this script are prefixed with its basename.
*/

// Ensure this script is accessed via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    error_log(basename(__FILE__) . " - Error: Only POST requests are allowed. Method: " . $_SERVER['REQUEST_METHOD']);
    echo "Only POST requests are allowed.";
    exit;
}

// Retrieve and log headers
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
error_log(basename(__FILE__) . " - User-Agent: " . $userAgent);

$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'Unknown';
error_log(basename(__FILE__) . " - Content-Type: " . $contentType);

// Get raw POST data
$rawPostData = file_get_contents('php://input');
if ($rawPostData === false) {
    http_response_code(400); // Bad Request
    error_log(basename(__FILE__) . " - Error: Could not read raw POST data.");
    echo "Error reading POST data.";
    exit;
}
error_log(basename(__FILE__) . " - Received raw POST data, length: " . strlen($rawPostData));

// Initialize variables for storing extracted data (similar to deviceactivation.php)
$activationInfoXMLString = null; // Will hold the primary XML content from POST if not form-urlencoded
$innerXMLString = null;
$innerXMLObject = null;
$isCredentialsSubmission = false; // Flag for form-urlencoded submissions

// Further processing will be added in subsequent steps...

/**
 * Handles the "Step 3" credentials submission.
 * This typically occurs when the device sends form-urlencoded data with login credentials.
 * It responds with an HTML page containing a plist for the device to proceed.
 */
function handle_credentials_submission() {
    error_log(basename(__FILE__) . " - Credentials submission detected (handle_credentials_submission).");

    if (isset($_POST['login'])) {
        error_log(basename(__FILE__) . " - Login parameter found in POST: " . $_POST['login']);
    } else {
        error_log(basename(__FILE__) . " - Login parameter NOT found in POST for credentials submission.");
    }

    header('Content-Type: text/html');
    echo '<!DOCTYPE html>';
    echo '<html><head><title>iPhone Activation Step 3</title></head><body><script id="protocol" type="text/x-apple-plist">';
    echo '<plist version="1.0"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>';
    echo '</script></body></html>';
    exit;
}

// Check Content-Type to determine how to process the POST data
if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
    $isCredentialsSubmission = true;
    // $_POST will be populated, no need to parse $rawPostData further for this type.
    error_log(basename(__FILE__) . " - Content-Type is form-urlencoded. Setting isCredentialsSubmission to true.");
} elseif (!empty($rawPostData)) {
    // For other content types (e.g., application/xml, text/xml, multipart/form-data containing XML part),
    // assume $rawPostData contains the primary XML payload.
    $activationInfoXMLString = $rawPostData;
    error_log(basename(__FILE__) . " - Assigning raw POST data to activationInfoXMLString, length: " . strlen($activationInfoXMLString));
} else {
    // No raw post data and not form-urlencoded, this might be an issue.
    error_log(basename(__FILE__) . " - Warning: Content-Type is not form-urlencoded and raw POST data is empty.");
    // Potentially send an error response or let it fall through to default handling.
}

// Handle Step 3: Credentials Submission Response (if form-urlencoded)
if ($isCredentialsSubmission) {
    handle_credentials_submission(); // This function will call exit()
}

// If not a credentials submission, proceed to parse $activationInfoXMLString...
// (Further parsing logic will be added in the next subtask)

/**
 * Parses the outer XML (plist containing ActivationInfoXML) and the inner ActivationInfoXML.
 *
 * @param string $xmlString The raw XML string received in the POST request.
 * @return SimpleXMLElement|null The parsed inner ActivationInfoXML object on success, or null on failure.
 */
function parse_activation_xml($xmlString) {
    error_log(basename(__FILE__) . " - Starting XML parsing (parse_activation_xml).");

    if (empty($xmlString)) {
        error_log(basename(__FILE__) . " - Error: XML string is empty in parse_activation_xml.");
        return null;
    }

    try {
        // Disable libxml errors from echoing to stdout/stderr, and fetch them with libxml_get_errors()
        libxml_use_internal_errors(true);
        $outerXML = new SimpleXMLElement($xmlString);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xmlErrors) {
            foreach ($xmlErrors as $error) {
                error_log(basename(__FILE__) . " - LibXMLError (Outer XML parsing): " . trim($error->message) . " on line " . $error->line);
            }
            error_log(basename(__FILE__) . " - Error parsing outer XML (plist). Full string: " . $xmlString);
            return null;
        }
    } catch (Exception $e) {
        error_log(basename(__FILE__) . " - Exception during outer XML (plist) parsing: " . $e->getMessage());
        error_log(basename(__FILE__) . " - Outer XML string: " . $xmlString);
        return null;
    }

    // Navigate to ActivationInfoXML data
    // The structure is typically plist -> dict -> key (ActivationInfoXML) -> data
    if (isset($outerXML->dict->key) && isset($outerXML->dict->data)) {
        $activationInfoNode = null;
        for ($i = 0; $i < count($outerXML->dict->key); $i++) {
            if ((string)$outerXML->dict->key[$i] === 'ActivationInfoXML') {
                $activationInfoNode = $outerXML->dict->data[$i];
                break;
            }
        }

        if ($activationInfoNode !== null) {
            $base64EncodedInnerXML = (string)$activationInfoNode;
            if (empty($base64EncodedInnerXML)) {
                error_log(basename(__FILE__) . " - Error: ActivationInfoXML data node is empty.");
                return null;
            }

            $innerXMLString = base64_decode($base64EncodedInnerXML, true); // Use strict mode
            if ($innerXMLString === false) {
                error_log(basename(__FILE__) . " - Error: Failed to base64 decode ActivationInfoXML. Original data (first 100 chars): " . substr($base64EncodedInnerXML, 0, 100));
                return null;
            }

            if (empty($innerXMLString)) {
                error_log(basename(__FILE__) . " - Error: Decoded inner XML string is empty.");
                return null;
            }

            error_log(basename(__FILE__) . " - Successfully decoded inner XML, length: " . strlen($innerXMLString));
            // error_log(basename(__FILE__) . " - Inner XML (first 200 chars): " . substr($innerXMLString, 0, 200));


            try {
                libxml_use_internal_errors(true);
                $innerXMLObject = new SimpleXMLElement($innerXMLString);
                $xmlErrorsInner = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors(false);

                if ($xmlErrorsInner) {
                    foreach ($xmlErrorsInner as $error) {
                        error_log(basename(__FILE__) . " - LibXMLError (Inner XML parsing): " . trim($error->message) . " on line " . $error->line);
                    }
                    error_log(basename(__FILE__) . " - Error parsing inner ActivationInfoXML. Decoded string (first 200 chars): " . substr($innerXMLString, 0, 200));
                    return null;
                }
                error_log(basename(__FILE__) . " - Successfully parsed inner ActivationInfoXML.");
                return $innerXMLObject;
            } catch (Exception $e) {
                error_log(basename(__FILE__) . " - Exception during inner ActivationInfoXML parsing: " . $e->getMessage());
                error_log(basename(__FILE__) . " - Inner XML string (first 200 chars): " . substr($innerXMLString, 0, 200));
                return null;
            }
        } else {
            error_log(basename(__FILE__) . " - Error: 'ActivationInfoXML' key not found in the plist structure.");
            // error_log(basename(__FILE__) . " - Outer XML structure: " . $outerXML->asXML());
            return null;
        }
    } else {
        error_log(basename(__FILE__) . " - Error: Expected plist dict->key or dict->data structure not found in outer XML.");
        // error_log(basename(__FILE__) . " - Outer XML structure: " . $outerXML->asXML());
        return null;
    }
}

// If not a credentials submission, and we have an XML string, try to parse it.
if (!empty($activationInfoXMLString)) {
    $innerXMLObject = parse_activation_xml($activationInfoXMLString);
    if ($innerXMLObject === null) {
        error_log(basename(__FILE__) . " - Failed to parse ActivationInfoXML or inner XML. Further processing based on innerXMLObject might be skipped.");
    } else {
        error_log(basename(__FILE__) . " - Successfully parsed inner XML.");
    }
} elseif (!$isCredentialsSubmission) {
    // This case means it wasn't credentials submission, but activationInfoXMLString is also empty.
    // This might happen if rawPostData was empty and it wasn't a form-urlencoded request.
    error_log(basename(__FILE__) . " - Warning: Not a credentials submission and activationInfoXMLString is empty. Cannot parse XML.");
}

// (Further handling for different activation states based on $innerXMLObject will be added next)

/**
 * Handles the 'Unactivated' state, typically Step 2 (Activation Lock HTML).
 *
 * @param SimpleXMLElement|null $innerXML The parsed inner ActivationInfoXML object.
 * @return bool True if handled (and script exited), false otherwise.
 */
function handle_unactivated_state($innerXML) {
    if (!$innerXML instanceof SimpleXMLElement) {
        error_log(basename(__FILE__) . " - handle_unactivated_state: Invalid innerXML provided.");
        return false;
    }

    // Path to ActivationState: dict -> key (ActivationRequestInfo) -> dict -> key (ActivationState) -> string
    // Need to find the 'ActivationRequestInfo' dict first.
    $activationRequestInfoDict = null;
    foreach ($innerXML->dict->key as $index => $keyNode) {
        if ((string)$keyNode === 'ActivationRequestInfo') {
            $activationRequestInfoDict = $innerXML->dict->dict[$index]; // Assuming key and dict are paired
            break;
        }
    }

    if ($activationRequestInfoDict instanceof SimpleXMLElement) {
        $activationState = null;
        foreach ($activationRequestInfoDict->key as $index => $keyNode) {
            if ((string)$keyNode === 'ActivationState') {
                $activationState = (string)$activationRequestInfoDict->string[$index]; // Assuming key and string are paired
                break;
            }
        }

        if ($activationState === 'Unactivated') {
            error_log(basename(__FILE__) . " - Step 2 (Activation Lock HTML) scenario detected (handle_unactivated_state).");
            header('Content-Type: text/html');
            echo '<!DOCTYPE html><html><head><title>iPhone Activation</title></head><body>Activation Lock</body></html>';
            exit;
        }
    }
    return false;
}

/**
 * Handles the 'Activated' state without ActivationRequestInfo, typically Step 5 (Final XML).
 *
 * @param SimpleXMLElement|null $innerXML The parsed inner ActivationInfoXML object.
 * @return bool True if handled (and script exited), false otherwise.
 */
function handle_activated_state($innerXML) {
    if (!$innerXML instanceof SimpleXMLElement) {
        error_log(basename(__FILE__) . " - handle_activated_state: Invalid innerXML provided.");
        return false;
    }

    // Check for ActivationState directly under the root dict.
    $activationState = null;
    $activationRequestInfoFound = false;

    if (isset($innerXML->dict->key)) {
        for ($i = 0; $i < count($innerXML->dict->key); $i++) {
            $keyName = (string)$innerXML->dict->key[$i];
            if ($keyName === 'ActivationState') {
                // Assuming the value is the next sibling string node or similar structure
                // This depends heavily on the exact structure of $innerXML when ActivationState is at root level
                // For now, let's assume it's paired with a <string> tag as the next sibling of <key>ActivationState</key>
                // This might need adjustment based on actual XML structure for this state.
                if(isset($innerXML->dict->string[$i])) { // This direct index pairing is an assumption
                    $activationState = (string)$innerXML->dict->string[$i];
                }
            } elseif ($keyName === 'ActivationRequestInfo') {
                $activationRequestInfoFound = true;
            }
        }
    }


    if ($activationState === 'Activated' && !$activationRequestInfoFound) {
        error_log(basename(__FILE__) . " - Step 5 (Final Activation Record XML) scenario detected (handle_activated_state).");
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>iphone-activation</key><dict><key>activation-ack</key><dict><key>ack-received</key><true/></dict></dict></dict></plist>';
        exit;
    }
    return false;
}

/**
 * Sends the default/fallback XML plist response.
 * This is similar to the response `ideviceactivate` might expect when not using the -s service mode.
 */
function send_default_fallback_response() {
    error_log(basename(__FILE__) . " - Proceeding with default XML plist response (ideviceactivate style) (send_default_fallback_response).");
    header('Content-Type: application/xml');
    // This is the large default/fallback XML from the original deviceactivation.php
    echo '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>ActivationRecord</key><dict><key>ActivateDevice</key><string>true</string><key>ActivationInfoComplete</key><string>true</string><key>FairPlayCertReq</key><dict><key>FPSAP</key><data>...</data><key>FairPlayVersion</key><integer>2</integer></dict><key>FairPlayKeyData</key><data>...</data><key>InternationalMobileEquipmentIdentity</key><string>0</string><key>InternationalMobileSubscriberIdentity</key><string>0</string><key>PhoneNumber</key><string>0</string><key>ProductType</key><string>0</string><key>SerialNumber</key><string>0</string><key>SIMStatus</key><string>kCTSIMSupportSIMStatusNotInserted</string><key>UniqueDeviceID</key><string>0</string><key>WildcardTicket</key><string>0</string><key>unbrick</key><true/></dict><key>AccountToken</key><string>0</string><key>AccountTokenCertificate</key><data>...</data><key>AccountTokenSignature</key><data>...</data></dict></plist>';
    exit;
}


// Attempt to handle specific states if innerXMLObject was parsed
if ($innerXMLObject instanceof SimpleXMLElement) {
    if (handle_unactivated_state($innerXMLObject)) {
        // handle_unactivated_state will exit if it matches
    } elseif (handle_activated_state($innerXMLObject)) {
        // handle_activated_state will exit if it matches
    }
    // If neither of the specific handlers matched and exited,
    // it might fall through to the default response, or it might mean
    // $innerXMLObject was present but didn't match specific known final states.
    // The original script implies the default response is the ultimate fallback
    // if $innerXMLObject parsing failed OR if it didn't match specific states.
    error_log(basename(__FILE__) . " - Inner XML object was present but did not match Unactivated or Activated final states. Proceeding to default response.");
} else if (empty($activationInfoXMLString) && !$isCredentialsSubmission) {
    // This condition means no initial XML was provided, and it wasn't credentials.
    // This is a scenario where the original ideviceactivate (non -s mode) might connect.
    error_log(basename(__FILE__) . " - No ActivationInfoXML provided and not a credentials submission. This might be a direct request for default activation files.");
} else if (!empty($activationInfoXMLString) && $innerXMLObject === null) {
    // This means parsing was attempted but failed.
    error_log(basename(__FILE__) . " - ActivationInfoXML was provided but parsing failed. Proceeding to default response.");
}


// If the script has not exited by now, send the default/fallback response.
send_default_fallback_response();

/*
Conceptual Testing for this HTTP Endpoint:
------------------------------------------
Since this script (`lib_deviceactivation.php`) acts as an HTTP endpoint, testing involves
simulating the requests that `ideviceactivation.exe -s` would send.

1.  **Simulate POST Requests:**
    *   Use tools like `curl`, Postman, or custom scripts to send HTTP POST requests to this script.
    *   The body of the POST request should contain XML/plist data that mimics the different
      payloads sent by `ideviceactivation.exe` at various stages. (Refer to the `albert.apple.com`
      sample data in the repository for examples of these payloads if available).

2.  **Verify Responses:**
    *   **Content-Type:** Check that the `Content-Type` header in the response is correct
      (e.g., `text/html`, `application/xml`).
    *   **Response Body:** Verify that the XML/plist or HTML content of the response matches
      what's expected for the given request payload (e.g., correct plist for credential step,
      Activation Lock HTML for unactivated state, final activation record for activated state,
      or the default fallback plist).
    *   **HTTP Status Codes:** While this script primarily uses `http_response_code(405)` for
      wrong method and `http_response_code(400)` for POST read errors, most valid interactions
      will result in a 200 OK with a specific body.

3.  **Test Scenarios:**
    *   **Credential Submission:** POST with `Content-Type: application/x-www-form-urlencoded`
      and `login`/`password`/`activation-info-base64` data. Expect the Step 3 HTML/plist response.
    *   **Unactivated State:** POST XML data that, when parsed, shows `ActivationState` as `Unactivated`.
      Expect the "Activation Lock" HTML page.
    *   **Activated State:** POST XML data that shows `ActivationState` as `Activated` (and no
      `ActivationRequestInfo`). Expect the final "iphone-activation" XML record.
    *   **Default/Fallback:** POST minimal or unexpected XML data (or even an empty request if that's
      a scenario `ideviceactivation.exe` might present for initial handshake/default files).
      Expect the default `ActivationRecord` plist.
    *   **Malformed XML/Data:** Send invalid XML to test error handling in `parse_activation_xml()`
      (though it should still fall back to the default response).

4.  **Logging:**
    *   Monitor the PHP error log during tests to see the trace of execution and any logged errors,
      which is helpful for debugging.

A dedicated testing tool or framework that can make HTTP requests and assert responses
would be beneficial for automating these tests.
*/
?>
