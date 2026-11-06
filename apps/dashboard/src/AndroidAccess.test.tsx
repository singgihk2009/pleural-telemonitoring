// @vitest-environment jsdom
import { render, screen, fireEvent, waitFor, cleanup, act } from '@testing-library/react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { AndroidAccess } from './AndroidAccess'
import { generatePin } from './pin'
import type { Enrollment, Session } from './api'
const participant: Enrollment = {
  id: '00000000-0000-0000-0000-000000000010',
  kode: 'SYN-PIN-TEST',
  kelompok: 'INTERVENSI',
  tanggal_pulang: '2026-11-01',
  vas_baseline_mm: 0,
  tanggal_consent: '2026-11-01',
  versi_consent: 'SYN-v1',
  status: 'AKTIF',
  updated_at: null,
  alasan_drop_out: null,
  baseline_locked: false,
  contact: null,
}
const session: Session = {
  token: 'SYNTHETIC-STAFF',
  expiresAt: new Date(Date.now() + 3600000).toISOString(),
}
let status: string
let handler: (method: string) => Promise<Response> | Response | undefined
let calls: { method: string; url: string; body?: { pin?: string } }[]
let onExpired = vi.fn<() => void>()
function json(data: unknown, code = 200) {
  return new Response(JSON.stringify(data), {
    status: code,
    headers: { 'Content-Type': 'application/json' },
  })
}
beforeEach(() => {
  status = 'BELUM_DITERBITKAN'
  calls = []
  handler = () => undefined
  onExpired = vi.fn<() => void>()
  vi.spyOn(crypto, 'getRandomValues').mockImplementation((array) => {
    ;(array as Uint32Array)[0] = 42
    return array
  })
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, options: RequestInit) => {
      const method = options.method || 'GET'
      calls.push({
        method,
        url,
        body: options.body ? JSON.parse(String(options.body)) : undefined,
      })
      const custom = handler(method)
      if (custom) return custom
      if (method === 'POST') status = 'AKTIF'
      if (method === 'DELETE') status = 'DICABUT'
      return json(
        method === 'GET' ? { data: { status } } : { message: 'Success' },
        method === 'POST' ? 201 : 200,
      )
    }),
  )
})
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})
function mount(p = participant, s = session) {
  return render(
    <AndroidAccess participant={p} session={s} disabled={false} onExpired={onExpired} />,
  )
}
async function issue() {
  fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
  return screen.findByRole('region', { name: 'PIN Android berhasil diterbitkan' })
}
test('Web Crypto rejection sampling preserves leading zeros', () => {
  vi.mocked(crypto.getRandomValues)
    .mockImplementationOnce((array) => {
      ;(array as Uint32Array)[0] = 0xffffffff
      return array
    })
    .mockImplementationOnce((array) => {
      ;(array as Uint32Array)[0] = 7
      return array
    })
  expect(generatePin()).toBe('000007')
  expect(crypto.getRandomValues).toHaveBeenCalledTimes(2)
})
test('no insecure randomness fallback', () => {
  vi.stubGlobal('crypto', undefined)
  expect(() => generatePin()).toThrow('Secure randomness unavailable')
})
test('issue uses existing contract; one-time PIN clears on close and reopen, never persisted', async () => {
  const persist = vi.spyOn(Storage.prototype, 'setItem')
  const view = mount()
  await issue()
  expect(screen.getByText('000042')).toBeTruthy()
  expect(calls.find((c) => c.method === 'POST')?.body).toEqual({ pin: '000042' })
  expect(calls.every((c) => !c.url.includes('000042'))).toBe(true)
  expect(persist).not.toHaveBeenCalled()
  expect(
    screen.getByRole('region', { name: 'PIN Android berhasil diterbitkan' }),
  ).toHaveProperty('tabIndex', -1)
  fireEvent.click(screen.getByRole('button', { name: 'Tutup PIN' }))
  expect(screen.queryByText('000042')).toBeNull()
  view.unmount()
  mount()
  await screen.findByRole('button', { name: 'Reset PIN Android' })
  expect(screen.queryByText('000042')).toBeNull()
})
test('reset and revoke require confirmation; reset reveals only the successful new PIN', async () => {
  status = 'AKTIF'
  mount()
  fireEvent.click(await screen.findByRole('button', { name: 'Reset PIN Android' }))
  expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0)
  expect(document.activeElement).toBe(
    screen.getByRole('group', { name: 'Konfirmasi reset PIN' }),
  )
  fireEvent.click(screen.getByRole('button', { name: 'Batal tindakan' }))
  expect(document.activeElement).toBe(
    screen.getByRole('button', { name: 'Reset PIN Android' }),
  )
  fireEvent.click(screen.getByRole('button', { name: 'Reset PIN Android' }))
  fireEvent.click(screen.getByRole('button', { name: 'Ya, reset PIN' }))
  await screen.findByText('000042')
  fireEvent.click(screen.getByRole('button', { name: 'Tutup PIN' }))
  fireEvent.click(screen.getByRole('button', { name: 'Cabut Akses Android' }))
  expect(calls.filter((c) => c.method === 'DELETE')).toHaveLength(0)
  fireEvent.click(screen.getByRole('button', { name: 'Ya, cabut akses' }))
  await screen.findByText('Dicabut')
  expect(calls.filter((c) => c.method === 'DELETE')).toHaveLength(1)
  expect(screen.queryByText('000042')).toBeNull()
})
test.each(['KONTROL', 'SELESAI', 'DROP_OUT'])(
  '%s has no credential controls or status request',
  (value) => {
    mount({
      ...participant,
      ...(value === 'KONTROL'
        ? { kelompok: 'KONTROL' as const }
        : { status: value as Enrollment['status'] }),
    })
    expect(screen.queryByRole('button', { name: /PIN Android/ })).toBeNull()
    expect(calls).toHaveLength(0)
  },
)
test('status loading and error are authoritative; retry recovers without guessed state', async () => {
  handler = () => json({}, 503)
  mount()
  expect(screen.getByText('Memuat…')).toBeTruthy()
  await screen.findByRole('alert')
  expect(screen.queryByText('Belum diterbitkan')).toBeNull()
  handler = () => undefined
  fireEvent.click(screen.getByRole('button', { name: 'Muat ulang status Android' }))
  await screen.findByText('Belum diterbitkan')
})
test.each([401, 403])('permission/session %s denies controls', async (code) => {
  handler = () => json({}, code)
  mount()
  await waitFor(() => expect(onExpired).toHaveBeenCalled())
  expect(screen.queryByRole('button', { name: 'Terbitkan PIN Android' })).toBeNull()
})
test.each([422, 500])(
  'failed issuance %s never reveals plaintext and requires status reload',
  async (code) => {
    handler = (method) => (method === 'POST' ? json({}, code) : undefined)
    mount()
    fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
    await screen.findByRole('alert')
    expect(screen.queryByText('000042')).toBeNull()
    expect(screen.queryByRole('button', { name: 'Terbitkan PIN Android' })).toBeNull()
  },
)
test('duplicate clicks cause only one pending issuance; closing/logging out cannot reveal late PIN', async () => {
  let resolve!: (response: Response) => void
  handler = (method) =>
    method === 'POST'
      ? new Promise<Response>((done) => {
          resolve = done
        })
      : undefined
  const view = mount()
  const button = await screen.findByRole('button', { name: 'Terbitkan PIN Android' })
  fireEvent.click(button)
  fireEvent.click(button)
  expect(calls.filter((c) => c.method === 'POST')).toHaveLength(1)
  view.unmount()
  await act(async () => {
    resolve(json({ message: 'Success' }, 201))
  })
  expect(screen.queryByText('000042')).toBeNull()
  expect(calls.filter((c) => c.method === 'GET')).toHaveLength(1)
})
test('participant and staff session changes clear the PIN', async () => {
  const view = mount()
  await issue()
  status = 'BELUM_DITERBITKAN'
  view.rerender(
    <AndroidAccess
      participant={{ ...participant, id: 'synthetic-other' }}
      session={session}
      disabled={false}
      onExpired={onExpired}
    />,
  )
  expect(screen.queryByText('000042')).toBeNull()
  await issue()
  view.rerender(
    <AndroidAccess
      participant={{ ...participant, id: 'synthetic-other' }}
      session={{ ...session, token: 'SYNTHETIC-SECOND' }}
      disabled={false}
      onExpired={onExpired}
    />,
  )
  expect(screen.queryByText('000042')).toBeNull()
})
test('session expiry clears visible PIN and invalidates the workspace', async () => {
  mount(participant, { ...session, expiresAt: new Date(Date.now() + 1500).toISOString() })
  await issue()
  await waitFor(() => expect(onExpired).toHaveBeenCalled(), { timeout: 2500 })
  expect(screen.queryByText('000042')).toBeNull()
})
test('a forbidden status recheck after successful write cannot reveal a PIN', async () => {
  let wrote = false
  handler = (method) => {
    if (method === 'POST') wrote = true
    return method === 'GET' && wrote ? json({}, 403) : undefined
  }
  mount()
  fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
  await waitFor(() => expect(onExpired).toHaveBeenCalled())
  expect(screen.queryByText('000042')).toBeNull()
})
test('unsaved participant status change disables credential mutations', async () => {
  render(
    <AndroidAccess
      participant={participant}
      session={session}
      disabled
      onExpired={onExpired}
    />,
  )
  const button = await screen.findByRole('button', { name: 'Terbitkan PIN Android' })
  expect(button.hasAttribute('disabled')).toBe(true)
  fireEvent.click(button)
  expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0)
})

test('concurrently revoked status suppresses a newly issued PIN', async () => {
  let wrote = false
  handler = (method) => {
    if (method === 'POST') wrote = true
    return method === 'GET' && wrote ? json({ data: { status: 'DICABUT' } }) : undefined
  }
  mount()
  fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
  await screen.findByText('Dicabut')
  expect(screen.queryByText('000042')).toBeNull()
  expect(screen.getByText(/Status akses telah berubah/)).toBeTruthy()
})
