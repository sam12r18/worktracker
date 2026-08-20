export const DEFAULT_SETTINGS = Object.freeze({
  trackingEnabled: false,
  privacyMode: "domain_path",
  excludedDomains: []
});

const HTTP_PROTOCOLS = new Set(["http:", "https:"]);

export function normalizeSettings(value = {}) {
  const excluded = Array.isArray(value.excludedDomains)
    ? value.excludedDomains
        .map(item => String(item || "").trim().toLowerCase())
        .filter(Boolean)
        .slice(0, 500)
    : [];

  return {
    trackingEnabled: value.trackingEnabled === true,
    privacyMode: value.privacyMode === "domain_only" ? "domain_only" : "domain_path",
    excludedDomains: [...new Set(excluded)]
  };
}

export function normalizeUrl(rawUrl, privacyMode = "domain_path") {
  if (!rawUrl) return null;

  let parsed;
  try {
    parsed = new URL(rawUrl);
  } catch {
    return null;
  }

  if (!HTTP_PROTOCOLS.has(parsed.protocol)) return null;

  parsed.username = "";
  parsed.password = "";
  parsed.search = "";
  parsed.hash = "";

  const host = parsed.host.toLowerCase().slice(0, 512);
  const hostname = parsed.hostname.toLowerCase().slice(0, 512);
  const path = normalizePath(parsed.pathname);
  const safeUrl = privacyMode === "domain_only"
    ? `${parsed.protocol}//${host}/`
    : `${parsed.protocol}//${host}${path}`;

  return {
    url: safeUrl.slice(0, 4096),
    host,
    hostname,
    path: privacyMode === "domain_only" ? "/" : path
  };
}

export function isExcludedHost(hostname, excludedDomains = []) {
  const normalized = String(hostname || "").trim().toLowerCase();
  if (!normalized) return true;

  return excludedDomains.some(item => {
    const excluded = String(item || "").trim().toLowerCase().replace(/^\*\./, "");
    return excluded && (normalized === excluded || normalized.endsWith(`.${excluded}`));
  });
}

function normalizePath(pathname) {
  let value = String(pathname || "/").replace(/\/{2,}/g, "/");
  if (!value.startsWith("/")) value = `/${value}`;
  return value.slice(0, 2048) || "/";
}
