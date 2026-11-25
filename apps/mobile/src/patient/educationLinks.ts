// The API validates source references too. Recheck at the device handoff.
export function canOpenEducationUrl(url: string, video: boolean): boolean {
  // eslint-disable-next-line no-control-regex
  if (/[\x00-\x20\x7f]/.test(url) || url.includes(String.fromCharCode(92))) return false;
  const match = /^https:\/\/([A-Za-z0-9.-]+)(?::443)?(\/[^?#]*)?(\?[^#]*)?(?:#.*)?$/.exec(url);
  if (!match) return false;
  const host = match[1].toLowerCase();
  if (!host.includes('.') || /^[\d.]+$/.test(host) || /\.(localhost|local|internal|test|invalid)\.?$/.test(host)) return false;
  if (!video) return !/(^|\.)youtube\.com\.?$|^youtu\.be\.?$/.test(host);
  const path = match[2] ?? '';
  if (host === 'youtu.be') return /^\/[A-Za-z0-9_-]{11}$/.test(path);
  if (!['youtube.com','www.youtube.com','m.youtube.com'].includes(host) || path !== '/watch') return false;
  const ids = (match[3] ?? '').slice(1).split('&').filter(part => part.startsWith('v='));
  return ids.length === 1 && /^v=[A-Za-z0-9_-]{11}$/.test(ids[0]);
}
