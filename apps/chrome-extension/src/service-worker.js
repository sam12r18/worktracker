import { DEFAULT_SETTINGS, normalizeSettings } from "./privacy.js";
import { buildTabContext } from "./tab-context.js";
import { queryForCandidate } from "./tab-selection.js";
import { clearContext, publishContext } from "./native-bridge.js";

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
    // Popup/DevTools and other transient focus changes can make Chrome report no focused
    // browser window even though the last-focused normal Chrome window still has a valid
    // active tab. Defer to lastFocusedWindow selection instead of clearing immediately.
    schedulePublish();
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
    await clearPublishedContext("disabled", false);
    return;
  }

  let candidate;
  try {
    candidate = await queryForCandidate(chrome, preferredWindowId);
  } catch {
    candidate = { tab: null, windowId: null };
  }

  const tab = candidate.tab;
  if (!tab) {
    await clearPublishedContext("browser_not_focused");
    return;
  }

  const context = buildTabContext(tab, true, settings, manifest.version);
  if (!context) {
    await clearPublishedContext(tab?.incognito ? "incognito_ignored" : "page_ignored");
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
    await writeStatus({
      state: "native_host_error",
      connected: false,
      lastError: String(error?.message || error),
      host: null,
      path: null,
      title: null
    });
  }
}

async function clearPublishedContext(state, reportNativeError = true) {
  let nativeWrittenAt = null;
  let clearError = null;

  try {
    const response = await clearContext(state);
    nativeWrittenAt = response.written_at_utc || null;
  } catch (error) {
    clearError = String(error?.message || error);
  }

  if (clearError && reportNativeError) {
    await writeStatus({
      state: "native_host_error",
      connected: false,
      lastError: clearError,
      host: null,
      path: null,
      title: null
    });
    return;
  }

  await writeStatus({
    state,
    connected: state !== "disabled",
    lastError: clearError,
    host: null,
    path: null,
    title: null,
    nativeWrittenAt
  });
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
