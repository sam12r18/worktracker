export const NATIVE_HOST_NAME = "ir.rayaasun.worktracker.browser";

export async function publishContext(context) {
  return sendNative({
    action: "context.update",
    context
  });
}

export async function clearContext(reason = "context_unavailable") {
  return sendNative({
    action: "context.clear",
    reason: String(reason || "context_unavailable").slice(0, 64)
  });
}

async function sendNative(message) {
  const response = await chrome.runtime.sendNativeMessage(NATIVE_HOST_NAME, message);

  if (!response || response.ok !== true) {
    throw new Error(response?.error || "Native host did not acknowledge the browser context request.");
  }

  return response;
}
