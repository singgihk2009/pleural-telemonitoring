import { useEffect, useRef, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { ApiError, errorMessage, request } from './api'
import type { Enrollment, Group, Session, Status } from './api'
import { Icon } from './icons'

type Fields = {
  kode: string
  kelompok: Group
  tanggal_pulang: string
  vas_baseline_mm: string
  tanggal_consent: string
  versi_consent: string
  status: Status
  alasan_drop_out: string
  initials: string
  patient_contact: string
  family_contact: string
}
const empty: Fields = {
  kode: '',
  kelompok: 'INTERVENSI',
  tanggal_pulang: '',
  vas_baseline_mm: '',
  tanggal_consent: '',
  versi_consent: '',
  status: 'AKTIF',
  alasan_drop_out: '',
  initials: '',
  patient_contact: '',
  family_contact: '',
}
function Field({
  label,
  id,
  error,
  children,
  note,
}: {
  label: string
  id: string
  error?: string
  children: ReactNode
  note?: string
}) {
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      {children}
      {note && <small>{note}</small>}
      {error && (
        <p className="field-error" id={`${id}-error`}>
          {error}
        </p>
      )}
    </div>
  )
}
export function EnrollmentForm({
  session,
  participantId,
  onClose,
  onSaved,
  onExpired,
}: {
  session: Session
  participantId: string | null
  onClose: () => void
  onSaved: () => void
  onExpired: () => void
}) {
  const [fields, setFields] = useState<Fields>(empty)
  const [loaded, setLoaded] = useState<{
    key: string
    data: Enrollment | null
    error: string
  } | null>(null)
  const [saving, setSaving] = useState(false)
  const [submitError, setError] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [retry, setRetry] = useState(0)
  const loadKey = `${participantId || 'new'}:${retry}`
  const current = loaded?.key === loadKey ? loaded : null
  const record = current?.data ?? null
  const loading = participantId !== null && current === null
  const error = submitError || current?.error || ''
  const modal = useRef<HTMLDivElement>(null)
  const mounted = useRef(true)
  useEffect(() => {
    mounted.current = true
    return () => {
      mounted.current = false
    }
  }, [])
  useEffect(() => {
    const previous = document.activeElement
    const dialog = modal.current
    dialog?.focus()
    const trap = (event: KeyboardEvent) => {
      if (event.key !== 'Tab') return
      const controls = dialog?.querySelectorAll<HTMLElement>(
        'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), summary',
      )
      if (!controls?.length) return
      const first = controls[0]
      const last = controls[controls.length - 1]
      if (
        event.shiftKey &&
        (document.activeElement === first || document.activeElement === dialog)
      ) {
        last.focus()
        event.preventDefault()
      } else if (!event.shiftKey && document.activeElement === last) {
        first.focus()
        event.preventDefault()
      }
    }
    dialog?.addEventListener('keydown', trap)
    return () => {
      dialog?.removeEventListener('keydown', trap)
      if (previous instanceof HTMLElement && document.contains(previous)) previous.focus()
    }
  }, [])
  useEffect(() => {
    if (!participantId) return
    const controller = new AbortController()
    request<{ data: Enrollment }>(
      `/research/participants/${encodeURIComponent(participantId)}`,
      session.token,
      'GET',
      undefined,
      controller.signal,
    )
      .then(({ data }) => {
        if (controller.signal.aborted) return
        setLoaded({ key: loadKey, data, error: '' })
        setFields({
          kode: data.kode,
          kelompok: data.kelompok,
          tanggal_pulang: data.tanggal_pulang,
          vas_baseline_mm: String(data.vas_baseline_mm),
          tanggal_consent: data.tanggal_consent,
          versi_consent: data.versi_consent,
          status: data.status,
          alasan_drop_out: data.alasan_drop_out || '',
          initials: data.contact?.initials || '',
          patient_contact: data.contact?.patient_contact || '',
          family_contact: data.contact?.family_contact || '',
        })
      })
      .catch((failure) => {
        if (!controller.signal.aborted) {
          if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
          else setLoaded({ key: loadKey, data: null, error: errorMessage(failure) })
        }
      })
    return () => controller.abort()
  }, [participantId, session.token, onExpired, retry, loadKey])
  const change = (name: keyof Fields, value: string, errorKey: string = name) => {
    setFields((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [errorKey]: '' }))
  }
  const attr = (name: keyof Fields, serverName = name as string) => ({
    id: name,
    value: fields[name],
    onChange: (event: { target: { value: string } }) =>
      change(name, event.target.value, serverName),
    'aria-invalid': Boolean(errors[serverName]),
    'aria-describedby': errors[serverName] ? `${name}-error` : undefined,
  })
  async function submit(event: FormEvent) {
    event.preventDefault()
    const invalid: Record<string, string> = {}
    for (const key of ['kode', 'tanggal_pulang', 'tanggal_consent', 'versi_consent'] as const)
      if (!fields[key].trim()) invalid[key] = 'Isian ini wajib diisi.'
    const vas = Number(fields.vas_baseline_mm)
    if (!fields.vas_baseline_mm.trim() || !Number.isInteger(vas) || vas < 0 || vas > 100)
      invalid.vas_baseline_mm = 'Masukkan bilangan bulat 0–100 mm.'
    if (fields.status === 'DROP_OUT' && !fields.alasan_drop_out.trim())
      invalid.alasan_drop_out = 'Alasan wajib diisi untuk DROP_OUT.'
    if (Object.keys(invalid).length) {
      setErrors(invalid)
      setError('Periksa kembali isian yang ditandai.')
      return
    }
    setSaving(true)
    setError('')
    setErrors({})
    const contactValues = {
      initials: fields.initials.trim() || null,
      patient_contact: fields.patient_contact.trim() || null,
      family_contact: fields.family_contact.trim() || null,
    }
    const contactChanged =
      !participantId ||
      (Object.keys(contactValues) as (keyof typeof contactValues)[]).some(
        (key) => contactValues[key] !== (record?.contact?.[key] ?? null),
      )
    const values = {
      tanggal_pulang: fields.tanggal_pulang,
      vas_baseline_mm: vas,
      tanggal_consent: fields.tanggal_consent,
      versi_consent: fields.versi_consent.trim(),
      status: fields.status,
      alasan_drop_out: fields.status === 'DROP_OUT' ? fields.alasan_drop_out.trim() : null,
      ...(contactChanged ? { contact: contactValues } : {}),
    }
    try {
      await request(
        participantId
          ? `/research/participants/${encodeURIComponent(participantId)}`
          : '/research/participants',
        session.token,
        participantId ? 'PATCH' : 'POST',
        participantId
          ? { ...values, expected_updated_at: record?.updated_at }
          : { ...values, kode: fields.kode.trim(), kelompok: fields.kelompok },
      )
      if (mounted.current) onSaved()
    } catch (failure) {
      if (!mounted.current) return
      if (failure instanceof ApiError && [401, 403].includes(failure.status)) {
        onExpired()
        return
      }
      setError(errorMessage(failure))
      if (failure instanceof ApiError)
        setErrors(
          Object.fromEntries(
            Object.entries(failure.fields).map(([name, messages]) => [name, messages[0]]),
          ),
        )
    } finally {
      if (mounted.current) setSaving(false)
    }
  }
  const locked = record?.baseline_locked || false
  return (
    <div className="modal-backdrop">
      <div
        className="enrollment-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="editor-title"
        tabIndex={-1}
        ref={modal}
        onKeyDown={(event) => {
          if (event.key === 'Escape' && !saving) onClose()
        }}
      >
        <header className="modal-head">
          <div>
            <span className="eyebrow">PENDAFTARAN STUDI</span>
            <h2 id="editor-title">
              {participantId ? 'Ubah data partisipan' : 'Daftarkan partisipan'}
            </h2>
            <p>
              {participantId
                ? 'Tinjau baseline, persetujuan, dan status penelitian.'
                : 'Catat persetujuan dan baseline sebelum pemantauan dimulai.'}
            </p>
          </div>
          <button
            className="icon-button"
            onClick={onClose}
            disabled={saving}
            aria-label="Tutup editor"
          >
            <Icon name="close" />
          </button>
        </header>
        {loading ? (
          <div className="modal-loading" role="status">
            Memuat data partisipan…
          </div>
        ) : participantId && !record ? (
          <div className="modal-loading">
            <p role="alert">{error}</p>
            <button className="secondary" onClick={() => setRetry((value) => value + 1)}>
              Coba lagi
            </button>
          </div>
        ) : (
          <form onSubmit={submit} noValidate>
            <div className="form-scroll">
              {error && (
                <div className="error-banner" role="alert">
                  {error}
                </div>
              )}
              <section className="form-section">
                <h3>
                  <span>01</span> Identitas penelitian
                </h3>
                <p>Gunakan kode studi, bukan nama atau nomor identitas pasien.</p>
                <div className="form-grid">
                  <Field
                    id="kode"
                    label="Kode responden *"
                    error={errors.kode}
                    note={
                      participantId ? 'Kode tetap setelah pendaftaran.' : 'Maksimal 32 karakter.'
                    }
                  >
                    <input
                      {...attr('kode')}
                      maxLength={32}
                      disabled={participantId !== null}
                      placeholder="Kode studi"
                      autoComplete="off"
                    />
                  </Field>
                  <Field
                    id="kelompok"
                    label="Kelompok penelitian *"
                    error={errors.kelompok}
                    note="Kelompok tidak dapat diubah setelah tersimpan."
                  >
                    <select {...attr('kelompok')} disabled={participantId !== null}>
                      <option value="INTERVENSI">INTERVENSI</option>
                      <option value="KONTROL">KONTROL</option>
                    </select>
                  </Field>
                </div>
                {fields.kelompok === 'KONTROL' && (
                  <div className="info-note">
                    <Icon name="lock" size={16} /> Kelompok kontrol tidak memperoleh kredensial
                    aplikasi pasien.
                  </div>
                )}
              </section>
              <section className="form-section">
                <h3>
                  <span>02</span> Baseline & persetujuan
                </h3>
                {locked && (
                  <div className="info-note">
                    <Icon name="lock" size={16} /> Baseline dan hari 0 terkunci karena sudah ada
                    catatan penelitian.
                  </div>
                )}
                <div className="form-grid">
                  <Field
                    id="tanggal_pulang"
                    label="Tanggal pulang (hari 0) *"
                    error={errors.tanggal_pulang}
                  >
                    <input {...attr('tanggal_pulang')} type="date" disabled={locked} />
                  </Field>
                  <Field
                    id="vas_baseline_mm"
                    label="VAS baseline *"
                    error={errors.vas_baseline_mm}
                    note="Bilangan bulat 0–100 mm."
                  >
                    <div className="input-unit">
                      <input
                        {...attr('vas_baseline_mm')}
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={100}
                        step={1}
                        disabled={locked}
                        placeholder="0–100"
                      />
                      <span>mm</span>
                    </div>
                  </Field>
                  <Field
                    id="tanggal_consent"
                    label="Tanggal persetujuan *"
                    error={errors.tanggal_consent}
                  >
                    <input {...attr('tanggal_consent')} type="date" />
                  </Field>
                  <Field
                    id="versi_consent"
                    label="Versi dokumen persetujuan *"
                    error={errors.versi_consent}
                  >
                    <input
                      {...attr('versi_consent')}
                      maxLength={40}
                      placeholder="Versi dokumen"
                      autoComplete="off"
                    />
                  </Field>
                </div>
              </section>
              <details className="contact-section" open={Boolean(record?.contact)}>
                <summary>
                  <span>
                    Kontak pasien & keluarga <small>Opsional</small>
                  </span>
                  <span>+</span>
                </summary>
                <p>Kontak disimpan terenkripsi dan hanya dapat dibaca oleh akun berwenang.</p>
                <div className="form-grid">
                  <Field id="initials" label="Inisial pasien" error={errors['contact.initials']}>
                    <input
                      {...attr('initials', 'contact.initials')}
                      maxLength={20}
                      autoComplete="off"
                    />
                  </Field>
                  <Field
                    id="patient_contact"
                    label="Kontak pasien"
                    error={errors['contact.patient_contact']}
                  >
                    <input
                      {...attr('patient_contact', 'contact.patient_contact')}
                      maxLength={40}
                      autoComplete="off"
                    />
                  </Field>
                  <Field
                    id="family_contact"
                    label="Kontak keluarga"
                    error={errors['contact.family_contact']}
                  >
                    <input
                      {...attr('family_contact', 'contact.family_contact')}
                      maxLength={40}
                      autoComplete="off"
                    />
                  </Field>
                </div>
              </details>
              <section className="form-section status-section">
                <h3>
                  <span>03</span> Status partisipan
                </h3>
                <div className="form-grid">
                  <Field id="status" label="Status penelitian" error={errors.status}>
                    <select {...attr('status')}>
                      <option value="AKTIF">AKTIF</option>
                      <option value="SELESAI">SELESAI</option>
                      <option value="DROP_OUT">DROP_OUT</option>
                    </select>
                  </Field>
                  {fields.status === 'DROP_OUT' && (
                    <Field
                      id="alasan_drop_out"
                      label="Alasan DROP_OUT *"
                      error={errors.alasan_drop_out}
                      note="Jangan sertakan nama atau nomor kontak."
                    >
                      <textarea {...attr('alasan_drop_out')} maxLength={1000} rows={3} />
                    </Field>
                  )}
                </div>
              </section>
            </div>
            <footer className="modal-foot">
              <small>* Wajib diisi</small>
              <div>
                <button type="button" className="secondary" onClick={onClose} disabled={saving}>
                  Batal
                </button>
                <button className="primary" disabled={saving}>
                  {saving
                    ? 'Menyimpan…'
                    : participantId
                      ? 'Simpan perubahan'
                      : 'Daftarkan partisipan'}
                </button>
              </div>
            </footer>
          </form>
        )}
      </div>
    </div>
  )
}
