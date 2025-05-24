# albet.apple.com

this is a clone of apple activation server

ACTIVATION_USER_AGENT_IOS = 'iOS Device Activator (MobileActivation-20 built on Jan 15 2012 at 19:07:28)'
ACTIVATION_DEFAULT_URL = 'https://albert.apple.com/deviceservices/deviceActivation'
ACTIVATION_DRM_HANDSHAKE_DEFAULT_URL = 'https://albert.apple.com/deviceservices/drmHandshake'
DEFAULT_HEADERS = {
    'Accept': 'application/xml',
    'User-Agent': ACTIVATION_USER_AGENT_IOS,
    'Expect': '100-continue',
}

Key Features:

- iCloud Activation Lock Bypass: Allows access to a device stuck on the iCloud activation screen.
- GSM/Cellular Network Retention: Claims to maintain cellular service (calls, SMS, mobile data) on supported models after the bypass.
- "Hello Screen" Bypass: Aims to provide a clean bypass, allowing the device to function as if it were a new device.
- "Fake Reset" Functionality: Advertised to survive factory resets, preventing the device from relocking to the iCloud activation screen.
- Broad Device and iOS Support: Allegedly supports a wide range of iPhones, from XR up to the latest models (e.g., iPhone 15 Pro Max) and various iOS versions (e.g., iOS 15.x to 17.x.x, with some Wi-Fi-only options for newer iOS versions like 18.2+).
