<?php
$activation= (array_key_exists('activation-info-base64', $_POST) 
			  ? base64_decode($_POST['activation-info-base64']) 
			  : array_key_exists('activation-info', $_POST) ? $_POST['activation-info'] : '');

if(!isset($activation) || empty($activation)) { exit('make sure device is connected'); }


$encodedrequest = new DOMDocument;
$encodedrequest->loadXML($activation);
$activationDecoded= base64_decode($encodedrequest->getElementsByTagName('data')->item(0)->nodeValue);

$decodedrequest = new DOMDocument;
$decodedrequest->loadXML($activationDecoded);
$nodes = $decodedrequest->getElementsByTagName('dict')->item(0)->getElementsByTagName('*');

for ($i = 0; $i < $nodes->length - 1; $i=$i+2)
{

	switch ($nodes->item($i)->nodeValue)
	{
		case "ActivationRandomness": $activationRandomness = $nodes->item($i + 1)->nodeValue; break;
		case "DeviceClass": $deviceClass = $nodes->item($i + 1)->nodeValue; break;
		case "SerialNumber": $serialNumber = $nodes->item($i + 1)->nodeValue; break;
		case "UniqueDeviceID": $uniqueDiviceID = $nodes->item($i + 1)->nodeValue; break;
		case "MobileEquipmentIdentifier": $meid = $nodes->item($i + 1)->nodeValue; break;
		case "InternationalMobileEquipmentIdentity": $imei = $nodes->item($i + 1)->nodeValue; break;
		case "ActivationState": $activationState = $nodes->item($i + 1)->nodeValue; break;
		case "ProductVersion": $productVersion = $nodes->item($i + 1)->nodeValue; break;
	}
}


$snLength = strlen($serialNumber);

if($snLength > 12){
	echo "Hmmm something isn't right don't ya think?";
	exit();
}
if($snLength < 11){
	echo "Hmmm something isn't right dont ya think?";
	exit();
}

$udidLength = strlen($uniqueDiviceID);
if($udidLength < 40){
	echo "Hmmm something isn't right don't ya think?";
	exit();
}
if($udidLength > 40){
	echo "Hmmm something isn't right don't ya think?";
	exit();
}


$devicefolder = $deviceClass.'/'.$serialNumber.'/';
if (!file_exists($deviceClass.'/')) mkdir($deviceClass.'/', 0777, true);
if (!file_exists($devicefolder))  mkdir($devicefolder, 0777, true);
$decodedrequest->save($devicefolder.'device-request-decoded.xml');

# -------------------------------------------- Sign account token -----------------------------------------

$accountToken2= 
	"\n\t".'"InternationalMobileEquipmentIdentity" = "'.$InternationalMobileEquipmentIdentity.'";'.
	"\n\t".'"ActivationTicket" = "MIIBkgIBATAKBggqhkjOPQQDAzGBn58/BKcA1TCfQAThQBQAn0sUYMeqwt5j6cNdU5ZeFkUyh+Fnydifh20HNWIoMpSJJp+IAAc1YigyaTIzn5c9GAAAAADu7u7u7u7u7xAAAADu7u7u7u7u75+XPgQAAAAAn5c/BAEAAACfl0AEAQAAAJ+XRgQGAAAAn5dHBAEAAACfl0gEAAAAAJ+XSQQBAAAAn5dLBAAAAACfl0wEAQAAAARnMGUCMDf5D2EOrSirzH8zQqox7r+Ih8fIaZYjFj7Q8gZChvnLmUgbX4t7sy/sKFt+p6ZnbQIxALyXlWNh9Hni+bTkmIzkfjGhw1xNZuFATlEpORJXSJAAifzq3GMirueuNaJ339NrxqN2MBAGByqGSM49AgEGBSuBBAAiA2IABA4mUWgS86Jmr2wSbV0S8OZDqo4aLqO5jzmX2AGBh9YHIlyRqitZFvB8ytw2hBwR2JjF/7sorfMjpzCciukpBenBeaiaL1TREyjLR8OuJEtUHk8ZkDE2z3emSrGQfEpIhQ==";
	"\n\t".'"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";'.
	"\n\t".'"InternationalMobileSubscriberIdentity" = "'.$InternationalMobileSubscriberIdentity.'";'.
	"\n\t".'"ProductType" = "'.$productType".'";'.
	"\n\t".'"UniqueDeviceID" = "'.$uniqueDiviceID.'";'.
	"\n\t".'"SerialNumber" = "'.$serialNumber.'";'.
	"\n\t".'"MobileEquipmentIdentifier" = "'.$MobileEquipmentIdentifier.'";'.
	"\n\t".'"InternationalMobileEquipmentIdentity2" = "'.$InternationalMobileEquipmentIdentity2.'";'.
	"\n\t".'"PostponementInfo" = "'.$PostponementInfo.'";'.
	"\n\t".'"ActivationRandomness" = "'.$activationRandomness.'";'.
	"\n\t".'"ActivityURL" = "https://albert.apple.com/deviceservices/activity.'";'.
	"\n\t".'"IntegratedCircuitCardIdentity" = "'.$IntegratedCircuitCardIdentity.'";'.
	"\n\t".'"FactoryActivated" = "True";'.
	"\n".
 '}';	
$accountToken= 
	"\n\t".'"InternationalMobileEquipmentIdentity" = "'.$InternationalMobileEquipmentIdentity.'";'.
	"\n\t".'"ActivationTicket" = "MIIBkgIBATAKBggqhkjOPQQDAzGBn58/BKcA1TCfQAThQBQAn0sUYMeqwt5j6cNdU5ZeFkUyh+Fnydifh20HNWIoMpSJJp+IAAc1YigyaTIzn5c9GAAAAADu7u7u7u7u7xAAAADu7u7u7u7u75+XPgQAAAAAn5c/BAEAAACfl0AEAQAAAJ+XRgQGAAAAn5dHBAEAAACfl0gEAAAAAJ+XSQQBAAAAn5dLBAAAAACfl0wEAQAAAARnMGUCMDf5D2EOrSirzH8zQqox7r+Ih8fIaZYjFj7Q8gZChvnLmUgbX4t7sy/sKFt+p6ZnbQIxALyXlWNh9Hni+bTkmIzkfjGhw1xNZuFATlEpORJXSJAAifzq3GMirueuNaJ339NrxqN2MBAGByqGSM49AgEGBSuBBAAiA2IABA4mUWgS86Jmr2wSbV0S8OZDqo4aLqO5jzmX2AGBh9YHIlyRqitZFvB8ytw2hBwR2JjF/7sorfMjpzCciukpBenBeaiaL1TREyjLR8OuJEtUHk8ZkDE2z3emSrGQfEpIhQ==";
	"\n\t".'"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";'.
	"\n\t".'"InternationalMobileSubscriberIdentity" = "'.$InternationalMobileSubscriberIdentity.'";'.
	"\n\t".'"ProductType" = "'.$ProductType".'";'.
	"\n\t".'"UniqueDeviceID" = "'.$uniqueDiviceID.'";'.
	"\n\t".'"SerialNumber" = "'.$serialNumber.'";'.
	"\n\t".'"MobileEquipmentIdentifier" = "'.$MobileEquipmentIdentifier.'";'.
	"\n\t".'"InternationalMobileEquipmentIdentity2" = "'.$InternationalMobileEquipmentIdentity2.'";'.
	"\n\t".'"PostponementInfo" = "'.$PostponementInfo.'";'.
	"\n\t".'"ActivationRandomness" = "'.$activationRandomness.'";'.
	"\n\t".'"ActivityURL" = "https://albert.apple.com/deviceservices/activity.'";'.
	"\n\t".'"IntegratedCircuitCardIdentity" = "'.$IntegratedCircuitCardIdentity.'";'.
	"\n\t".'"FactoryActivated" = "True";'.
	"\n".
 '}';	

$accountTokenBase64=base64_encode($accountToken);
$accountTokenBase642=base64_encode($accountToken2);

/* 

The RSA Private Key that is required for iPhone Activation on iOS 7.1.2 must be one of the following:

1) iPhoneActivation.pem 
2) FactoryActivation.pem
3) RaptorActivation.pem
4) Self-Signed iPhoneActivation.cer & iPhoneActivation_private.pem

The following RSA key is a working example of option 4 above.

*/

$private = '-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDB6D3uBkBTq3ANlIjWUBfjRMbYXMdr6oQeX1uWGWAVvzMO3yEy
mbBOB2P1fUol39VGtT+K8yv6Yf888V3+0vIxlShDXk+HXXNbf77fZ5Qzpwuzv9d0
KUSZwZKYI84xZ4uQ9H5fmgq40fSwF7GSInC0J7tzjppnw0xxzrdbqi4PpQIDAQAB
AoGATbVc3D71GJLj3Q1hqUF/0TyG076azMy3FdTxRz30G8L8G0GgdD7TQPIFRSRo
yrThK+0HAhBh133eY/X2zWCMXk9dBPvlSy8fuEH17gCb1BiQ3yc7ujKtVUt4k8hg
sutREh3zknDAwJx9i0lB6nlQUNug3a5Fvcpo6xmZCl0ZGgECQQDxJ68dReE+0Xj2
uxL005CJdzEUjeQMxFNftNyFWZcY1SQtBMCYB41kE6WPqmbtxNqNPtc0p5ZusOJc
bjv6ZNo1AkEAzdf9C/2mO4igUOOELWmlfXGoQNHuesfH0Skk9Qt0/r8u893ldRN0
IY6fAxqMfXmcgjgOSOqDMhODkb+IbbSNsQJBAIHwwSHD0o/XrRc9TASRrvLzP4X0
wqnCa65JNP3BfXIK/vgm9GO2xg/jqjUUO2vow16SOsGLf7pbI01stHLCPvUCQQCF
U74akyul6gP1ALjvdTt0ujaB7bgrHNXHG4BNnCMmkgzGdlaWc4hH6AoEx6Bx8WA3
VDmkbwmVWOBieg3TCRyxAkBKfDVXdUGBs+EpzQ4kdTyJtxRMOXLnXsQRDmtW8BTn
LlRnlBF5VKlYlTyiQRTsfkSUKmDZzHobxR0c/uDXy5ba
-----END RSA PRIVATE KEY-----';

$pkeyid = openssl_pkey_get_private($private);
$pkeyid2 = openssl_pkey_get_private($private);

openssl_sign($accountToken, $signature, $pkeyid);
openssl_free_key($pkeyid);

openssl_sign($accountToken2, $signature2, $pkeyid2);
openssl_free_key($pkeyid2);
# -------------------------------------------------------------------------------------------------


$accountTokenSignature= base64_encode($signature);
$accountTokenSignature2= base64_encode($signature2);
$accountTokenCertificateBase64 = 'LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=';
$fairPlayKeyData = 'LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQWQxcUNvNXAyWVo2bm9pTkVsNkI4cEFKbjBQZmxudHFPVklnckJDdDJXakNMdkpCL3V0TW5lZ01sUHBXClBjWjFNQWoyVHk0aGpIQms1OCtUbmE0azZRNVBLTDdQcFJvSlFkOFNFZjFTOERNdTBFclp6bDAvT3BwK2ZtTG4KYXBtcHhXeWVRcGJ6V0c3ZGh0aDFuRmxKam9GNnhlMmMyaTlMVGdlb3UzR05GaERURGhjQlczdkRIT2dGN01wbgpnaXNsWmxDYVRpQ1JIenNERkdNNzVzSjVBK1d6bUpucWpWTXA1RDZtVzM1U1NqTVhuVFNwajRwYmh4dlJlcnV4Ck9Uak1BS254MzgwbEM2bkIycDVsRGZyemdWQ1pTMmwxSW9ETlBkbm9KMjJ4RFpSWEx5d3RpemhZU0hBYStYcGYKd3daWklvWjM3YlcwTTl3TjlVWWQxVTE5MEw1QmdSNjhxYjEyVXQzbEFnVGNjVDRzOVo2Z01ZbGNVRGZsK2Nuawo4dW5qT3lkUVNvSUtJZWZEaGRsMkRibkdaUW04eXVzRlJBTnNRR2xkYzIzeGJTU3VPRFo3UWNmbEVjWnFUcUh3CjU4TytFUllwZmExVjZVbmh2VmlJTERrRTlhbkJFTm9oSnBKeHJqcG12dkc0ZlR0YVlYWlFuOXhZbHEvQktMa1kKdXg2REdUNnFsbnpBdlJqM1dpMUxFeWVFUGNJWnYxRVVoblhPRTgxU0puTVY0RTdsLzRqdy9WOEtRdmxWR3JUVQpwZjNQYVBBbVhsK29IZGlaK3ZRc3g0VzRZT1NLbE0wVFNQM04rb3FWblRWNUNxbFJkM09vVnRYV0dZSTMyM3VnCmZrM2VHM21VLytPMFUxS3Fvd1JFTVVHMjBaWWlxbUprdVE0VmJLTHFuUnZFc2FLZ0F6RDlsbWF3WGFtOWt2QkcKOVhEUUFJK3VoSWVuSWNYVFU0dUVLMWc4NmJNYzdwaHI0WGljWFlpampmTFY0elFtOGhyNlRwWVVtWlJXY2E4YgpaZ0FIN3BKMGJtUnA1aWF6OXRaMDd2Njh5ZnkxeGVNdGxaRkwxTGRVNHRrUmdCK2ZNbnRKWU9FWkV4ei9ETkM4CjNWNWZsV0VDSDRjL1d5dU5jd0o4OGhYVWRDcW9nYjU3bEdiM0NnT0tKOU9UMEdIdXBVY1NBVDY1RU5CcEcvZXEKME1mdVR5TnovZ25HRnFYTUFWaDcwWlUvOHVuT2dIK3pMdWRKdDBkUjR0SDBLWVVjUTdFSGtPTmNKMHNwQk5vcAplYmpBM2RYeGJXaXlSd28reDJaUlp1bFI3TlhRcUFESEZRY2JXcmFZRXI5MjhveGh6SDZkZC96WlBYalBVZE9RCkN0TVRUbGE4RXlXU1p1dFVNdjJFUmFEYytrWkU2bkx1a2pKbEdrUlR1dkhNQnNzYzI0dHcwS1pDbE5FRXp4N2cKNy9Remc0d3Q2Snpsb25tTC9hOVhjSVpoYzB1QXZsTk5CK0w1LzJFeFJhdVZZSTB4U1lkYkFXK3FlWGF2M1hsZwp2VEV5MTFHakY4WmMzaXc4NkdZclhqMmx3QjRpZDJWRFFlQ2xMWGdRaTBId0tTdXkvRGk4K0JmM2dKVm1tSUFoCjUzWTVBaGY4bHUyM3VHMkJSYjlZa29UdWpLMXdzYWdVWWZOb2wxUDlZSUpFUVVOeTFOWFZaUVpNQVZ5UmJUeDcKaHREUFI5anFNK1MzcjQ4SXo3LzEwYkpuWWlKSC8zODRoT3VRR1F6NDlWbGhQbEtDRXFVS25OR1N6emlid3BITgpURkZoT1VLVDc2NFVDNmY4STExelBpWW1WbFlnRVFseVBDL0lxM0pod2M1NFNOS3hzcm9zaHZRN0swQVZ5bnVFCjhqRXgxZjlaTWtvOTU0eTU1TlhaWmh4ZWRmOTFNRklZMHQrVTFMNUp6ODcvQ1FOWnI2emdKZ2FyU2tEN244TG0KcUR1ZWV1KzBEWjdra05kdnV2b1VmNzJOamc2MDk2RG9VdHhOZURwa1NKRmFSSGNVCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==';
$deviceCertificate = 'LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQklYbG4wMEdIZXppN0RBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZVekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1VnYVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElFTkJNQjRYRFRFNE1EVXhOekl6Ck5EZ3pOMW9YRFRJeE1EVXhOekl6TkRnek4xb3dnWU14TFRBckJnTlZCQU1XSkRJMVJEVkZNelJETFVSRk56QXQKTkRaRVFTMUJRelZFTFVJeVJFVkJRemMwTWpBM1JqRUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0JqUUF3Z1lrQ2dZRUF2Nmt6V1BVRkE0REIKTzdGR1ZRbXR3blUvbVY5TTJrWkRkNmZWTmtFOUU4K0hMelp0cWNNeHRvL0FSaEVJWkhHTWdiSUcrR3llMzNQUwpTTlBPVDNOMWdMdmhRN1VMSkhlVUhQL1pvYlNPWk83OXYvMDlLd2RvV1pzWm13a3NpWnVmR01WYjYzMVIzMkw1ClFHTzZ5bkJTRXgya3JwWHpOVzJYRjAva0VlaGlGTjBDQXdFQUFhT0JsVENCa2pBZkJnTlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRFRmdRVVc2d1BPNERLZ0NnU0FmZkRZQWJ3dzVITQpmK0F3REFZRFZSMFRBUUgvQkFJd0FEQU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVIQXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0liM0RRRUIKQlFVQUE0R0JBR08vV25MK3lhbXhMYXZMWG53VVZWQVNNUE8xOGhma3Q2RzgxNWZucWErMXhhV2tZVnY2VHZQeApjVlZvVnZmcnIvZ2IrL2hjMGdFRm1iL2tlOTJVcEwvN1I4Vm9TL2NCSUhXVm44WFU3a2J1bTVaTlVxeVdMbU9lClZ4VW5kdHptaUFhaTg3VmFNMFFFMjA3ZWpUQVBDc1YwdVppaktwdmEvS0sxZmhlYUp5dTcKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=';


file_put_contents($productVersion.'-'.$serialNumber.'.html','<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="keywords" content="iTunes Store" /><meta name="description" content="iTunes Store" /><title>iPhone Activation</title><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/shared/common-min.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/pages/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" /><link href="resources/auth_styles.css" charset="utf-8" rel="stylesheet" /><script id="protocol" type="text/x-apple-plist">
<plist version="1.0">
	<dict>
		<key>'.($deviceClass == "iPhone" ? 'iphone' : 'device').'-activation</key>
		<dict>
			<key>activation-record</key>
			<dict>
				<key>unbrick</key>
				<true/>
				<key>AccountTokenCertificate</key>
				<data>'.$accountTokenCertificate.'</data>
				<key>DeviceCertificate</key>
				<data>'.$deviceCertificate.'</data>
				<key>RegulatoryInfo</key>
				<data>'.$regulatoryInfo.'</data>
				<key>FairPlayKeyData</key>
				<data>'.$fairPlayKeyData.'</data>
				<key>AccountToken</key>
				<data>'.$accountToken.'</data>
				<key>AccountTokenSignature</key>
				<data>'.$accountTokenSignature.'</data>
				<key>UniqueDeviceCertificate</key>
				<data>'.$uniqueDeviceCertificate.'</data>
			</dict>
		</dict>
	</dict>
</plist>
</script><script>var protocolElement = document.getElementById("protocol");var protocolContent = protocolElement.innerText;iTunes.addProtocol(protocolContent);</script></head>
</html>');

$response ='<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="keywords" content="iTunes Store" /><meta name="description" content="iTunes Store" /><title>iPhone Activation</title><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/shared/common-min.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/pages/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" /><link href="resources/auth_styles.css" charset="utf-8" rel="stylesheet" /><script id="protocol" type="text/x-apple-plist">
<plist version="1.0">
	<dict>
		<key>'.($deviceClass == "iPhone" ? 'iphone' : 'device').'-activation</key>
		<dict>
			<key>activation-record</key>
			<dict>
				<key>unbrick</key>
				<true/>
				<key>AccountTokenCertificate</key>
				<data>'.$accountTokenCertificate.'</data>
				<key>DeviceCertificate</key>
				<data>'.$deviceCertificate.'</data>
				<key>RegulatoryInfo</key>
				<data>'.$regulatoryInfo.'</data>
				<key>FairPlayKeyData</key>
				<data>'.$fairPlayKeyData.'</data>
				<key>AccountToken</key>
				<data>'.$accountToken.'</data>
				<key>AccountTokenSignature</key>
				<data>'.$accountTokenSignature.'</data>
				<key>UniqueDeviceCertificate</key>
				<data>'.$uniqueDeviceCertificate.'</data>
			</dict>
		</dict>
	</dict>
</plist>
</script><script>var protocolElement = document.getElementById("protocol");var protocolContent = protocolElement.innerText;iTunes.addProtocol(protocolContent);</script></head>
</html>';

$response ='<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="keywords" content="iTunes Store" /><meta name="description" content="iTunes Store" /><title>iPhone Activation</title><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/shared/common-min.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" /><link href="http://static.ips.apple.com/ipa_itunes/stylesheets/pages/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" /><link href="resources/auth_styles.css" charset="utf-8" rel="stylesheet" /><script id="protocol" type="text/x-apple-plist">
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
</plist>

echo $response';
exit;
?>
