const BASE64_CHARS =
  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

function base64Decode(input) {
  let output = '';
  let buffer = 0;
  let bits = 0;

  for (const char of input) {
    const value = BASE64_CHARS.indexOf(char);
    if (value === -1) continue;
    buffer = (buffer << 6) | value;
    bits += 6;
    if (bits >= 8) {
      bits -= 8;
      output += String.fromCharCode((buffer >> bits) & 0xff);
    }
  }

  return output;
}

/**
 * Decodes a JWT payload without verifying its signature. Only safe for
 * reading UI-only claims (roles, expiry) — authorization is always
 * re-checked server-side on every request. Implemented without atob/Buffer
 * since neither is guaranteed to be globally available in Hermes.
 */
export function decodeJwtPayload(token) {
  const payload = token?.split('.')[1];
  if (!payload) {
    return null;
  }

  const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');

  try {
    const decoded = base64Decode(base64);
    const json = decodeURIComponent(
      decoded
        .split('')
        .map((char) => '%' + char.charCodeAt(0).toString(16).padStart(2, '0'))
        .join(''),
    );
    return JSON.parse(json);
  } catch {
    return null;
  }
}
