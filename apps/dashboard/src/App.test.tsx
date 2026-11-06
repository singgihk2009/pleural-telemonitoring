// @vitest-environment jsdom
import { render, screen, cleanup, fireEvent, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import App from './App'
import type { Enrollment, Participant } from './api'

const token = 'SYNTHETIC-BROWSER-TOKEN'
const participant: Enrollment = {
  id: '00000000-0000-0000-0000-000000000001',
  kode: 'SYN-STUDY-001',
  kelompok: 'INTERVENSI',
  tanggal_pulang: '2026-11-01',
  vas_baseline_mm: 0,
  tanggal_consent: '2026-11-01',
  versi_consent: 'SYN-v1',
  status: 'AKTIF',
  updated_at: '2026-11-03T02:00:00.123456Z',
  contact: null,
  alasan_drop_out: null,
  baseline_locked: false,
}
let rows: Participant[]
let overrides: (
  path: string,
  method: string,
  data: Record<string, unknown>,
) => Response | undefined | Promise<Response | undefined>
let calls: {
  url: string
  path: string
  method: string
  data: Record<string, unknown>
  options: RequestInit
}[]
function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}
beforeEach(() => {
  rows = [participant]
  calls = []
  overrides = () => undefined
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string, options: RequestInit = {}) => {
      const path = new URL(input).pathname.replace('/api/v1', '')
      const method = options.method || 'GET'
      const data = options.body ? JSON.parse(String(options.body)) : {}
      calls.push({ url: input, path, method, data, options })
      const overridden = await overrides(path, method, data)
      if (overridden) return overridden
      if (path === '/auth/staff/login')
        return json({
          access_token: token,
          token_type: 'Bearer',
          expires_at: new Date(Date.now() + 3600000).toISOString(),
        })
      if (path === '/research/access') return json({ data: { can_manage_enrollment: true } })
      if (path === '/research/participants/search')
        return json({
          data: rows,
          meta: {
            total: rows.length,
            intervensi: rows.filter((row) => row.kelompok === 'INTERVENSI').length,
            kontrol: rows.filter((row) => row.kelompok === 'KONTROL').length,
            aktif: rows.filter((row) => row.status === 'AKTIF').length,
            current_page: 1,
            last_page: 1,
          },
        })
      if (path === `/research/participants/${participant.id}` && method === 'GET')
        return json({ data: participant })
      if (path === '/research/participants' && method === 'POST') {
        const created = { ...participant, ...data, id: 'synthetic-created' } as Participant
        rows = [created, ...rows]
        return json({ data: created }, 201)
      }
      if (method === 'PATCH') return json({ data: { ...participant, ...data } })
      if (path === '/auth/logout') return json({ message: 'Logged out.' })
      return json({ message: 'Not Found.' }, 404)
    }),
  )
})
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})
async function signIn() {
  render(<App />)
  fireEvent.change(screen.getByLabelText('Email staf'), {
    target: { value: 'synthetic@example.invalid' },
  })
  fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: 'synthetic-password' } })
  fireEvent.click(screen.getByRole('button', { name: 'Masuk ke ruang penelitian' }))
  await screen.findByRole('heading', { name: 'Partisipan penelitian' })
}
async function newForm() {
  fireEvent.click(screen.getByRole('button', { name: 'Daftarkan partisipan' }))
  return screen.getByRole('dialog')
}
function fillForm(dialog: HTMLElement, values = { kode: 'SYN-CREATED-002', vas: '0' }) {
  const form = within(dialog)
  fireEvent.change(form.getByLabelText('Kode responden *'), { target: { value: values.kode } })
  fireEvent.change(form.getByLabelText('Tanggal pulang (hari 0) *'), {
    target: { value: '2026-11-01' },
  })
  fireEvent.change(form.getByLabelText('VAS baseline *'), { target: { value: values.vas } })
  fireEvent.change(form.getByLabelText('Tanggal persetujuan *'), {
    target: { value: '2026-11-01' },
  })
  fireEvent.change(form.getByLabelText('Versi dokumen persetujuan *'), {
    target: { value: 'SYN-v1' },
  })
}

test('researcher login keeps token in memory and all requests omit cookies', async () => {
  const persist = vi.spyOn(Storage.prototype, 'setItem')
  await signIn()
  await screen.findByText(participant.kode)
  expect(calls.some((call) => call.path === '/research/access')).toBe(true)
  expect(persist).not.toHaveBeenCalled()
  for (const call of calls) {
    expect(call.options.credentials).toBe('omit')
    expect(call.options.cache).toBe('no-store')
  }
  const search = calls.find((call) => call.path === '/research/participants/search')
  expect((search?.options.headers as Record<string, string> | undefined)?.Authorization).toBe(
    `Bearer ${token}`,
  )
  expect(screen.queryByText('SYNTHETIC-NOT-A-PHONE')).toBeNull()
})

test('unauthorized staff are denied before any participant data is loaded and token is revoked', async () => {
  overrides = (path) =>
    path === '/research/access' ? json({ message: 'Forbidden.' }, 403) : undefined
  render(<App />)
  fireEvent.change(screen.getByLabelText('Email staf'), {
    target: { value: 'synthetic@example.invalid' },
  })
  fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: 'synthetic-password' } })
  fireEvent.click(screen.getByRole('button', { name: 'Masuk ke ruang penelitian' }))
  await screen.findByRole('alert')
  expect(calls.some((call) => call.path === '/research/participants/search')).toBe(false)
  expect(calls.some((call) => call.path === '/auth/logout')).toBe(true)
  expect(screen.queryByText(participant.kode)).toBeNull()
})

test('a false server capability does not open the workspace', async () => {
  overrides = (path) =>
    path === '/research/access' ? json({ data: { can_manage_enrollment: false } }) : undefined
  render(<App />)
  fireEvent.change(screen.getByLabelText('Email staf'), {
    target: { value: 'synthetic@example.invalid' },
  })
  fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: 'synthetic-password' } })
  fireEvent.click(screen.getByRole('button', { name: 'Masuk ke ruang penelitian' }))
  await screen.findByRole('alert')
  expect(calls.some((call) => call.path === '/research/participants/search')).toBe(false)
})

test('search and filters are sent in JSON with no respondent code in URLs', async () => {
  await signIn()
  await screen.findByText(participant.kode)
  fireEvent.change(screen.getByLabelText('Cari kode responden'), {
    target: { value: 'SYN-PRIVATE-CODE' },
  })
  fireEvent.click(screen.getByRole('button', { name: 'Cari' }))
  fireEvent.change(screen.getByLabelText('Kelompok'), { target: { value: 'KONTROL' } })
  fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'SELESAI' } })
  await waitFor(() =>
    expect(
      calls.some(
        (call) =>
          call.data.search === 'SYN-PRIVATE-CODE' &&
          call.data.group === 'KONTROL' &&
          call.data.status === 'SELESAI',
      ),
    ).toBe(true),
  )
  expect(calls.every((call) => !call.url.includes('SYN-PRIVATE-CODE'))).toBe(true)
  fireEvent.click(screen.getByRole('button', { name: 'Reset filter' }))
  await waitFor(() => expect(calls.at(-1)?.data.search).toBeNull())
})

test('pagination submits the selected page and displays matching rows', async () => {
  overrides = (path, _method, body) =>
    path === '/research/participants/search'
      ? json({
          data: [{ ...participant, kode: body.page === 2 ? 'SYN-PAGE-TWO' : participant.kode }],
          meta: {
            total: 26,
            intervensi: 26,
            kontrol: 0,
            aktif: 26,
            current_page: body.page,
            last_page: 2,
          },
        })
      : undefined
  await signIn()
  await screen.findByText(participant.kode)
  fireEvent.click(screen.getByRole('button', { name: 'Halaman berikutnya' }))
  await screen.findByText('SYN-PAGE-TWO')
  expect(screen.getByText('Halaman 2 dari 2')).toBeTruthy()
  expect(calls.some((call) => call.data.page === 2)).toBe(true)
})

test('enrollment accepts zero VAS and control group without issuing patient credentials', async () => {
  await signIn()
  const dialog = await newForm()
  fillForm(dialog)
  fireEvent.change(within(dialog).getByLabelText('Kelompok penelitian *'), {
    target: { value: 'KONTROL' },
  })
  fireEvent.click(within(dialog).getByRole('button', { name: 'Daftarkan partisipan' }))
  await screen.findByText('Partisipan berhasil didaftarkan.')
  const saved = calls.find(
    (call) => call.path === '/research/participants' && call.method === 'POST',
  )
  expect(saved?.data.vas_baseline_mm).toBe(0)
  expect(saved?.data.kelompok).toBe('KONTROL')
  expect(calls.some((call) => call.path.includes('credentials'))).toBe(false)
  expect(screen.queryByRole('dialog')).toBeNull()
})

test('invalid baseline and missing drop-out reason are rejected before submission', async () => {
  await signIn()
  const dialog = await newForm()
  fillForm(dialog, { kode: 'SYN-INVALID', vas: '101' })
  fireEvent.change(within(dialog).getByLabelText('Status penelitian'), {
    target: { value: 'DROP_OUT' },
  })
  fireEvent.click(within(dialog).getByRole('button', { name: 'Daftarkan partisipan' }))
  expect(await within(dialog).findByText('Masukkan bilangan bulat 0–100 mm.')).toBeTruthy()
  expect(within(dialog).getByText('Alasan wajib diisi untuk DROP_OUT.')).toBeTruthy()
  expect(calls.some((call) => call.path === '/research/participants')).toBe(false)
})

test('duplicate code validation stays in the form without losing entered values', async () => {
  overrides = (path, method) =>
    path === '/research/participants' && method === 'POST'
      ? json(
          { message: 'Validation failed.', errors: { kode: ['Kode responden sudah digunakan.'] } },
          422,
        )
      : undefined
  await signIn()
  const dialog = await newForm()
  fillForm(dialog)
  fireEvent.click(within(dialog).getByRole('button', { name: 'Daftarkan partisipan' }))
  await within(dialog).findByText('Kode responden sudah digunakan.')
  expect((within(dialog).getByLabelText('Kode responden *') as HTMLInputElement).value).toBe(
    'SYN-CREATED-002',
  )
})

test('editor preserves immutable identity and locked baseline and submits the exact version', async () => {
  overrides = (path, method) =>
    path === `/research/participants/${participant.id}` && method === 'GET'
      ? json({
          data: {
            ...participant,
            baseline_locked: true,
            contact: {
              initials: 'SYN',
              patient_contact: 'SYNTHETIC-CONTACT',
              family_contact: null,
            },
          },
        })
      : undefined
  await signIn()
  await screen.findByText(participant.kode)
  fireEvent.click(screen.getByRole('button', { name: `Ubah partisipan ${participant.kode}` }))
  const dialog = screen.getByRole('dialog')
  await within(dialog).findByText(
    'Baseline dan hari 0 terkunci karena sudah ada catatan penelitian.',
  )
  for (const name of [
    'Kode responden *',
    'Kelompok penelitian *',
    'VAS baseline *',
    'Tanggal pulang (hari 0) *',
  ])
    expect((within(dialog).getByLabelText(name) as HTMLInputElement).disabled).toBe(true)
  fireEvent.change(within(dialog).getByLabelText('Status penelitian'), {
    target: { value: 'DROP_OUT' },
  })
  fireEvent.change(within(dialog).getByLabelText('Alasan DROP_OUT *'), {
    target: { value: 'SYNTHETIC REASON' },
  })
  fireEvent.click(within(dialog).getByRole('button', { name: 'Simpan perubahan' }))
  await screen.findByText('Perubahan partisipan berhasil disimpan.')
  const change = calls.find((call) => call.method === 'PATCH')
  expect(change?.data.expected_updated_at).toBe(participant.updated_at)
  expect(change?.data).not.toHaveProperty('kode')
  expect(change?.data).not.toHaveProperty('kelompok')
  expect(change?.data.alasan_drop_out).toBe('SYNTHETIC REASON')
})

test('stale edits show conflict without silently retrying or overwriting', async () => {
  overrides = (_path, method) =>
    method === 'PATCH' ? json({ message: 'Conflict.' }, 409) : undefined
  await signIn()
  await screen.findByText(participant.kode)
  fireEvent.click(screen.getByRole('button', { name: `Ubah partisipan ${participant.kode}` }))
  const dialog = screen.getByRole('dialog')
  await within(dialog).findByDisplayValue('SYN-v1')
  fireEvent.click(within(dialog).getByRole('button', { name: 'Simpan perubahan' }))
  await within(dialog).findByText(/Data telah diperbarui oleh peneliti lain/)
  expect(calls.filter((call) => call.method === 'PATCH')).toHaveLength(1)
  expect(screen.getByRole('dialog')).toBeTruthy()
})

test('expired sessions remove participant data and return to login', async () => {
  await signIn()
  await screen.findByText(participant.kode)
  overrides = (path) =>
    path === '/research/participants/search' ? json({ message: 'Unauthorized.' }, 401) : undefined
  fireEvent.click(screen.getByRole('button', { name: 'Muat ulang daftar' }))
  await screen.findByRole('heading', { name: 'Selamat datang kembali' })
  expect(screen.queryByText(participant.kode)).toBeNull()
})

test('list failure can be retried and empty states contain no fabricated participants', async () => {
  let attempts = 0
  rows = []
  overrides = (path) =>
    path === '/research/participants/search' && attempts++ === 0
      ? json({ message: 'Service Unavailable.' }, 503)
      : undefined
  await signIn()
  await screen.findByRole('alert')
  fireEvent.click(screen.getByRole('button', { name: 'Coba lagi' }))
  await screen.findByRole('heading', { name: 'Belum ada partisipan' })
  expect(screen.queryByText(participant.kode)).toBeNull()
})

test('logout clears local participant data even when server revocation cannot be reached', async () => {
  await signIn()
  await screen.findByText(participant.kode)
  overrides = (path) => {
    if (path === '/auth/logout') throw new Error('synthetic network failure')
    return undefined
  }
  fireEvent.click(screen.getByRole('button', { name: 'Keluar' }))
  await screen.findByRole('heading', { name: 'Selamat datang kembali' })
  expect(screen.queryByText(participant.kode)).toBeNull()
  await screen.findByText(
    'Keluar dari perangkat ini. Pencabutan sesi server belum dapat dikonfirmasi.',
  )
})

test('researcher manages Android PIN inside the existing participant editor without saving enrollment', async () => {
  let status = 'BELUM_DITERBITKAN'
  overrides = (path, method) => {
    if (path !== `/participants/${participant.id}/credentials`) return undefined
    if (method === 'POST') { status = 'AKTIF'; return json({ message: 'Success' }, 201) }
    if (method === 'DELETE') { status = 'DICABUT'; return json({ message: 'Success' }) }
    return json({ data: { status } })
  }
  await signIn(); await screen.findByText(participant.kode)
  fireEvent.click(screen.getByRole('button', { name: `Ubah partisipan ${participant.kode}` }))
  fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
  const reveal = await screen.findByRole('region', { name: 'PIN Android berhasil diterbitkan' })
  expect(within(reveal).getByText(participant.kode, { exact: false })).toBeTruthy()
  expect(calls.find(c => c.path.endsWith('/credentials') && c.method === 'POST')?.data.pin).toMatch(/^[0-9]{6}$/)
  expect(calls.some(c => c.method === 'PATCH')).toBe(false)
  fireEvent.click(screen.getByRole('button', { name: 'Tutup editor' }))
  expect(screen.queryByRole('region', { name: 'PIN Android berhasil diterbitkan' })).toBeNull()
  fireEvent.click(screen.getByRole('button', { name: `Ubah partisipan ${participant.kode}` }))
  await screen.findByRole('button', { name: 'Reset PIN Android' })
  expect(screen.queryByRole('region', { name: 'PIN Android berhasil diterbitkan' })).toBeNull()
})

test('credential denial invalidates staff session without revealing PIN', async () => {
  overrides = (path, method) => path.endsWith('/credentials') ?
    method === 'GET' ? json({ data: { status: 'BELUM_DITERBITKAN' } }) : json({}, 403) : undefined
  await signIn(); await screen.findByText(participant.kode)
  fireEvent.click(screen.getByRole('button', { name: `Ubah partisipan ${participant.kode}` }))
  fireEvent.click(await screen.findByRole('button', { name: 'Terbitkan PIN Android' }))
  await screen.findByRole('button', { name: 'Masuk ke ruang penelitian' })
  expect(screen.queryByRole('region', { name: 'PIN Android berhasil diterbitkan' })).toBeNull()
})
