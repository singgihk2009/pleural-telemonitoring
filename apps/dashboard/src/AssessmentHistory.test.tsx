// @vitest-environment jsdom
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { vi, beforeEach, afterEach, expect, test } from 'vitest'
import { AssessmentHistory } from './AssessmentHistory'
import { ApiError, request } from './api'
vi.mock('./api', async () => ({ ...await vi.importActual('./api'), request: vi.fn() }))
afterEach(cleanup)
const session = { token: 'SYNTHETIC', expiresAt: '2099-01-01T00:00:00Z' }
const onExpired = vi.fn()
beforeEach(() => { vi.resetAllMocks() })
test('only loads authorized read projection on demand and has no editing controls', async () => {
  vi.mocked(request).mockResolvedValue({ data: [{ id: 'SYNTHETIC', date_wib: '2026-11-07', study_day: 1, vas_mm: 0, filled_by: 'PASIEN', received_at: '2026-11-07T01:00:00Z', version: 'SYNTHETIC-V1', answers: [{ code: 'SYNTHETIC_ITEM', value: 'SYNTHETIC_A' }] }], meta: { last_page: 1 } })
  render(<AssessmentHistory participantId="SYNTHETIC" session={session} onExpired={onExpired} />)
  expect(request).not.toHaveBeenCalled()
  fireEvent.click(screen.getByRole('button', { name: 'Lihat riwayat assessment' }))
  await screen.findByText('SYNTHETIC_ITEM: SYNTHETIC_A')
  expect(request).toHaveBeenCalledWith('/participants/SYNTHETIC/assessments?page=1', session.token, 'GET', undefined, expect.any(AbortSignal))
  expect(screen.queryByRole('textbox')).toBeNull()
  expect(screen.getByText(/Jawaban tersimpan tidak dapat diedit/)).toBeTruthy()
})
test('denial reveals no data and expiration invalidates the researcher session', async () => {
  vi.mocked(request).mockRejectedValueOnce(new ApiError(403))
  render(<AssessmentHistory participantId="SYNTHETIC" session={session} onExpired={onExpired} />)
  fireEvent.click(screen.getByRole('button', { name: 'Lihat riwayat assessment' }))
  await screen.findByRole('alert'); expect(screen.queryByText(/VAS/)).toBeNull()
  vi.mocked(request).mockRejectedValueOnce(new ApiError(401))
  fireEvent.click(screen.getByRole('button', { name: 'Coba lagi' }))
  await waitFor(() => expect(onExpired).toHaveBeenCalled())
})
test('closing aborts read and a late response does not appear', async () => {
  let resolve!: (data: unknown) => void
  vi.mocked(request).mockImplementation(() => new Promise(done => { resolve = done }))
  render(<AssessmentHistory participantId="SYNTHETIC" session={session} onExpired={onExpired} />)
  fireEvent.click(screen.getByRole('button', { name: 'Lihat riwayat assessment' }))
  fireEvent.click(screen.getByRole('button', { name: 'Tutup riwayat assessment' }))
  expect(vi.mocked(request).mock.calls[0][4]?.aborted).toBe(true)
  resolve({ data: [], meta: { last_page: 1 } })
  expect(screen.queryByText('Belum ada assessment tersimpan.')).toBeNull()
})
