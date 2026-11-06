import { useEffect, useRef, useState } from 'react'
import { ApiError, errorMessage, request } from './api'
import type { Enrollment, Session } from './api'
import { generatePin } from './pin'

type CredentialStatus = 'BELUM_DITERBITKAN' | 'AKTIF' | 'DICABUT'
const labels: Record<CredentialStatus, string> = {
  BELUM_DITERBITKAN: 'Belum diterbitkan',
  AKTIF: 'Aktif',
  DICABUT: 'Dicabut',
}
export function AndroidAccess({
  participant,
  session,
  disabled,
  onExpired,
}: {
  participant: Enrollment
  session: Session
  disabled: boolean
  onExpired: () => void
}) {
  // Changing participant remounts all secret/operation state, including pending promises.
  return (
    <CredentialPanel
      key={participant.id}
      participant={participant}
      session={session}
      disabled={disabled}
      onExpired={onExpired}
    />
  )
}
function CredentialPanel({
  participant,
  session,
  disabled,
  onExpired,
}: {
  participant: Enrollment
  session: Session
  disabled: boolean
  onExpired: () => void
}) {
  const eligible = participant.kelompok === 'INTERVENSI' && participant.status === 'AKTIF'
  const [status, setStatus] = useState<{ owner: string; value: CredentialStatus } | null>(null)
  const [pin, setPin] = useState<{ owner: string; value: string } | null>(null)
  const [confirm, setConfirm] = useState<'reset' | 'revoke' | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [retry, setRetry] = useState(0)
  const pending = useRef(false)
  const generation = useRef(0)
  const controller = useRef<AbortController | null>(null)
  const reveal = useRef<HTMLElement>(null)
  const confirmation = useRef<HTMLDivElement>(null)
  const actionButton = useRef<HTMLButtonElement>(null)
  const hadPanel = useRef(false)
  const path = `/participants/${encodeURIComponent(participant.id)}/credentials`
  const current = status?.owner === session.token ? status.value : null
  const secret =
    pin?.owner === session.token && Date.parse(session.expiresAt) > Date.now()
      ? pin.value
      : null
  async function read(signal: AbortSignal): Promise<CredentialStatus> {
    const response = await request<{ data: { status: CredentialStatus } }>(
      path,
      session.token,
      'GET',
      undefined,
      signal,
    )
    if (!Object.hasOwn(labels, response.data.status)) throw new Error('Invalid status')
    return response.data.status
  }
  useEffect(() => {
    const run = ++generation.current
    const abort = new AbortController()
    controller.current = abort
    pending.current = true
    // Credential plaintext must be cleared on identity/session/eligibility changes.
    setPin(null)
    setConfirm(null)
    setStatus(null)
    setError('')
    setNotice('')
    setBusy(eligible)
    if (eligible) {
      void read(abort.signal)
        .then((value) => {
          if (generation.current === run) setStatus({ owner: session.token, value })
        })
        .catch((failure) => {
          if (generation.current !== run) return
          if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
          else setError(errorMessage(failure))
        })
        .finally(() => {
          if (generation.current === run) {
            pending.current = false
            setBusy(false)
          }
        })
    } else pending.current = false
    const expires = window.setTimeout(
      () => {
        generation.current++
        abort.abort()
        setPin(null)
        setStatus(null)
        setConfirm(null)
        onExpired()
      },
      Math.max(0, Date.parse(session.expiresAt) - Date.now()),
    )
    const lifecycle = generation
    return () => {
      lifecycle.current++
      abort.abort()
      window.clearTimeout(expires)
    }
    // Operation closures are scoped to the participant and staff session, never to form drafts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [participant.id, eligible, session.token, session.expiresAt, retry, onExpired])
  useEffect(() => {
    if (secret) {
      reveal.current?.focus()
      hadPanel.current = true
    } else if (confirm) {
      confirmation.current?.focus()
      hadPanel.current = true
    } else if (hadPanel.current && !busy) {
      actionButton.current?.focus()
      hadPanel.current = false
    }
  }, [secret, confirm, busy])
  async function act(action: 'issue' | 'reset' | 'revoke') {
    if (pending.current || disabled || !eligible || !current || secret) return
    pending.current = true
    setBusy(true)
    setError('')
    setNotice('')
    setConfirm(null)
    setPin(null)
    const run = generation.current
    let created: string | null = null
    try {
      const signal = controller.current!.signal
      if (action !== 'revoke') created = generatePin()
      await request(
        path,
        session.token,
        action === 'revoke' ? 'DELETE' : 'POST',
        created === null ? undefined : { pin: created },
        signal,
      )
      if (generation.current !== run) return
      const value = await read(signal)
      if (generation.current !== run) return
      setStatus({ owner: session.token, value })
      if (created !== null && value === 'AKTIF')
        setPin({ owner: session.token, value: created })
      else if (created !== null)
        setNotice(
          'Status akses telah berubah. PIN tidak ditampilkan; tinjau status sebelum menerbitkan kembali.',
        )
      else setNotice('Pencabutan selesai. Periksa status akses terkini di atas.')
    } catch (failure) {
      if (generation.current !== run) return
      setPin(null)
      setStatus(null)
      if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
      else
        setError(
          `${errorMessage(failure)} Muat ulang status sebelum mencoba lagi. Jika penerbitan terputus, reset PIN; PIN tidak dapat dipulihkan.`,
        )
    } finally {
      created = null
      if (generation.current === run) {
        pending.current = false
        setBusy(false)
      }
    }
  }
  return (
    <section className="form-section android-access" aria-labelledby="android-access-title">
      <h3 id="android-access-title">Akses Aplikasi Android</h3>
      {!eligible ? (
        <p>
          Hanya partisipan INTERVENSI berstatus AKTIF yang dapat dikelola. Status kredensial
          tidak dimuat.
        </p>
      ) : (
        <>
          <p>
            Status akses Android:{' '}
            <strong>
              {busy ? 'Memuat…' : current ? labels[current] : 'Belum dapat diverifikasi'}
            </strong>
          </p>
          {error && (
            <p role="alert" className="error-text">
              {error}
            </p>
          )}
          {notice && <p role="status">{notice}</p>}
          {secret ? (
            <section
              className="pin-reveal"
              aria-label="PIN Android berhasil diterbitkan"
              tabIndex={-1}
              ref={reveal}
            >
              <h4>PIN Android berhasil diterbitkan</h4>
              <p>Kode responden: {participant.kode}</p>
              <p>
                PIN sementara: <strong className="pin-value">{secret}</strong>
              </p>
              <p>
                Catat dan sampaikan PIN kepada responden melalui prosedur aman. PIN tidak dapat
                ditampilkan kembali setelah jendela ini ditutup.
              </p>
              <button
                type="button"
                className="secondary"
                onClick={() => {
                  setPin(null)
                  setNotice('PIN ditutup dan tidak dapat ditampilkan kembali.')
                }}
              >
                Tutup PIN
              </button>
            </section>
          ) : confirm ? (
            <div
              className="info-note pin-confirm"
              role="group"
              tabIndex={-1}
              ref={confirmation}
              aria-label={
                confirm === 'reset' ? 'Konfirmasi reset PIN' : 'Konfirmasi pencabutan akses'
              }
            >
              <p>
                {confirm === 'reset'
                  ? 'Reset PIN Android? PIN lama dan semua sesi pasien akan dinonaktifkan.'
                  : 'Cabut akses Android? Semua sesi pasien akan dinonaktifkan. Data penelitian tetap tersimpan.'}
              </p>
              <div className="credential-actions">
                <button type="button" className="secondary" onClick={() => setConfirm(null)}>
                  Batal tindakan
                </button>
                <button
                  type="button"
                  className="primary"
                  disabled={busy || disabled}
                  onClick={() => {
                    void act(confirm)
                  }}
                >
                  Ya, {confirm === 'reset' ? 'reset PIN' : 'cabut akses'}
                </button>
              </div>
            </div>
          ) : (
            <div className="credential-actions">
              {!current && !busy && (
                <button
                  type="button"
                  className="secondary"
                  ref={actionButton}
                  onClick={() => setRetry((v) => v + 1)}
                >
                  Muat ulang status Android
                </button>
              )}
              {current && (
                <>
                  <button
                    type="button"
                    className="secondary"
                    disabled={busy || disabled}
                    ref={actionButton}
                    onClick={() => {
                      if (current === 'AKTIF') setConfirm('reset')
                      else void act('issue')
                    }}
                  >
                    {current === 'AKTIF' ? 'Reset PIN Android' : 'Terbitkan PIN Android'}
                  </button>
                  {current === 'AKTIF' && (
                    <button
                      type="button"
                      className="secondary"
                      disabled={busy || disabled}
                      onClick={() => setConfirm('revoke')}
                    >
                      Cabut Akses Android
                    </button>
                  )}
                </>
              )}
            </div>
          )}
          {disabled && (
            <p>
              Simpan atau batalkan perubahan status penelitian sebelum mengelola akses Android.
            </p>
          )}
        </>
      )}
    </section>
  )
}
