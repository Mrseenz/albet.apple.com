<?php

// Check if the REQUEST_METHOD is 'POST'
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "Only POST requests are allowed.";
    exit;
}

// Retrieve and store the User-Agent header
$userAgent = $_SERVER['HTTP_USER_AGENT'];
error_log("DeviceActivation - User-Agent: " . $userAgent);

// Initialize variables for storing extracted data
$login = null;
$password = null;
$activationInfoBase64 = null;
$activationInfoXMLString = null;
$innerXMLString = null;
$innerXMLObject = null;
$isCredentialsSubmission = false;

// Check the Content-Type header
if (isset($_SERVER['CONTENT_TYPE'])) {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === 0) {
        // Retrieve login, password, and activation-info-base64 from $_POST
        if (isset($_POST['login'])) {
            $login = $_POST['login'];
        }
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
        }
        if (isset($_POST['activation-info-base64'])) {
            $activationInfoBase64 = $_POST['activation-info-base64'];
        }
        $isCredentialsSubmission = true;
    } else {
        // Assuming multipart/form-data or similar for other activation steps
        // Retrieve the activation-info field from $_POST
        if (isset($_POST['activation-info'])) {
            $activationInfoXMLString = $_POST['activation-info'];
            error_log("DeviceActivation - Received activation-info (multipart), length: " . strlen($activationInfoXMLString));
        }
    }
}

// Step 3: Credentials Submission Response (if form-urlencoded)
if ($isCredentialsSubmission) {
    error_log("DeviceActivation - Step 3 (Credentials Submission) request detected.");
    if (isset($_POST['login'])) {
        error_log("DeviceActivation - Login: " . $_POST['login']);
    }
    header('Content-Type: text/html');
    echo "<!DOCTYPE html>\n<html><head><title>iPhone Activation Step 3</title></head><body><script id=\"protocol\" type=\"text/x-apple-plist\">\n<plist version=\"1.0\"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>\n</script></body></html>";
    exit;
}

// If $activationInfoXMLString is not empty, parse it
if (!empty($activationInfoXMLString)) {
    try {
        $xml = new SimpleXMLElement($activationInfoXMLString);
        // Navigate the plist structure to find ActivationInfoXML
        // dict -> key (ActivationInfoXML) -> data
        $activationInfoDataNode = null;
        foreach ($xml->dict->key as $keyNode) {
            if ((string)$keyNode === 'ActivationInfoXML') {
                $activationInfoDataNode = $keyNode->following-sibling::data[0];
                break;
            }
        }

        if ($activationInfoDataNode) {
            $base64EncodedInnerXML = (string)$activationInfoDataNode;
            $innerXMLString = base64_decode($base64EncodedInnerXML);
            if ($innerXMLString === false) { // Check for base64_decode failure
                error_log("DeviceActivation - Base64 DecodeError for inner XML");
                $innerXMLString = null; // Ensure it's null if decoding failed
            } else if (!empty($innerXMLString)) {
                try {
                    $innerXMLObject = new SimpleXMLElement($innerXMLString);
                } catch (Exception $e_inner) {
                    error_log("DeviceActivation - Inner XML ParseError: " . $e_inner->getMessage());
                    $innerXMLObject = null; // Ensure it's null if parsing failed
                }
            } else {
                 // innerXMLString is empty after decode
                $innerXMLObject = null;
            }
        } else {
            error_log("DeviceActivation - 'ActivationInfoXML' data not found/empty in outer XML. Treating as original ideviceactivate-style request or error.");
        }
    } catch (Exception $e_outer) {
        error_log("DeviceActivation - Outer XML ParseError: " . $e_outer->getMessage());
    }
}

// Step 2: Activation Lock HTML (if Unactivated state)
if ($innerXMLObject instanceof SimpleXMLElement) {
    // Navigate to ActivationRequestInfo -> ActivationState
    $activationState = null;
    if (isset($innerXMLObject->dict->key)) {
        foreach ($innerXMLObject->dict->key as $keyNode) {
            if ((string)$keyNode === 'ActivationRequestInfo') {
                $activationRequestInfoDict = $keyNode->following-sibling::dict[0];
                if ($activationRequestInfoDict) {
                    foreach ($activationRequestInfoDict->key as $subKeyNode) {
                        if ((string)$subKeyNode === 'ActivationState') {
                            $activationState = (string)$subKeyNode->following-sibling::string[0];
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }
    }

    if ($activationState === 'Unactivated') {
        error_log("DeviceActivation - Step 2 (Activation Lock HTML) scenario detected.");
        header('Content-Type: text/html');
        echo "<!DOCTYPE html>\n<html><head><title>iPhone Activation</title></head><body>Activation Lock</body></html>";
        exit;
    }
}

// Step 5: Final Activation XML (if Activated state)
if ($innerXMLObject instanceof SimpleXMLElement) {
    $activationState = null;
    $activationRequestInfoFound = false;

    if (isset($innerXMLObject->dict->key)) {
        foreach ($innerXMLObject->dict->key as $keyNode) {
            if ((string)$keyNode === 'ActivationState') {
                $activationState = (string)$keyNode->following-sibling::string[0];
            }
            if ((string)$keyNode === 'ActivationRequestInfo') {
                $activationRequestInfoFound = true;
            }
        }
    }

    if ($activationState === 'Activated' && !$activationRequestInfoFound) {
        error_log("DeviceActivation - Step 5 (Final Activation Record XML) scenario detected.");
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
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
</plist>';
        exit;
    }
}

// Default/Fallback XML response
error_log("DeviceActivation - Proceeding with default XML plist response (ideviceactivate style).");
header('Content-Type: application/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict><key>device-activation</key><dict>
<key>activation-record</key><dict><key>FairPlayKeyData</key><data>PLACEHOLDER_FAIRPLAY_KEY_DATA</data>
<key>DevicePublicKey</key><string>PLACEHOLDER_DEVICE_PUBLIC_KEY</string>
<key>AccountToken</key><string>dummy-account-token</string>
<key>AccountTokenCertificate</key><data>PLACEHOLDER_ACCOUNT_TOKEN_CERTIFICATE_DATA</data></dict>
<key>activation-info-signature</key><data>PLACEHOLDER_ACTIVATION_INFO_SIGNATURE_DATA</data>
</dict></dict></plist>';

?>

<?php
/*
Testing Instructions:

The following are example `curl` commands for testing different scenarios of this script.
Replace `<your_server_url>` with the actual URL where `deviceactivation.php` is hosted.
For complex XML data, it's often easier to save the XML content to a file (e.g., `payload.xml`)
and then use the `-F "activation-info=@payload.xml"` option with curl.

1. Test Step 2: Activation Lock HTML (Unactivated state)

   This test simulates a device in the "Unactivated" state sending its activation information.
   The script should respond with an HTML page indicating "Activation Lock".

   Inner XML (to be base64 encoded):
   ----------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationRequestInfo</key>
       <dict>
           <key>ActivationState</key>
           <string>Unactivated</string>
       </dict>
   </dict>
   </plist>
   ----------------------------------
   Base64 encoded version of the above inner XML (no newlines):
   PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmF0aW9uU3RhdGU8L2tleT48c3RyaWNnPlVuYWN0aXZhdGVkPC9zdHJpbmc+PC9kaWN0PjwvZGljdD48L3BsaXN0Pg==

   Outer XML (`activation_info_xml_content`) to be sent in the POST request:
   (Save this content to a file, e.g., `step2_payload.xml`)
   ------------------------------------------------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmF0aW9uU3RhdGU8L2tleT48c3RyaW5nPlVuYWN0aXZhdGVkPC9zdHJpbmc+PC9kaWN0PjwvZGljdD48L3BsaXN0Pg==</data>
   </dict>
   </plist>
   ------------------------------------------------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@step2_payload.xml" <your_server_url>/deviceactivation.php

   Expected output:
   <!DOCTYPE html>
   <html><head><title>iPhone Activation</title></head><body>Activation Lock</body></html>


2. Test Step 3: Credentials Submission Response

   This test simulates submitting login credentials.
   The script should respond with an HTML page containing a specific plist script tag.

   Curl command:
   curl -X POST -H "Content-Type: application/x-www-form-urlencoded" -d "login=testuser&password=testpass&activation-info-base64=UNUSED_DATA" <your_server_url>/deviceactivation.php

   Expected output:
   <!DOCTYPE html>
   <html><head><title>iPhone Activation Step 3</title></head><body><script id="protocol" type="text/x-apple-plist">
   <plist version="1.0"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>
   </script></body></html>


3. Test Step 5: Final Activation XML (Activated state, no ActivationRequestInfo at top level)

   This test simulates a device that is "Activated" and its inner XML does not contain "ActivationRequestInfo" at the top level.
   The script should respond with the final activation success XML.

   Inner XML (to be base64 encoded):
   ----------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationState</key>
       <string>Activated</string>
   </dict>
   </plist>
   ----------------------------------
   Base64 encoded version of the above inner XML (no newlines):
   PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblN0YXRlPC9rZXk+PHN0cmluZz5BY3RpdmF0ZWQ8L3N0cmluZz48L2RpY3Q+PC9wbGlzdD4=

   Outer XML (`step5_payload.xml`):
   (Save this content to a file)
   ------------------------------------------------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblN0YXRlPC9rZXk+PHN0cmluZz5BY3RpdmF0ZWQ8L3N0cmluZz48L2RpY3Q+PC9wbGlzdD4=</data>
   </dict>
   </plist>
   ------------------------------------------------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@step5_payload.xml" <your_server_url>/deviceactivation.php

   Expected output (contains `ack-received` and `show-settings`):
   <?xml version="1.0" encoding="UTF-8"?>
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
   </plist>


4. Test Default/Fallback XML Response

   This test uses an inner XML that won't match conditions for Step 2 or Step 5 (e.g., an empty dictionary).
   The script should respond with the default ideviceactivate-style XML.

   Inner XML (empty dict, to be base64 encoded):
   ----------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
   </dict>
   </plist>
   ----------------------------------
   Base64 encoded version:
   PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjwvZGljdD48L3BsaXN0Pg==

   Outer XML (`default_payload.xml`):
   (Save this content to a file)
   ------------------------------------------------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjwvZGljdD48L3BsaXN0Pg==</data>
   </dict>
   </plist>
   ------------------------------------------------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@default_payload.xml" <your_server_url>/deviceactivation.php

   Expected output (the default plist):
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0"><dict><key>device-activation</key><dict>
   <key>activation-record</key><dict><key>FairPlayKeyData</key><data>PLACEHOLDER_FAIRPLAY_KEY_DATA</data>
   <key>DevicePublicKey</key><string>PLACEHOLDER_DEVICE_PUBLIC_KEY</string>
   <key>AccountToken</key><string>dummy-account-token</string>
   <key>AccountTokenCertificate</key><data>PLACEHOLDER_ACCOUNT_TOKEN_CERTIFICATE_DATA</data></dict>
   <key>activation-info-signature</key><data>PLACEHOLDER_ACTIVATION_INFO_SIGNATURE_DATA</data>
   </dict></dict></plist>

*/
?>
