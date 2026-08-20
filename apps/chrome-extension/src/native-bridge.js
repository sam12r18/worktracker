export const NATIVE_HOST_NAME = "ir.rayaasun.worktracker.browser";

export async function publishContext(context) {
  const response = await chrome.runtime.sendNativeMessage(NATIVE_HOST_NAME, {
    action: "context.update",
    context
  });

  if (!response || response.ok !== true) {
    throw new Error(response?.error || "Native host did not acknowledge the browser context.");
  }

  return response;
}
