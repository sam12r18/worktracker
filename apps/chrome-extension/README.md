# WorkTracker Chrome Extension — Alpha 8.1 P0

This Manifest V3 extension provides the active Chrome tab context to the local WorkTracker Windows Agent.

## Privacy boundaries

The extension sends only browser name, active tab title, normalized host/path, a normalized URL with query string/fragment/URL credentials removed, tab/window ids, observation timestamp and extension version.

It does **not** read or send page body content, form fields, cookies, LocalStorage, network request bodies, clipboard data, passwords or authentication tokens.

Tracking is disabled by default and requires explicit opt-in in the popup. Incognito tabs are ignored.

## Development install

1. Build and register `WorkTracker.BrowserBridge`.
2. Open `chrome://extensions` and enable Developer mode.
3. Click **Load unpacked** and choose `apps/chrome-extension`.
4. Copy the generated Extension ID.
5. Run `./tools/install-chrome-native-host.ps1 -ExtensionId "<EXTENSION_ID>"`.
6. Restart Chrome and enable browser context tracking from the popup.
