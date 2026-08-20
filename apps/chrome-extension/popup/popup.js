const trackingEnabled = document.getElementById("trackingEnabled");
const privacyMode = document.getElementById("privacyMode");
const status = document.getElementById("status");
const host = document.getElementById("host");
const path = document.getElementById("path");
const error = document.getElementById("error");
const stateDot = document.getElementById("stateDot");
const publishNow = document.getElementById("publishNow");

void refresh();
trackingEnabled.addEventListener("change", async () => {
  await chrome.storage.local.set({ trackingEnabled: trackingEnabled.checked });
  await requestPublish();
  await refresh();
});
privacyMode.addEventListener("change", async () => {
  await chrome.storage.local.set({ privacyMode: privacyMode.value });
  await requestPublish();
  await refresh();
});
publishNow.addEventListener("click", async () => { await requestPublish(); await refresh(); });
chrome.storage.onChanged.addListener((changes, area) => {
  if (area === "local" && (changes.bridgeStatus || changes.trackingEnabled || changes.privacyMode)) void refresh();
});

async function requestPublish() {
  error.textContent = "";
  try {
    const response = await chrome.runtime.sendMessage({ action: "publish-now" });
    if (response && response.ok === false) throw new Error(response.error || "ارسال ناموفق بود.");
  } catch (e) {
    error.textContent = String(e?.message || e);
  }
}

async function refresh() {
  const data = await chrome.storage.local.get({ trackingEnabled: false, privacyMode: "domain_path", bridgeStatus: null });
  trackingEnabled.checked = data.trackingEnabled === true;
  privacyMode.value = data.privacyMode === "domain_only" ? "domain_only" : "domain_path";
  const bridge = data.bridgeStatus || {};
  status.textContent = labelForState(bridge.state);
  host.textContent = bridge.host || "—";
  path.textContent = bridge.path || "—";
  error.textContent = bridge.lastError || "";
  stateDot.classList.toggle("ok", bridge.state === "connected");
}

function labelForState(value) {
  const labels = {
    connected: "متصل",
    disabled: "غیرفعال",
    browser_not_focused: "Chrome فعال نیست",
    incognito_ignored: "Incognito نادیده گرفته شد",
    page_ignored: "صفحه نادیده گرفته شد",
    native_host_error: "Native Host در دسترس نیست"
  };
  return labels[value] || "نامشخص";
}
