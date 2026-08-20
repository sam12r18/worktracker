import { isExcludedHost, normalizeSettings, normalizeUrl } from "./privacy.js";

export function buildTabContext(tab, windowFocused, rawSettings, extensionVersion) {
  const settings = normalizeSettings(rawSettings);
  if (!settings.trackingEnabled || !tab || tab.active !== true || windowFocused !== true) return null;
  if (tab.incognito === true) return null;

  const normalized = normalizeUrl(tab.url, settings.privacyMode);
  if (!normalized || isExcludedHost(normalized.hostname, settings.excludedDomains)) return null;

  return {
    protocol_version: 1,
    extension_version: String(extensionVersion || "0.0.0").slice(0, 64),
    browser: "chrome",
    title: String(tab.title || "").trim().slice(0, 1024),
    url: normalized.url,
    host: normalized.host,
    path: normalized.path,
    tab_id: Number.isInteger(tab.id) ? tab.id : -1,
    window_id: Number.isInteger(tab.windowId) ? tab.windowId : -1,
    incognito: false,
    focused: true,
    observed_at_utc: new Date().toISOString(),
    source: "chrome_extension"
  };
}
