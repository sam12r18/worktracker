import { DEFAULT_SETTINGS, normalizeSettings } from "./privacy.js";
import { buildTabContext } from "./tab-context.js";
import { publishContext } from "./native-bridge.js";

const manifest = chrome.runtime.getManifest();
let scheduled = null;

chrome.runtime.onInstalled.addListener(() => {
  void ensureDefaults().then(() => schedulePublish());
});
chrome.runtime.onStartup.addListener(() => schedulePublish());
chrome.tabs.onActivated.addListener(({ windowId }) => schedulePublish(windowId));
chrome.tabs.onUpdated.addListener((_tabId, changeInfo, tab) => {
  if (!tab.active) return;
  if (!("url" in changeInfo) && !("title" in changeInfo) && changeInfo.status !== "complete") return;
  schedulePublish(tab.windowId);
});
chrome.windows.onFocusChanged.addListener(windowId => {
  if (windowId === chrome.windows.WINDOW_ID_NONE) {
    void writeStatus({ state: "browser_not_focused", connected: true, lastError: null });
    return;
  }
  schedulePublish(windowId);
});
chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== "local") return;
  if (changes.trackingEnabled || changes.privacyMode || changes.excludedDomains) schedulePublish();
});
chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.action !== "publish-now") return false;
  publishFocusedTab()
    .then(() => sendResponse({ ok: true }))
    .catch(error => sendResponse({ ok: false, error: String(error?.message || error) }));
  return true;
});

function schedulePublish(windowId = undefined) {
  if (scheduled) clearTimeout(scheduled);
  scheduled = setTimeout(() => {
    scheduled = null;
    void publishFocusedTab(windowId);
  }, 175);
}

async function publishFocusedTab(preferredWindowId = undefined) {
  const rawSettings = await chrome.storage.local.get(DEFAULT_SETTINGS);
  const settings = normalizeSettings(rawSettings);
  if (!settings.trackingEnabled) {
    await writeStatus({ state: "disabled", connected: false, lastError: null });
    return;
  }

  let windowInfo;
  try {
    windowInfo = Number.isInteger(preferredWindowId) && preferredWindowId !== chrome.windows.WINDOW_ID_NONE
      ? await chrome.windows.get(preferredWindowId)
      : await chrome.windows.getLastFocused();
  } catch {
    windowInfo = null;
  }

  if (!windowInfo?.focused || !Number.isInteger(windowInfo.id)) {
    await writeStatus({ state: "browser_not_focused", connected: true, lastError: null });
    return;
  }

  const [tab] = await chrome.tabs.query({ active: true, windowId: windowInfo.id });
  const context = buildTabContext(tab, true, settings, manifest.version);
  if (!context) {
    await writeStatus({
      state: tab?.incognito ? "incognito_ignored" : "page_ignored",
      connected: true,
      lastError: null
    });
    return;
  }

  try {
    const response = await publishContext(context);
    await writeStatus({
      state: "connected",
      connected: true,
      lastError: null,
      lastPublishedAt: new Date().toISOString(),
      host: context.host,
      path: context.path,
      title: context.title,
      nativeWrittenAt: response.written_at_utc || null
    });
  } catch (error) {
    await writeStatus({ state: "native_host_error", connected: false, lastError: String(error?.message || error) });
  }
}

async function ensureDefaults() {
  const current = await chrome.storage.local.get(DEFAULT_SETTINGS);
  await chrome.storage.local.set(normalizeSettings(current));
}

async function writeStatus(partial) {
  const stored = await chrome.storage.local.get("bridgeStatus");
  const status = {
    ...(stored.bridgeStatus || {}),
    ...partial,
    updatedAt: new Date().toISOString()
  };
  await chrome.storage.local.set({ bridgeStatus: status });
}
