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

// Database connection
$dbName = 'device_activation_credentials.sqlite';
$pdo = null; // Initialize $pdo to null

try {
    $pdo = new PDO('sqlite:' . $dbName);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: Log successful connection if desired
    // error_log("DeviceActivation - Successfully connected to database: " . $dbName);
} catch (PDOException $e) {
    error_log("DeviceActivation - Database connection failed: " . $e->getMessage());
    // Depending on desired behavior, you might want to output a generic error
    // and exit if the database is crucial for all operations.
    // For now, just logging the error. The script might proceed to parts
    // that don't strictly need DB if designed that way, or fail later.
    // Consider adding:
    // http_response_code(500); // Internal Server Error
    // echo "A server error occurred. Please try again later.";
    // exit;
    // For now, we will let it proceed and fail later if $pdo is used while null.
}

// Initialize variables for storing extracted data
$login = null;
$password = null;
$activationInfoBase64 = null;
$activationInfoXMLString = null;
$innerXMLString = null;
$innerXMLObject = null;
$isCredentialsSubmission = false;
$activation_error_message = null; // Initialize for potential error messages
$activationInfoXMLDataNodeText = null; // To store original base64 activation info

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

    $appleID = null;
    $password = null;
    $current_user_id = null;
    $credentials_valid = false; // Initialize credentials_valid
    $activation_error_message = null; // Initialize error message

    if (isset($_POST['login'])) {
        $appleID = $_POST['login'];
        error_log("DeviceActivation - Apple ID received: " . $appleID);
    }
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        // Do not log the raw password.
    }

    if ($pdo && $appleID && $password) { // Only proceed if DB is connected and we have credentials
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id, hashed_password FROM users WHERE apple_id = ?"); // Fetch hashed_password
            $stmt->execute([$appleID]);
            $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_row) {
                // User does not exist, register them
                error_log("DeviceActivation - Apple ID '$appleID' not found. Registering new user.");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("INSERT INTO users (apple_id, hashed_password) VALUES (?, ?)");
                if ($insertStmt->execute([$appleID, $hashedPassword])) {
                    $current_user_id = $pdo->lastInsertId();
                    error_log("DeviceActivation - Successfully registered new user '$appleID' with user_id: $current_user_id.");
                    $credentials_valid = true; // New user registration is implicitly valid
                } else {
                    error_log("DeviceActivation - Failed to register new user '$appleID'.");
                    $credentials_valid = false; // Registration failed
                }
            } else {
                // User exists, validate password
                $current_user_id = $user_row['user_id'];
                $stored_hashed_password = $user_row['hashed_password'];
                error_log("DeviceActivation - Apple ID '$appleID' found with user_id: $current_user_id. Validating password.");

                if (password_verify($password, $stored_hashed_password)) {
                    error_log("DeviceActivation - Password verification successful for user '$appleID'.");
                    $credentials_valid = true;
                } else {
                    error_log("DeviceActivation - Password verification failed for user '$appleID'.");
                    $credentials_valid = false;
                    $activation_error_message = "Invalid Apple ID or password.";
                }
            }

        } catch (PDOException $e) {
            error_log("DeviceActivation - Database error during user check/registration: " . $e->getMessage());
            // Potentially output a generic error and exit, depending on how critical this step is
            // For now, the script will proceed. $credentials_valid will be false.
        }
    } elseif (!$pdo) {
        error_log("DeviceActivation - Database connection not available. Cannot process credentials.");
        $credentials_valid = false; // Cannot validate
        // The script will proceed and eventually not send the Step 3 HTML due to $credentials_valid = false
        // and will likely hit the Activation Lock page logic (Step 7 with error message)
        $activation_error_message = "A server error occurred. Please try again later.";
        // No http_response_code(500) + exit here to allow fallback to Activation Lock page.
    } else { // Missing $appleID or $password
        error_log("DeviceActivation - Missing Apple ID or password in POST data for credentials submission.");
        $credentials_valid = false; // Cannot validate
        $activation_error_message = "Missing Apple ID or password.";
        // No http_response_code(400) + exit here to allow fallback to Activation Lock page.
    }

    // TODO: Implement Step 6 (Device Registration) if $credentials_valid is true.
    // TODO: Implement Step 7 (Conditional HTML response) based on $credentials_valid and $activation_error_message.

    // For now, the original Step 3 response is sent if credentials are valid.
    // This will be replaced by Step 7 logic.
    if ($credentials_valid) {
        // Step 6: Device Registration
        error_log("DeviceActivation - Credentials valid for user_id: $current_user_id. Proceeding to device registration.");

        $deviceDetailsXML = null;
        if (isset($_POST['activation-info-base64'])) {
            $activationInfoBase64 = $_POST['activation-info-base64'];
            $innerXMLStringFromCreds = base64_decode($activationInfoBase64);

            if ($innerXMLStringFromCreds !== false && !empty($innerXMLStringFromCreds)) {
                try {
                    $deviceDetailsXML = new SimpleXMLElement($innerXMLStringFromCreds);
                    error_log("DeviceActivation - Successfully parsed activation-info-base64 for device registration.");
                } catch (Exception $e) {
                    error_log("DeviceActivation - Error parsing XML from activation-info-base64: " . $e->getMessage());
                    // Device details cannot be extracted, but activation might still proceed without device registration.
                }
            } else {
                error_log("DeviceActivation - Failed to decode or empty activation-info-base64.");
            }
        } else {
            error_log("DeviceActivation - 'activation-info-base64' not found in POST data for device registration.");
        }

        $udid = null;
        $serialNumber = null;
        $productType = null;

        if ($deviceDetailsXML instanceof SimpleXMLElement) {
            // Extract UDID (UniqueID under DeviceID dictionary)
            // Example path: dict -> key (DeviceID) -> dict -> key (UniqueID) -> string
            if (isset($deviceDetailsXML->dict)) {
                foreach ($deviceDetailsXML->dict->key as $keyNode) {
                    if ((string)$keyNode === 'DeviceID') {
                        $deviceIDDict = $keyNode->following-sibling::dict[0];
                        if ($deviceIDDict) {
                            foreach ($deviceIDDict->key as $subKeyNode) {
                                if ((string)$subKeyNode === 'UniqueID') {
                                    $udid = (string)$subKeyNode->following-sibling::string[0];
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            if ($udid) {
                error_log("DeviceActivation - Extracted UDID: $udid");
            } else {
                error_log("DeviceActivation - UDID (UniqueID) not found in device details XML.");
            }

            // Extract SerialNumber & ProductType (under DeviceInfo dictionary)
            // Example path: dict -> key (DeviceInfo) -> dict -> key (SerialNumber/ProductType) -> string
            if (isset($deviceDetailsXML->dict)) {
                 foreach ($deviceDetailsXML->dict->key as $keyNode) {
                    if ((string)$keyNode === 'DeviceInfo') {
                        $deviceInfoDict = $keyNode->following-sibling::dict[0];
                        if ($deviceInfoDict) {
                            foreach ($deviceInfoDict->key as $subKeyNode) {
                                if ((string)$subKeyNode === 'SerialNumber') {
                                    $serialNumber = (string)$subKeyNode->following-sibling::string[0];
                                } elseif ((string)$subKeyNode === 'ProductType') {
                                    $productType = (string)$subKeyNode->following-sibling::string[0];
                                }
                            }
                            // Break outer loop once DeviceInfo is processed
                            if ($serialNumber !== null && $productType !== null) break; 
                        }
                    }
                }
            }
            if ($serialNumber) {
                error_log("DeviceActivation - Extracted SerialNumber: $serialNumber");
            } else {
                error_log("DeviceActivation - SerialNumber not found in device details XML.");
            }
            if ($productType) {
                error_log("DeviceActivation - Extracted ProductType: $productType");
            } else {
                error_log("DeviceActivation - ProductType not found in device details XML.");
            }
        }

        if ($pdo && $udid && $current_user_id) { // UDID and user_id are essential
            try {
                $stmt = $pdo->prepare("SELECT device_id FROM devices WHERE udid = ?");
                $stmt->execute([$udid]);
                $device_row = $stmt->fetch(PDO::FETCH_ASSOC);

                $activation_state_to_set = "Activated"; // Or a more specific state if available

                if ($device_row) {
                    // Device exists, update it
                    $device_id = $device_row['device_id'];
                    error_log("DeviceActivation - Device with UDID '$udid' (ID: $device_id) already exists. Updating details for user_id: $current_user_id.");
                    $updateStmt = $pdo->prepare("UPDATE devices SET user_id = ?, serial_number = ?, product_type = ?, activation_state = ?, last_seen_at = CURRENT_TIMESTAMP WHERE udid = ?");
                    if ($updateStmt->execute([$current_user_id, $serialNumber, $productType, $activation_state_to_set, $udid])) {
                        error_log("DeviceActivation - Successfully updated device UDID '$udid'.");
                    } else {
                        error_log("DeviceActivation - Failed to update device UDID '$udid'.");
                    }
                } else {
                    // Device does not exist, insert it
                    error_log("DeviceActivation - Registering new device with UDID '$udid' for user_id: $current_user_id.");
                    $insertStmt = $pdo->prepare("INSERT INTO devices (user_id, udid, serial_number, product_type, activation_state) VALUES (?, ?, ?, ?, ?)");
                    if ($insertStmt->execute([$current_user_id, $udid, $serialNumber, $productType, $activation_state_to_set])) {
                        error_log("DeviceActivation - Successfully registered new device UDID '$udid'.");
                    } else {
                        error_log("DeviceActivation - Failed to register new device UDID '$udid'.");
                    }
                }
            } catch (PDOException $e) {
                error_log("DeviceActivation - Database error during device registration/update for UDID '$udid': " . $e->getMessage());
            }
        } elseif (!$udid) {
            error_log("DeviceActivation - Skipping device registration/update because UDID could not be extracted.");
        } elseif (!$pdo) {
            error_log("DeviceActivation - Skipping device registration/update because database connection is not available.");
        }


        // Original Step 3 HTML response - this will eventually be replaced by Step 7 logic
        header('Content-Type: text/html');
        echo "<!DOCTYPE html>\n<html><head><title>iPhone Activation Step 3</title></head><body><script id=\"protocol\" type=\"text/x-apple-plist\">\n<plist version=\"1.0\"><dict><key>ActivationRecord</key><dict><key>unbrick</key><true/></dict></dict></plist>\n</script></body></html>";
        exit;
    } else {
        // If credentials are not valid, we should fall through to Step 7 (Activation Lock with error)
        // For now, this means it will fall through to Step 2 logic or default XML if not handled by Step 7.
        // The subtask for Step 7 will explicitly handle rendering Activation Lock with $activation_error_message.
        error_log("DeviceActivation - Credentials invalid or error during validation. Error: " . ($activation_error_message ? $activation_error_message : "No specific error message."));
    }
}
// IMPORTANT: The 'exit' for failed credential validation is removed.
// The script will now fall through to Step 2 / Step 7 logic if credentials are not valid.

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
            // Store this for the hidden field if we need to show the Activation Lock form
            if (!$isCredentialsSubmission) { // Only capture on initial device request
                 $activationInfoXMLDataNodeText = $base64EncodedInnerXML;
                 error_log("DeviceActivation - Captured base64 ActivationInfoXML for potential form resubmission.");
            }
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

    if ($activationState === 'Unactivated' || !empty($activation_error_message)) { // Also show if there was a previous error
        error_log("DeviceActivation - Step 2/7 (Activation Lock HTML with form) scenario detected. Error message: " . ($activation_error_message ? $activation_error_message : "None"));
        header('Content-Type: text/html; charset=utf-8');
        // If $activationInfoBase64 is set (from a failed credential submission), use it for the hidden field,
        // otherwise use $activationInfoXMLDataNodeText (from initial device POST)
        $hiddenFieldBase64Data = $activationInfoBase64 ? $activationInfoBase64 : $activationInfoXMLDataNodeText;

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>iPhone Activation</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
        .container { background-color: #fff; max-width: 400px; margin: 0 auto; padding: 30px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; margin-bottom: 20px; }
        .error { color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; border-radius: 4px; background-color: #ffebeb; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-bottom: 15px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        .info-text { color: #666; font-size: 0.9em; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Activation Lock</h1>
HTML;
        if (!empty($activation_error_message)) {
            echo '        <p class="error">' . htmlspecialchars($activation_error_message) . '</p>' . "\n";
        }
        echo <<<HTML
        <p class="info-text">This iPhone is linked to an Apple ID. Enter the Apple ID and password that were used to set up this iPhone.</p>
        <form method="post" action="deviceactivation.php">
            <div>
                <label for="appleid">Apple ID</label>
                <input type="text" id="appleid" name="login" value="" placeholder="example@icloud.com">
            </div>
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
            </div>
HTML;
        if (!empty($hiddenFieldBase64Data)) {
            echo '            <input type="hidden" name="activation-info-base64" value="' . htmlspecialchars($hiddenFieldBase64Data) . '">' . "\n";
        }
        echo <<<HTML
            <button type="submit">Continue</button>
        </form>
    </div>
</body>
</html>
HTML;
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
Testing Instructions for deviceactivation.php:

General Setup:
- Ensure `init_activation_db.php` has been run to create the 'device_activation_credentials.sqlite' database and its tables.
- Replace `<your_server_url>` with the actual URL where `deviceactivation.php` is hosted.
- For XML payloads sent via `multipart/form-data`, save the XML content to a file (e.g., `payload.xml`) and use `curl -F "activation-info=@payload.xml"`.
- For `application/x-www-form-urlencoded` POSTs, use `curl -X POST -d "key1=value1&key2=value2" <url>`.

Example Base64 for `activation-info-base64` (contains minimal device info and "Unactivated" state):
This can be used for testing credential submissions.
`PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmFwdGlvblN0YXRlPC9rZXk+PHN0cmluZz5VbmFjdGl2YXRlZDwvc3RyaW5nPiAgICA8IS0tIEFkZCBvdGhlciBrZXlzIGhlcmUgZm9yIGEgZnVsbCBkZXZpY2UgYWN0aXZhdGlvbiByZXF1ZXN0LCBlLmcuIERldmljZUlkLCBEZXZpY2VJbmZvIC0tPiAgICA8L2RpY3Q+PC9kaWN0PjwvcGxpc3Q+`

---

1. Test Initial Activation Lock Page Display (Step 2/7)

   This test simulates an initial request from a device in the "Unactivated" state.
   It should display the HTML form for Apple ID and password entry.

   Payload file (`initial_unactivated_payload.xml`):
   ------------------------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <!-- This contains the base64 encoded inner XML with ActivationState = Unactivated and device details -->
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmFwdGlvblN0YXRlPC9rZXk+PHN0cmluZz5VbmFjdGl2YXRlZDwvc3RyaW5nPiAgICA8IS0tIEFkZCBvdGhlciBrZXlzIGhlcmUgZm9yIGEgZnVsbCBkZXZpY2UgYWN0aXZhdGlvbiByZXF1ZXN0LCBlLmcuIERldmljZUlkLCBEZXZpY2VJbmZvIC0tPiAgICA8L2RpY3Q+PC9kaWN0PjwvcGxpc3Q+</data>
   </dict>
   </plist>
   ------------------------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@initial_unactivated_payload.xml" <your_server_url>/deviceactivation.php

   Expected outcome:
   - The HTML Activation Lock page is displayed.
   - The page includes a form with "login" and "password" fields.
   - A hidden field named "activation-info-base64" is present in the form, containing the base64 string from the <data> tag of the request.

---

2. Test Credential Submission - New User Registration & Success

   This test simulates submitting credentials for a new user.
   The user and device should be registered, and the Step 3 success HTML should be returned.

   Curl command (ensure the DB is empty or this Apple ID doesn't exist):
   curl -X POST -H "Content-Type: application/x-www-form-urlencoded" \
     -d "login=newuser@example.com" \
     -d "password=password123" \
     -d "activation-info-base64=PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmFwdGlvblN0YXRlPC9rZXk+PHN0cmluZz5VbmFjdGl2YXRlZDwvc3RyaW5nPiAgICA8IS0tIEFkZCBvdGhlciBrZXlzIGhlcmUgZm9yIGEgZnVsbCBkZXZpY2UgYWN0aXZhdGlvbiByZXF1ZXN0LCBlLmcuIERldmljZUlkLCBEZXZpY2VJbmZvIC0tPiAgICA8L2RpY3Q+PC9kaWN0PjwvcGxpc3Q+" \
     <your_server_url>/deviceactivation.php

   Expected outcome:
   - A new user "newuser@example.com" is created in the `users` table (password will be hashed).
   - If the `activation-info-base64` decodes to XML with device details (UDID, etc.), a new device is registered in the `devices` table, linked to the new user, with `activation_state` = "Activated".
   - The response is the Step 3 HTML (title "iPhone Activation Step 3", with the embedded "unbrick" plist script tag).

---

3. Test Credential Submission - Existing User, Failed Login

   This test simulates submitting incorrect credentials for an existing user.
   The Activation Lock page should be re-displayed with an error message.

   Prerequisite: User "newuser@example.com" exists from the previous test.

   Curl command:
   curl -X POST -H "Content-Type: application/x-www-form-urlencoded" \
     -d "login=newuser@example.com" \
     -d "password=wrongpassword" \
     -d "activation-info-base64=PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmFwdGlvblN0YXRlPC9rZXk+PHN0cmluZz5VbmFjdGl2YXRlZDwvc3RyaW5nPiAgICA8IS0tIEFkZCBvdGhlciBrZXlzIGhlcmUgZm9yIGEgZnVsbCBkZXZpY2UgYWN0aXZhdGlvbiByZXF1ZXN0LCBlLmcuIERldmljZUlkLCBEZXZpY2VJbmZvIC0tPiAgICA8L2RpY3Q+PC9kaWN0PjwvcGxpc3Q+" \
     <your_server_url>/deviceactivation.php

   Expected outcome:
   - The HTML Activation Lock page is displayed.
   - The page includes an error message, e.g., "Invalid Apple ID or password."
   - The hidden field "activation-info-base64" is present in the form, containing the same base64 string sent in the request.

---

4. Test Credential Submission - Existing User, Successful Login

   This test simulates submitting correct credentials for an existing user.
   The device info should be updated/re-confirmed, and the Step 3 success HTML returned.

   Prerequisite: User "newuser@example.com" with password "password123" exists.

   Curl command:
   curl -X POST -H "Content-Type: application/x-www-form-urlencoded" \
     -d "login=newuser@example.com" \
     -d "password=password123" \
     -d "activation-info-base64=PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblJlcXVlc3RJbmZvPC9rZXk+PGRpY3Q+PGtleT5BY3RpdmFwdGlvblN0YXRlPC9rZXk+PHN0cmluZz5VbmFjdGl2YXRlZDwvc3RyaW5nPiAgICA8IS0tIEFkZCBvdGhlciBrZXlzIGhlcmUgZm9yIGEgZnVsbCBkZXZpY2UgYWN0aXZhdGlvbiByZXF1ZXN0LCBlLmcuIERldmljZUlkLCBEZXZpY2VJbmZvIC0tPiAgICA8L2RpY3Q+PC9kaWN0PjwvcGxpc3Q+" \
     <your_server_url>/deviceactivation.php

   Expected outcome:
   - The device information (if any from `activation-info-base64`) is updated or re-confirmed in the `devices` table for "newuser@example.com".
   - The response is the Step 3 HTML (title "iPhone Activation Step 3", with the embedded "unbrick" plist script tag).

---

5. Test Final Activation XML (Step 5) - (Optional, if you want to ensure this path is still reachable)

   This test requires manual database setup to have a device associated with a user,
   and the request's inner XML must have `ActivationState` = "Activated" and NO `ActivationRequestInfo` key at the top level of the inner XML.
   This part of the script is less affected by recent DB changes but can be tested for completeness.

   Payload file (`step5_payload.xml`):
   ----------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <!-- Base64 of: <plist><dict><key>ActivationState</key><string>Activated</string></dict></plist> -->
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjxrZXk+QWN0aXZhdGlvblN0YXRlPC9rZXk+PHN0cmluZz5BY3RpdmF0ZWQ8L3N0cmluZz48L2RpY3Q+PC9wbGlzdD4=</data>
   </dict>
   </plist>
   ----------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@step5_payload.xml" <your_server_url>/deviceactivation.php

   Expected outcome:
   The XML response for successful activation (contains `iphone-activation -> ack-received -> true`).

---

6. Test Default/Fallback XML Response - (Optional)

   This tests the scenario where the request doesn't match any specific logic path.
   Payload file (`default_payload.xml`): (Inner XML is an empty dict)
   ----------------------------------
   <?xml version="1.0" encoding="UTF-8"?>
   <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
   <plist version="1.0">
   <dict>
       <key>ActivationInfoXML</key>
       <!-- Base64 of: <plist><dict></dict></plist> -->
       <data>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48IURPQ1RZUEUgcGxpc3QgUFVCTElDICItLy9BcHBsZS8vRFREIFBMSVNUIDEuMC8vRU4iICJodHRwOi8vd3d3LmFwcGxlLmNvbS9EVEQvUHJvcGVydHlMaXN0LTEuMC5kdGQiPjxwbGlzdCB2ZXJzaW9uPSIxLjAiPjxkaWN0PjwvZGljdD48L3BsaXN0Pg==</data>
   </dict>
   </plist>
   ----------------------------------

   Curl command:
   curl -X POST -H "Content-Type: multipart/form-data" -F "activation-info=@default_payload.xml" <your_server_url>/deviceactivation.php

   Expected outcome:
   The default ideviceactivate-style XML response.

*/
?>
