export function generatePin(): string {
  if (!globalThis.crypto?.getRandomValues) throw new Error('Secure randomness unavailable')
  const word = new Uint32Array(1)
  // Rejection sampling avoids modulo bias; no default PIN or numeric conversion.
  const limit = Math.floor(2 ** 32 / 1000000) * 1000000
  do {
    globalThis.crypto.getRandomValues(word)
  } while (word[0] >= limit)
  return String(word[0] % 1000000).padStart(6, '0')
}
