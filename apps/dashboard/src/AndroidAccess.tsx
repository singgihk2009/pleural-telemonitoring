import { useEffect, useRef, useState } from 'react'
import { ApiError, errorMessage, request } from './api'
import type { Enrollment, Session } from './api'
import { generatePin } from './pin'

type Snapshot = { status: CredentialStatus; revision: string | null }
const revisionValid = (value: unknown): value is string | null =>
  value === null ||
  (typeof value === 'string' &&
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value))

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
  onPendingChange,
}: {
  participant: Enrollment
  session: Session
  disabled: boolean
  onExpired: () => void
  onPendingChange?: (pending: boolean) => void
}) {
  // Changing participant remounts all secret/operation state, including pending promises.
  return (
    <CredentialPanel
      key={participant.id}
      participant={participant}
      session={session}
      disabled={disabled}
      onExpired={onExpired}
      onPendingChange={onPendingChange}
    />
  )
}
function CredentialPanel({
  participant,
  session,
  disabled,
  onExpired,
  onPendingChange,
}: {
  participant: Enrollment
  session: Session
  disabled: boolean
  onExpired: () => void
  onPendingChange?: (pending: boolean) => void
}) {
  const eligible = participant.kelompok === 'INTERVENSI' && participant.status === 'AKTIF'
  const [status, setStatus] = useState<{
    owner: string
    value: CredentialStatus
    revision: string | null
  } | null>(null)
  const [pin, setPin] = useState<{ owner: string; value: string; revision: string } | null>(
    null,
  )
  const [confirm, setConfirm] = useState<'reset' | 'revoke' | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [retry, setRetry] = useState(0)
  const [mutating, setMutating] = useState(false)
  const [verified, setVerified] = useState(false)
  const [verifiedAt, setVerifiedAt] = useState('')
  const verifyAgain = useRef<() => void>(() => undefined)
  const pending = useRef(false)
  const generation = useRef(0)
  const controller = useRef<AbortController | null>(null)
  const reveal = useRef<HTMLElement>(null)
  const confirmation = useRef<HTMLDivElement>(null)
  const actionButton = useRef<HTMLButtonElement>(null)
  const hadPanel = useRef(false)
  const path = `/participants/${encodeURIComponent(participant.id)}/credentials`
  const current = status?.owner === session.token ? status.value : null
  const currentPin = pin?.owner === session.token ? pin : null
  const secret =
    currentPin &&
    verified &&
    document.visibilityState !== 'hidden' &&
    status?.revision === currentPin.revision &&
    Date.parse(session.expiresAt) > Date.now()
      ? currentPin.value
      : null
  async function read(signal: AbortSignal): Promise<Snapshot> {
    const response = await request<{ data: Snapshot }>(
      path,
      session.token,
      'GET',
      undefined,
      signal,
    )
    if (!Object.hasOwn(labels, response.data.status) || !revisionValid(response.data.revision))
      throw new Error('Invalid status')
    return response.data
  }
  useEffect(() => {
    const run = ++generation.current
    const abort = new AbortController()
    controller.current = abort
    pending.current = true
    // Credential plaintext must be cleared on identity/session/eligibility changes.
    setPin(null)
    setVerified(false)
    setConfirm(null)
    setStatus(null)
    setError('')
    setNotice('')
    setBusy(eligible)
    if (eligible) {
      void read(abort.signal)
        .then((value) => {
          if (generation.current === run)
            setStatus({ owner: session.token, value: value.status, revision: value.revision })
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
        setMutating(false)
        onPendingChange?.(false)
        onExpired()
      },
      Math.max(0, Date.parse(session.expiresAt) - Date.now()),
    )
    const lifecycle = generation
    return () => {
      lifecycle.current++
      abort.abort()
      onPendingChange?.(false)
      window.clearTimeout(expires)
    }
    // Operation closures are scoped to the participant and staff session, never to form drafts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    participant.id,
    eligible,
    session.token,
    session.expiresAt,
    retry,
    onExpired,
    onPendingChange,
  ])
  useEffect(() => {
    if (currentPin) {
      reveal.current?.focus()
      hadPanel.current = true
    } else if (confirm) {
      confirmation.current?.focus()
      hadPanel.current = true
    } else if (hadPanel.current && !busy) {
      actionButton.current?.focus()
      hadPanel.current = false
    }
  }, [currentPin, confirm, busy])
  useEffect(() => {
    if (!mutating) return
    const warn = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [mutating])
  useEffect(() => {
    if (!pin || pin.owner !== session.token) return
    const operation = pin.revision
    const run = generation.current
    let check = 0
    let requestController: AbortController | null = null
    const verify = async () => {
      const call = ++check
      requestController?.abort()
      const abort = new AbortController()
      requestController = abort
      setVerified(false)
      if (document.visibilityState === 'hidden') return
      try {
        const snapshot = await read(abort.signal)
        if (run !== generation.current || call !== check) return
        if (snapshot.status !== 'AKTIF' || snapshot.revision !== operation) {
          setPin(null)
          setStatus(null)
          setError(
            'PIN operasi ini sudah diganti atau akses dicabut. PIN tidak ditampilkan. Muat ulang status, lalu reset dengan konfirmasi.',
          )
          return
        }
        setStatus({
          owner: session.token,
          value: snapshot.status,
          revision: snapshot.revision,
        })
        setVerifiedAt(new Date().toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta' }))
        setVerified(true)
      } catch (failure) {
        if (run !== generation.current || call !== check || abort.signal.aborted) return
        setPin(null)
        setStatus(null)
        if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
        else
          setError(
            'Revisi PIN tidak dapat diverifikasi. Muat ulang status dan reset PIN; permintaan sebelumnya mungkin telah tersimpan di server.',
          )
      }
    }
    verifyAgain.current = () => {
      void verify()
    }
    const timer = window.setInterval(() => {
      void verify()
    }, 5000)
    const focus = () => {
      void verify()
    }
    window.addEventListener('focus', focus)
    document.addEventListener('visibilitychange', focus)
    return () => {
      check++
      requestController?.abort()
      window.clearInterval(timer)
      window.removeEventListener('focus', focus)
      document.removeEventListener('visibilitychange', focus)
      verifyAgain.current = () => undefined
    }
    // Check the exact issuance receipt, never just AKTIF; session/participant changes invalidate checks.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pin?.revision, session.token, onExpired])
  async function act(action: 'issue' | 'reset' | 'revoke') {
    if (pending.current || disabled || !eligible || !current || currentPin) return
    pending.current = true
    onPendingChange?.(true)
    setMutating(true)
    setVerified(false)
    setBusy(true)
    setError('')
    setNotice('')
    setConfirm(null)
    setPin(null)
    const run = generation.current
    const operationController = controller.current!
    const timeout = window.setTimeout(() => operationController.abort(), 30000)
    let created: string | null = null
    try {
      const signal = operationController.signal
      if (action !== 'revoke') created = generatePin()
      const receipt = await request<{ data: { revision: string } }>(
        path,
        session.token,
        action === 'revoke' ? 'DELETE' : 'POST',
        { ...(created === null ? {} : { pin: created }), expected_revision: status!.revision },
        signal,
      )
      if (generation.current !== run) return
      const value = await read(signal)
      if (generation.current !== run) return
      setStatus({ owner: session.token, value: value.status, revision: value.revision })
      const revision = receipt.data?.revision
      if (!revision || !revisionValid(revision)) throw new Error('Invalid operation receipt')
      if (created !== null) {
        if (value.status !== 'AKTIF' || value.revision !== revision) {
          setStatus(null)
          setError(
            'PIN operasi ini sudah diganti atau akses dicabut. PIN tidak ditampilkan. Muat ulang status, lalu reset dengan konfirmasi.',
          )
          return
        }
        setPin({ owner: session.token, value: created, revision })
        setVerifiedAt(new Date().toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta' }))
        setVerified(true)
      } else setNotice('Pencabutan selesai. Periksa status akses terkini di atas.')
    } catch (failure) {
      if (generation.current !== run) return
      setPin(null)
      setStatus(null)
      if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
      else if (failure instanceof ApiError && failure.status === 409)
        setError(
          'Revisi kredensial sudah berubah. PIN operasi ini tidak ditampilkan. Muat ulang status, lalu reset dengan konfirmasi.',
        )
      else
        setError(
          `${errorMessage(failure)} Permintaan mungkin sudah tersimpan di server; browser berhenti menunggu bukan konfirmasi pembatalan. Muat ulang status, lalu reset PIN dengan konfirmasi jika respons penerbitan hilang.`,
        )
    } finally {
      window.clearTimeout(timeout)
      created = null
      if (generation.current === run) {
        pending.current = false
        setMutating(false)
        onPendingChange?.(false)
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
          <p>
            Jika editor ditutup paksa, partisipan diganti, atau Anda keluar saat permintaan
            berlangsung, operasi mungkin telah tersimpan. Muat ulang status dan reset PIN
            dengan konfirmasi; PIN tidak dapat dipulihkan.
          </p>
          {mutating && (
            <p role="status">
              Sedang memproses akses Android. Tunggu konfirmasi sebelum menutup editor.
            </p>
          )}
          {currentPin ? (
            <section
              className="pin-reveal"
              aria-label="PIN Android berhasil diterbitkan"
              tabIndex={-1}
              ref={reveal}
            >
              <h4>PIN Android berhasil diterbitkan</h4>
              <p>Kode responden: {participant.kode}</p>
              {secret ? (
                <>
                  <p>
                    PIN sementara: <strong className="pin-value">{secret}</strong>
                  </p>
                  <p>
                    Hasil pemeriksaan revisi diterima pukul {verifiedAt} WIB. Peneliti lain
                    dapat mengubah akses setelah pemeriksaan; verifikasi ulang sebelum
                    menyampaikan PIN.
                  </p>
                </>
              ) : (
                <p role="status">
                  PIN disembunyikan sampai revisi operasi diverifikasi ulang.
                </p>
              )}
              <button
                type="button"
                className="secondary"
                onClick={() => verifyAgain.current()}
              >
                Verifikasi ulang PIN
              </button>
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
