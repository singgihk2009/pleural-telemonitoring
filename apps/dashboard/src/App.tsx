import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, errorMessage, request } from './api'
import type { Group, Participant, Results, Session, Status } from './api'
import { InstrumentReview } from './InstrumentReview'
import { EnrollmentForm } from './EnrollmentForm'
import { Brand, Icon } from './icons'
import './App.css'

const labels: Record<Status, string> = { AKTIF: 'Aktif', SELESAI: 'Selesai', DROP_OUT: 'Drop-out' }
function date(value: string) {
  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00+07:00`))
}
function Login({ onSignedIn, notice }: { onSignedIn: (session: Session) => void; notice: string }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  async function login(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')
    let token: string | null = null
    try {
      const response = await request<{ access_token: string; expires_at: string }>(
        '/auth/staff/login',
        null,
        'POST',
        { email: email.trim(), password },
      )
      token = response.access_token
      const access = await request<{ data: { can_manage_enrollment: boolean } }>(
        '/research/access',
        token,
      )
      if (!access.data.can_manage_enrollment) throw new ApiError(403)
      onSignedIn({ token, expiresAt: response.expires_at })
    } catch (failure) {
      if (token) await request('/auth/logout', token, 'POST').catch(() => undefined)
      setError(
        failure instanceof ApiError && failure.status === 401
          ? 'Email atau kata sandi tidak sesuai.'
          : errorMessage(failure),
      )
    } finally {
      setPassword('')
      setBusy(false)
    }
  }
  return (
    <main className="login-page">
      <section className="login-story">
        <Brand />
        <div>
          <span className="eyebrow">PENELITIAN TELENAPAS</span>
          <h1>
            Penelitian yang tertata.
            <br />
            <em>Pemantauan yang terarah.</em>
          </h1>
          <p>Ruang kerja peneliti untuk pendaftaran partisipan, persetujuan, dan baseline studi.</p>
          <div className="story-note">
            <Icon name="lock" />
            <span>
              Akses terbatas untuk peneliti berwenang.
              <br />
              Data studi dan kontak dikelola secara terpisah.
            </span>
          </div>
        </div>
        <small>Prototipe penelitian · Telemonitoring efusi pleura</small>
      </section>
      <section className="login-panel">
        <form className="login-card" onSubmit={login}>
          <span className="login-symbol">
            <Icon name="users" size={27} />
          </span>
          <span className="eyebrow">RUANG KERJA PENELITI</span>
          <h2>Selamat datang kembali</h2>
          <p>Masuk dengan akun staf penelitian Anda.</p>
          {notice && (
            <div className="info-note" role="status">
              {notice}
            </div>
          )}
          {error && (
            <div className="error-banner" role="alert">
              {error}
            </div>
          )}
          <div className="field">
            <label htmlFor="email">Email staf</label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              value={email}
              required
              maxLength={190}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="Email penelitian"
            />
          </div>
          <div className="field">
            <label htmlFor="password">Kata sandi</label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </div>
          <button className="primary login-submit" disabled={busy}>
            {busy ? 'Memverifikasi…' : 'Masuk ke ruang penelitian'}
            <Icon name="arrow" size={17} />
          </button>
          <p className="login-help">
            Akun diterbitkan oleh pengelola studi.
            <br />
            Hubungi pengelola jika Anda memerlukan akses.
          </p>
        </form>
        <small className="login-footer">
          <Icon name="lock" size={14} /> Privasi penelitian · Akses hanya untuk tim berwenang
        </small>
      </section>
    </main>
  )
}
function Workspace({
  session,
  onExpired,
  onLogout,
}: {
  session: Session
  onExpired: () => void
  onLogout: () => Promise<void>
}) {
  const [response, setResponse] = useState<{
    key: string
    results?: Results
    error?: string
    loadedAt?: string
  } | null>(null)
  const [draftSearch, setDraftSearch] = useState('')
  const [search, setSearch] = useState('')
  const [group, setGroup] = useState<Group | ''>('')
  const [status, setStatus] = useState<Status | ''>('')
  const [page, setPage] = useState(1)
  const [reload, setReload] = useState(0)
  const [editor, setEditor] = useState<{ id: string | null } | null>(null)
  const [instrumentOpen, setInstrumentOpen] = useState(false)
  const [toast, setToast] = useState('')
  const [leaving, setLeaving] = useState(false)
  const queryKey = JSON.stringify([session.token, search, group, status, page, reload])
  const current = response?.key === queryKey ? response : null
  const results = current?.results ?? null
  const loading = current === null
  const error = current?.error || ''
  const loadedAt = current?.loadedAt || ''
  useEffect(() => {
    const controller = new AbortController()
    request<Results>(
      '/research/participants/search',
      session.token,
      'POST',
      { search: search || null, group: group || null, status: status || null, page },
      controller.signal,
    )
      .then((data) => {
        if (controller.signal.aborted) return
        if (data.meta.current_page > data.meta.last_page) {
          setPage(data.meta.last_page)
          return
        }
        setResponse({
          key: queryKey,
          results: data,
          loadedAt: new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
          }).format(new Date()),
        })
      })
      .catch((failure) => {
        if (!controller.signal.aborted) {
          if (failure instanceof ApiError && [401, 403].includes(failure.status)) onExpired()
          else setResponse({ key: queryKey, error: errorMessage(failure) })
        }
      })
    return () => controller.abort()
  }, [session.token, search, group, status, page, reload, onExpired, queryKey])
  useEffect(() => {
    if (!toast) return
    const timer = window.setTimeout(() => setToast(''), 6000)
    return () => window.clearTimeout(timer)
  }, [toast])
  const filtered = Boolean(search || group || status)
  const total = results?.meta.total ?? 0
  const metrics = [
    {
      label: 'Total partisipan',
      value: results?.meta.total,
      tone: 'purple',
      detail: filtered ? 'Sesuai filter aktif' : 'Terdaftar dalam studi',
    },
    {
      label: 'Kelompok intervensi',
      value: results?.meta.intervensi,
      tone: 'teal',
      detail: 'Pencatatan melalui aplikasi',
    },
    {
      label: 'Kelompok kontrol',
      value: results?.meta.kontrol,
      tone: 'blue',
      detail: 'Pencatatan oleh peneliti',
    },
    {
      label: 'Partisipan aktif',
      value: results?.meta.aktif,
      tone: 'amber',
      detail: 'Status penelitian aktif',
    },
  ]
  function resetFilters() {
    setDraftSearch('')
    setSearch('')
    setGroup('')
    setStatus('')
    setPage(1)
  }
  function saved() {
    setToast(
      editor?.id ? 'Perubahan partisipan berhasil disimpan.' : 'Partisipan berhasil didaftarkan.',
    )
    setEditor(null)
    setPage(1)
    setReload((value) => value + 1)
  }
  return (
    <>
      <div className="app-shell" inert={editor !== null || instrumentOpen}>
        <aside className="sidebar">
          <Brand />
          <span className="nav-caption">RUANG PENELITIAN</span>
          <div className="nav-active">
            <Icon name="users" />
            <span>Partisipan</span>
            <Icon name="arrow" size={15} />
          </div>
          <button type="button" className="instrument-nav" onClick={()=>setInstrumentOpen(true)}>Review instrumen harian</button>
          <div className="study-info">
            <Icon name="book" />
            <div>
              <strong>Studi TeleNapas</strong>
              <p>
                Enrollment & baseline
                <br />
                Hari 0 penelitian
              </p>
            </div>
          </div>
          <div className="sidebar-bottom">
            <span className="study-dot" /> Lingkungan penelitian
            <small>Gunakan data sintetis untuk pengujian.</small>
          </div>
        </aside>
        <div className="workspace">
          <header className="topbar">
            <span>
              Studi TeleNapas <span className="crumb-separator">/</span> <strong>Partisipan</strong>
            </span>
            <div className="user-tools">
              <span className="access-label">
                <Icon name="lock" size={14} /> Akses peneliti
              </span>
              <span className="avatar">PU</span>
              <button
                className="logout-button"
                disabled={leaving}
                onClick={async () => {
                  setLeaving(true)
                  await onLogout()
                }}
              >
                <Icon name="logout" size={16} />
                {leaving ? 'Keluar…' : 'Keluar'}
              </button>
            </div>
          </header>
          <main className="dashboard-content">
            <section className="page-heading">
              <div>
                <span className="eyebrow">ENROLLMENT & BASELINE</span>
                <h1>Partisipan penelitian</h1>
                <p>Kelola pendaftaran, persetujuan, dan baseline studi TeleNapas.</p>
              </div>
              <button className="primary" onClick={() => setEditor({ id: null })}>
                <Icon name="plus" size={18} /> Daftarkan partisipan
              </button>
            </section>
            {toast && (
              <div className="success-banner" role="status">
                <Icon name="check" />
                {toast}
              </div>
            )}
            <section className="metrics" aria-label="Ringkasan partisipan">
              {metrics.map((metric) => (
                <article className="metric" key={metric.label}>
                  <div>
                    <span className={`metric-dot ${metric.tone}`} />
                    <span>{metric.label}</span>
                  </div>
                  <strong aria-live="polite">
                    {metric.value === undefined ? '—' : metric.value.toLocaleString('id-ID')}
                  </strong>
                  <small>{metric.detail}</small>
                </article>
              ))}
            </section>
            <section className="participants-card">
              <div className="card-heading">
                <div className="card-title">
                  <span className="title-icon">
                    <Icon name="users" size={22} />
                  </span>
                  <div>
                    <h2>Daftar partisipan</h2>
                    <p>Data pendaftaran dan baseline penelitian</p>
                  </div>
                </div>
                <button
                  className="icon-button"
                  title="Muat ulang daftar"
                  aria-label="Muat ulang daftar"
                  onClick={() => setReload((value) => value + 1)}
                  disabled={loading}
                >
                  <Icon name="refresh" />
                </button>
              </div>
              <div className="filter-bar">
                <form
                  className="search-box"
                  onSubmit={(event) => {
                    event.preventDefault()
                    setSearch(draftSearch.trim())
                    setPage(1)
                  }}
                >
                  <Icon name="search" size={18} />
                  <input
                    aria-label="Cari kode responden"
                    value={draftSearch}
                    onChange={(event) => setDraftSearch(event.target.value)}
                    placeholder="Cari kode responden…"
                    maxLength={32}
                    autoComplete="off"
                  />
                  <button type="submit">Cari</button>
                </form>
                <div className="select-filter">
                  <label htmlFor="group-filter">Kelompok</label>
                  <select
                    id="group-filter"
                    value={group}
                    onChange={(event) => {
                      setGroup(event.target.value as Group | '')
                      setPage(1)
                    }}
                  >
                    <option value="">Semua kelompok</option>
                    <option value="INTERVENSI">Intervensi</option>
                    <option value="KONTROL">Kontrol</option>
                  </select>
                </div>
                <div className="select-filter">
                  <label htmlFor="status-filter">Status</label>
                  <select
                    id="status-filter"
                    value={status}
                    onChange={(event) => {
                      setStatus(event.target.value as Status | '')
                      setPage(1)
                    }}
                  >
                    <option value="">Semua status</option>
                    <option value="AKTIF">Aktif</option>
                    <option value="SELESAI">Selesai</option>
                    <option value="DROP_OUT">Drop-out</option>
                  </select>
                </div>
                {filtered && (
                  <button className="text-button" onClick={resetFilters}>
                    Reset filter
                  </button>
                )}
              </div>
              {loading ? (
                <div className="table-state" role="status">
                  <span className="spinner" />
                  Memuat partisipan…
                </div>
              ) : error ? (
                <div className="table-state">
                  <p className="error-text" role="alert">
                    {error}
                  </p>
                  <button className="secondary" onClick={() => setReload((value) => value + 1)}>
                    Coba lagi
                  </button>
                </div>
              ) : results && results.data.length > 0 ? (
                <div className="table-scroll">
                  <table>
                    <thead>
                      <tr>
                        <th>Kode responden</th>
                        <th>Kelompok</th>
                        <th>Tanggal pulang</th>
                        <th>VAS baseline</th>
                        <th>Persetujuan</th>
                        <th>Status</th>
                        <th>
                          <span className="sr-only">Tindakan</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {results.data.map((participant: Participant) => (
                        <tr key={participant.id}>
                          <td>
                            <button
                              className="code-link"
                              onClick={() => setEditor({ id: participant.id })}
                            >
                              {participant.kode}
                            </button>
                            <small className="cell-note">Kode penelitian</small>
                          </td>
                          <td>
                            <span className={`group-badge ${participant.kelompok.toLowerCase()}`}>
                              <span />
                              {participant.kelompok === 'INTERVENSI' ? 'Intervensi' : 'Kontrol'}
                            </span>
                          </td>
                          <td>
                            {date(participant.tanggal_pulang)}
                            <small className="cell-note">Hari 0</small>
                          </td>
                          <td>
                            <span className="vas-value">{participant.vas_baseline_mm}</span>
                            <span className="unit-label"> mm</span>
                          </td>
                          <td>
                            {date(participant.tanggal_consent)}
                            <small className="cell-note">Versi {participant.versi_consent}</small>
                          </td>
                          <td>
                            <span className={`status-badge ${participant.status.toLowerCase()}`}>
                              {labels[participant.status]}
                            </span>
                          </td>
                          <td>
                            <button
                              className="row-edit"
                              aria-label={`Ubah partisipan ${participant.kode}`}
                              onClick={() => setEditor({ id: participant.id })}
                            >
                              Ubah <Icon name="arrow" size={14} />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="table-state empty-state">
                  <span className="empty-symbol">
                    <Icon name="users" size={31} />
                  </span>
                  <h3>{filtered ? 'Tidak ada hasil yang sesuai' : 'Belum ada partisipan'}</h3>
                  <p>
                    {filtered
                      ? 'Coba ubah kode pencarian atau filter yang digunakan.'
                      : 'Daftarkan partisipan pertama untuk memulai pencatatan studi.'}
                  </p>
                  <button
                    className="secondary"
                    onClick={filtered ? resetFilters : () => setEditor({ id: null })}
                  >
                    {filtered ? 'Reset filter' : 'Daftarkan partisipan pertama'}
                  </button>
                </div>
              )}
              <div className="table-footer">
                <span>
                  {results && total > 0
                    ? `Menampilkan ${(page - 1) * 25 + 1}–${Math.min(page * 25, total)} dari ${total} partisipan`
                    : '25 partisipan per halaman'}
                </span>
                <div>
                  <button
                    className="page-button"
                    aria-label="Halaman sebelumnya"
                    disabled={loading || page <= 1}
                    onClick={() => setPage((value) => value - 1)}
                  >
                    ‹
                  </button>
                  <span>
                    Halaman {page} dari {results?.meta.last_page ?? 1}
                  </span>
                  <button
                    className="page-button"
                    aria-label="Halaman berikutnya"
                    disabled={loading || !results || page >= results.meta.last_page}
                    onClick={() => setPage((value) => value + 1)}
                  >
                    ›
                  </button>
                </div>
              </div>
            </section>
            <footer className="workspace-footer">
              <span>
                <Icon name="lock" size={13} /> Data kontak tidak ditampilkan dalam daftar
                partisipan.
              </span>
              <span>{loadedAt ? `Dimuat ${loadedAt} WIB` : 'Waktu studi: Asia/Jakarta'}</span>
            </footer>
          </main>
        </div>
      </div>
      {instrumentOpen && <InstrumentReview session={session} onExpired={onExpired} onClose={()=>setInstrumentOpen(false)} onLogout={onLogout}/> }
      {editor && (
        <EnrollmentForm
          session={session}
          participantId={editor.id}
          onClose={() => setEditor(null)}
          onSaved={saved}
          onExpired={onExpired}
          onLogout={onLogout}
        />
      )}
    </>
  )
}
export default function App() {
  const [session, setSession] = useState<Session | null>(null)
  const [notice, setNotice] = useState('')
  const expired = useCallback(() => {
    setSession(null)
    setNotice('Akses atau sesi telah berakhir. Silakan masuk kembali.')
  }, [])
  useEffect(() => {
    if (!session) return
    const remaining = Date.parse(session.expiresAt) - Date.now()
    const timer = window.setTimeout(expired, Math.max(0, remaining))
    return () => window.clearTimeout(timer)
  }, [session, expired])
  async function logout() {
    if (!session) return
    const token = session.token
    setSession(null)
    setNotice('Menutup sesi perangkat…')
    let message = 'Anda telah keluar dari ruang penelitian.'
    try {
      await request('/auth/logout', token, 'POST')
    } catch (failure) {
      if (!(failure instanceof ApiError && failure.status === 401))
        message = 'Keluar dari perangkat ini. Pencabutan sesi server belum dapat dikonfirmasi.'
    }
    setSession(null)
    setNotice(message)
  }
  return session ? (
    <Workspace session={session} onExpired={expired} onLogout={logout} />
  ) : (
    <Login
      notice={notice}
      onSignedIn={(value) => {
        setSession(value)
        setNotice('')
      }}
    />
  )
}
