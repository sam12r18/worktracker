export async function queryForCandidate(chromeApi, preferredWindowId = undefined) {
  if (Number.isInteger(preferredWindowId) && preferredWindowId !== chromeApi.windows.WINDOW_ID_NONE) {
    const [tab] = await chromeApi.tabs.query({ active: true, windowId: preferredWindowId });
    return {
      tab: tab || null,
      windowId: tab?.windowId ?? preferredWindowId
    };
  }

  const [tab] = await chromeApi.tabs.query({ active: true, lastFocusedWindow: true });
  return {
    tab: tab || null,
    windowId: tab?.windowId ?? null
  };
}
