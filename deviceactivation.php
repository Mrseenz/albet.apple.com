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
<plist version="1.0">
<dict>
    <key>ActivationRecord</key>
    <dict>
        <key>unbrick</key><true/>
        <key>AccountTokenCertificate</key>
        <data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
        <key>DeviceCertificate</key>
        <data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQW0yWEJVL3hoQ3lGZGpBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZVekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1VnYVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElFTkJNQjRYRFRJMU1EVXhNVEV5Ck1UQTBPRm9YRFRJNE1EVXhNVEV5TVRBME9Gb3dnWU14TFRBckJnTlZCQU1XSkRJMU5UazVOemN4TFRBMk9UTXQKTkRCRFJDMUJSREk1TFVJelJqWTVOa1U0TWtJeU1qRUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0JqUUF3Z1lrQ2dZRUF3ZjMzcWQwdUdLa1kKWEo4SzJCTm1ML0xxYWsrdDR3cTVscXM4RndBV090ZnpyZUgvbzRCLzdndWdqTFhVazI5aUcxUUt1bmlTZENINgoxdUEybVNNZDZQUm4wdm5pKzhUYjFmMS9GWWx2cVlySkwxUVlENHZDNGV4QWRjakRMZG9xQzkrM1lVTG9iSzNhCnlUcG1tcWppZ1VDM2psK0hJSzAxckhHeHVjWVJzdVVDQXdFQUFhT0JsVENCa2pBZkJnTlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRFRmdRVXNZNDJjV0xLNituanowMnY0SEkxMVpqOQprUnN3REFZRFZSMFRBUUgvQkFJd0FEQU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVIQXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0liM0RRRUIKQlFVQUE0R0JBRFI5a2gzQ0trWDdScUNkZFAyYVlxYS9nNlpCYUVyaHBUdGU2Q3Mralh3UVN3MG9FOXJqMmNmVgpkS2JoM2I5RmlCdXpDZ3dXSGgwSnN2SjhJUGVJS0t5cFpwK0dFRzJHMi9EaXdwNkx6Wmg0TnN3MW5JOWhYZ21wCndObHJUbGFCVU16Q0U1dWJPWmluQy91VHV2S2pXN29QOWpDQ1BGNjZWcnhCYUFCVUtvc0YKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
        <key>RegulatoryInfo</key>
        <data>eyJtYW51ZmFjdHVyaW5nRGF0ZSI6IjIwMjMtMDktMjBUMDM6MjI6MTZaIiwiZWxhYmVsIjp7ImJpcyI6eyJyZWd1bGF0b3J5IjoiUi00MTA5NDg5NyJ9fSwiY291bnRyeU9mT3JpZ2luIjp7Im1hZGVJbiI6IkNITiJ9fQ==</data>
        <key>FairPlayKeyData</key>
        <data>LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQVNsYnZlZkJCekRYWnpvaHVqeHJUbGRNNTRLemVwZW50cFo1Yk53TjhXRE41OEE5dHBCb3FiWStPY1VTCnF4Rm96dGpzL0VNdHB3Ym9PVVp6Zzl6K0pkV2tkd3RMYTlWU1AyeFZXdXhuN3d3OXlyNDN6aUNkR0FPYXZhVXAKdHhaeU5OODF0bXJJNXdoanl1b3JvczhDdnE1SVlIc2t6MzF0cllaZG4rbnZ5aWdrYzlhOVZuV3FHVGtpRW5PVApZQ0lwL2RMWWs1Z1dsNVZ3UysvZEJjWHJZT3V0bVZLUzZLUExGNDJRMDE5c3NQdjUwd1ArU1FSVGkrMms0T0QrCkxHZTlzOHNNV1ZTMTQ2NW91UklEek9HbGs3eC9BdWFwN2o2MUNzdElUTVUwTkdWY1M5eEQ3MWtKeXh4T1NFZVAKcmpZeTlGOWo2dXBXZ25lK2JJQkVQZ1hTVVRRQ2JkYzl5bytiZUt3MkVMKytxSmVOdmw1Mk9IckdidmtHK29EVQpqQ1NyTnVlYXE3Q1BGWHdpd3JYWnAzQmVzNjhNZWRsRHFhRlJ4dGVPUGJ0QXlRTW1jRnEyVUp1T3pSa01oWUdnCnFLL1FlcXpzNWY5VTlRVnR1UmxwdzdiQWhITTIvYmhEWmtxdFJvbGNpY2l1QjBGY25TTy9mRmdsQ09GbGgxeVoKQ1FQeDNFdmFrajFwdWIrcGkvYlVPVnhjQno1T05pQkFhQXBQTit3L0wvZWI1eVZzckxXK291R2U1YnB2VEpUTQo2dUtlSHVac3pQMW52Vk1TNFpsZ1BRTm9FbHVrbjRzVk8yUytvakUwc25tYXFrRGtzbmYxWURUZUFBdytrNTZZCldOanhQNVFTa2gvL0NhUVlmSU1ZUUN5cnV2bHdPTWVNcFRqVkRxYi9GVWJlZ2NtL3N3bVJBb2NKa2RGbWVwanAKeHlMUk5UUDJZL213cFYwRkRLeUVxQlNnK3pPZG5IbzZsZWlvVnh3MUQ0cW1ZN0NEbnFVc2JDN1AzblVMcFhTdgpvN3AzWndtblU0QzZLYWpFb2s2K2xsYzEwUEFjdzlzL0VyVWs5M0xDMDhsQU02ZXdOdkxJVTBQUS9JRkdsU2JmCnh1QzcwRDBpVjArQ3hudFZlWnB0dXFxdFllbFVkVnZONXE2OEprelFIbjNSc0J2Q2pSL2Zjak1KNEZiRDJrQkwKQXZmM0x2VjdyQVZmUG1xRTczNFpZL2tGbXp6Wmt6ZFJwb0RRMXVCZVY4SVIwK3greWpPY1dEWVlpV3FDa2pSaApCNFVWZkVndU9weExMVEFQN2E2YzFEalBKREFyZ3ZFVmpJcEJIemVWNzVyL0pQNVFRR2NDTjQwZURGZGNja0k5CkhaeWZ3VUhFcjJ2dC9taitaS1JKUVhpRzUxZWh4RkNZZGp3UXBOOUxINk45Qm9acllhWndQVlBQQ3pZdW03SDUKWmJ2M0pkNmxQMmRsVktWSDNmZGNpczZJaE9WVklhYkJ3Y2xIRThJVjJFVjl5SkpSdi9qeTJXWkRGL1RodDRjVAp6VG9TVlh2bkJpMnp3d0x3WUFGUXVHZldrYWVrWUxZNzBoMWsxbWd0OGxRa1ZRT2RUWW5OZkptTytZSzlIVUtnCjlUQm5WMm83M2RZNDNIWXh0K0F6U1BJaWF1bDFEL1pWOThZZ0xOVkJtT0llbzhpVURlNTh0Z25UdVVsM3BvUFkKSTVTWnpTcWZmZG9PK0hRWWVyNzJVajRQQkd4cUp0T3g3THR0QkdnOEd3N2sybUE5VHMyR3FRZVJrVWs2MVFERAp5b1JVMGdwbFhBeTZmRDFFdUo1WUJraElTUEJQR1hhaWppQVI5dnROS2QwSXRBQkJsL3pvVTlaZHhwbk5LSE9GCktyQW93aVluTkQrSHRRcjFzZ1dzKzdzYW5JS2Z6WUFCOG9EaEVJdnFFRjgvR1YySUV2OHJFNWFUaEx1V1hGNUsKd1V0a0dYNkFvYTVFWVVVc2paVDJwekJHcjJZY0IrbUN0M01jV2hiS0dvc3BhWXRJCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==</data>
        <key>AccountToken</key>
        <data>ew0KCSJJbnRlcm5hdGlvbmFsTW9iaWxlRXF1aXBtZW50SWRlbnRpdHkiID0gIjM1MTY3MjkyNzAyODE0MyI7DQoJIkFjdGl2YXRpb25UaWNrZXQiID0gIk1JSUJrZ0lCQVRBS0JnZ3Foa2pPUFFRREF6R0JuNTgvQktjQTFUQ2ZRQVRoUUJRQW4wc1VZTWVxd3Q1ajZjTmRVNVplRmtVeWgrRm55ZGlmaDIwSE5XSW9NcFNKSnArSUFBYzFZaWd5YVRJem41YzlHQUFBQUFEdTd1N3U3dTd1N3hBQUFBRHU3dTd1N3U3dTc1K1hQZ1FBQUFBQW41Yy9CQUVBQUFDZmwwQUVBUUFBQUorWFJnUUdBQUFBbjVkSEJBRUFBQUNmbDBnRUFBQUFBSitYU1FRQkFBQUFuNWRMQkFBQUFBQ2ZsMHdFQVFBQUFBUm5NR1VDTURmNUQyRU9yU2lyekg4elFxb3g3citJaDhmSWFaWWpGajdROGdaQ2h2bkxtVWdiWDR0N3N5L3NLRnQrcDZabmJRSXhBTHlYbFdOaDlIbmkrYlRrbUl6a2ZqR2h3MXhOWnVGQVRsRXBPUkpYU0pBQWlmenEzR01pcnVldU5hSjMzOU5yeHFOMk1CQUdCeXFHU000OUFnRUdCU3VCQkFBaUEySUFCQTRtVVdnUzg2Sm1yMndTYlYwUzhPWkRxbzRhTHFPNWp6bVgyQUdCaDlZSElseVJxaXRaRnZCOHl0dzJoQndSMkpqRi83c29yZk1qcHpDY2l1a3BCZW5CZWFpYUwxVFJFeWpMUjhPdUpFdFVIazhaa0RFMnozZW1TckdRZkVwSWhRPT0iOw0KCSJQaG9uZU51bWJlck5vdGlmaWNhdGlvblVSTCIgPSAiaHR0cHM6Ly9hbGJlcnQuYXBwbGUuY29tL2RldmljZXNlcnZpY2VzL3Bob25lSG9tZSI7DQoJIkludGVybmF0aW9uYWxNb2JpbGVTdWJzY3JpYmVySWRlbnRpdHkiID0gIjY1NTAxMzY3MTEyMjQ4NiI7DQoJIlByb2R1Y3RUeXBlIiA9ICJpUGhvbmUxMywyIjsNCgkiVW5pcXVlRGV2aWNlSUQiID0gIjAwMDA4MTAxLTAwMEU3MTRBM0VEMjAwMUUiOw0KCSJTZXJpYWxOdW1iZXIiID0gIkROUEY1NjFQMEYwTiI7DQoJIk1vYmlsZUVxdWlwbWVudElkZW50aWZpZXIiID0gIjM1MTY3MjkyNzAyODE0IjsNCgkiSW50ZXJuYXRpb25hbE1vYmlsZUVxdWlwbWVudElkZW50aXR5MiIgPSAiMzUxNjcyOTI3MjExNjU3IjsNCgkiUG9zdHBvbmVtZW50SW5mbyIgPSB7fTsNCgkiQWN0aXZhdGlvblJhbmRvbW5lc3MiID0gIjdENDU0NDBBLUY5RjYtNDAxQi1CODE4LUI2NzNERDAyNEYxNCI7DQoJIkFjdGl2aXR5VVJMIiA9ICJodHRwczovL2FsYmVydC5hcHBsZS5jb20vZGV2aWNlc2VydmljZXMvYWN0aXZpdHkiOw0KCSJJbnRlZ3JhdGVkQ2lyY3VpdENhcmRJZGVudGl0eSIgPSAiODkzNjcwMDAwMDAwMTEyMjQ4NjkiOw0KfQ</data>
        <key>AccountTokenSignature</key>
        <data>pcb+DzgSmehiis8bpb0dedu/lAFy25GXbm4CqAkmrAZgsOkgrw6CXnO0V2iVdjDaJtX8tWYcdVkkRRfHYt9M3CT5ijnoQZU9WpJgUqyfJ4M9h1oUijQaZkkdWqjUHVinJtjvOrxRSQYyCDn/a7qJAjAxOUY7jK7rcIuh7G5gRBo=</data>
        <key>UniqueDeviceCertificate</key>
        <data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURtekNDQTBHZ0F3SUJBZ0lHQVphL1FVVW1NQW9HQ0NxR1NNNDlCQU1DTUVVeEV6QVJCZ05WQkFnTUNrTmgKYkdsbWIzSnVhV0V4RXpBUkJnTlZCQW9NQ2tGd2NHeGxJRWx1WXk0eEdUQVhCZ05WQkFNTUVFWkVVa1JETFZWRApVbFF0VTFWQ1EwRXdIaGNOTWpVd05URXhNVEl3TURRM1doY05NalV3TlRFNE1USXhNRFEzV2pCdU1STXdFUVlEClZRUUlEQXBEWVd4cFptOXlibWxoTVJNd0VRWURWUVFLREFwQmNIQnNaU0JKYm1NdU1SNHdIQVlEVlFRTERCVjEKWTNKMElFeGxZV1lnUTJWeWRHbG1hV05oZEdVeElqQWdCZ05WQkFNTUdUQXdNREE0TVRFd0xUQXdNRFEyT0RKQgpNRU5HTUVFd01VVXdXVEFUQmdjcWhrak9QUUlCQmdncWhrak9QUU1CQndOQ0FBVC9yUFRxRUFWV3l6a3hnTGtpCmNmR2FYQmRRYm45S1gwcTdaTXZ2Vi9XN3NMTzN3TW9hT1JqcHNHekpSRnBPbE15VDFiUEJKcmYxV0xlVm1WQlMKTmJwam80SUI4akNDQWU0d0RBWURWUjBUQVFIL0JBSXdBREFPQmdOVkhROEJBZjhFQkFNQ0JQQXdnZ0ZhQmdrcQpoa2lHOTJOa0NnRUVnZ0ZMTVlJQlIvK0VrcjJrUkFzd0NSWUVRazlTUkFJQkN2K0VtcUdTVUEwd0N4WUVRMGhKClVBSURBSUVRLzRTcWpaSkVFVEFQRmdSRlEwbEVBZ2NFYUNvTThLQWUvNGFUdGNKakd6QVpGZ1JpYldGakJCRTEKT0Rvek5qbzFNem8xWWpvNE56bzBOUCtHeTdYS2FSa3dGeFlFYVcxbGFRUVBNelUyTWpJNE16STVORGc1TWpZeAovNGJybGRKa0dEQVdGZ1J0Wldsa0JBNHpOVFl5TWpnek1qazBPRGt5TnYrSG04bmNiUlF3RWhZRWMzSnViUVFLClRGQXlVRVEwV0RSWlN2K0hxNUhTWkNNd0lSWUVkV1JwWkFRWk1EQXdNRGd4TVRBdE1EQXdORFk0TWtFd1EwWXcKUVRBeFJmK0h1N1hDWXhzd0dSWUVkMjFoWXdRUk5UZzZNelk2TlRNNk5XRTZabVU2WkdYL2g1dVYwbVE2TURnVwpCSE5sYVdRRU1EQTBNVFkwT0RsQ01qUXhSRGt3TURJek1UUXdNVEV6TkRVME16STFNRFl3UWpZek5UVkZOVEV5Ck9USkRRVU14UkRBeUJnb3Foa2lHOTJOa0JnRVBCQ1F4SXYrRTZvV2NVQW93Q0JZRVRVRk9VREVBLzRUNmlaUlEKQ2pBSUZnUlBRa3BRTVFBd0VnWUpLb1pJaHZkalpBb0NCQVV3QXdJQkFEQW9CZ2txaGtpRzkyTmtDQWNFR3pBWgp2NHA0Q0FRR01UZ3VNQzR4djRwN0NRUUhNakpCTXpNM01EQUtCZ2dxaGtqT1BRUURBZ05JQURCRkFpRUEzSG9XCkdVZ0NvQ2hXcitPcFl1TXNJM1RzZUFEVnhPZVNuSkJUNFY4L1hOa0NJREE5SHNlMG0zWWhhZnNycjdlK0I5QTQKY216YmhKSEVEYTBRaW5zSzVtUncKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLXVrTXRIOVJkU1F2SHpCeDdGaUJHcjcvS2NtbHhYL1h3b1dlV25XYjZJUk09Ci0tLS0tQkVHSU4gQ0VSVElGSUNBVEUtLS0tLQpNSUlDRnpDQ0FaeWdBd0lCQWdJSU9jVXFROElDL2hzd0NnWUlLb1pJemowRUF3SXdRREVVTUJJR0ExVUVBd3dMClUwVlFJRkp2YjNRZ1EwRXhFekFSQmdOVkJBb01Da0Z3Y0d4bElFbHVZeTR4RXpBUkJnTlZCQWdNQ2tOaGJHbG0KYjNKdWFXRXdIaGNOTVRZd05ESTFNak0wTlRRM1doY05Namt3TmpJME1qRTBNekkwV2pCRk1STXdFUVlEVlFRSQpEQXBEWVd4cFptOXlibWxoTVJNd0VRWURWUVFLREFwQmNIQnNaU0JKYm1NdU1Sa3dGd1lEVlFRRERCQkdSRkpFClF5MVZRMUpVTFZOVlFrTkJNRmt3RXdZSEtvWkl6ajBDQVFZSUtvWkl6ajBEQVFjRFFnQUVhRGMyTy9NcnVZdlAKVlBhVWJLUjdSUnpuNjZCMTQvOEtvVU1zRURiN25Ia0dFTVg2ZUMrMGdTdEdIZTRIWU1yTHlXY2FwMXRERlltRQpEeWtHUTN1TTJhTjdNSGt3SFFZRFZSME9CQllFRkxTcU9rT3RHK1YremdvTU9CcTEwaG5MbFRXek1BOEdBMVVkCkV3RUIvd1FGTUFNQkFmOHdId1lEVlIwakJCZ3dGb0FVV08vV3ZzV0NzRlROR0thRXJhTDJlM3M2Zjg4d0RnWUQKVlIwUEFRSC9CQVFEQWdFR01CWUdDU3FHU0liM1kyUUdMQUVCL3dRR0ZnUjFZM0owTUFvR0NDcUdTTTQ5QkFNQwpBMmtBTUdZQ01RRGY1ek5paUtOL0pxbXMxdyszQ0RZa0VTT1BpZUpNcEVrTGU5YTBValdYRUJETDBWRXNxL0NkCkUzYUtYa2M2UjEwQ01RRFM0TWlXaXltWStSeGt2eS9oaWNERFFxSS9CTCtOM0xIcXpKWlV1dzJTeDBhZkRYN0IKNkx5S2src0xxNHVya01ZPQotLS0tLUVORCBDRVJUSUZJQ0FURS0tLS0t</data>
	</dict>
</dict>
</plist>';

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
