import { useEffect, useState } from 'react'
import { ApiError, errorMessage, request, type Session } from './api'

type Filters = { from: string; to: string; type: string }
const empty: Filters = { from: '', to: '', type: '' }
type Entry = {
  id: string; date_wib: string; study_day: number; vas_mm: number; type?: string;
  filled_by: string; received_at: string; device_created_at?: string; version: string | null;
  answers: { code: string; value: string; question_label?: string | null; answer_label?: string | null }[];
}
type Result = { data: Entry[]; meta: { last_page: number; total?: number; filters?: { from: string | null; to: string | null; type: string | null } } }
const wib = (value: string) => `${new Date(value).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB`
export function AssessmentHistory({ participantId, session, onExpired }: {
  participantId: string; session: Session; onExpired(): void
}) {
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(1)
  const [retry, setRetry] = useState(0)
  const [draft, setDraft] = useState<Filters>(empty)
  const [filters, setFilters] = useState<Filters>(empty)
  const [validation, setValidation] = useState('')
  const query = new URLSearchParams({ page: String(page) })
  for (const field of ['from', 'to', 'type'] as const) if (filters[field]) query.set(field, filters[field])
  const queryString = query.toString()
  const loadKey = `${participantId}:${session.token}:${queryString}:${retry}`
  const [loaded, setLoaded] = useState<{ key: string; result?: Result; error?: string } | null>(null)
  const result = loaded?.key === loadKey ? loaded.result : null
  const error = loaded?.key === loadKey ? loaded.error : ''
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    void request<Result>(`/participants/${participantId}/assessments?${queryString}`, session.token, 'GET', undefined, controller.signal)
      .then(data => {
        if (controller.signal.aborted) return
        const expected = Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, value || null]))
        if (Object.values(filters).some(Boolean) && (!data.meta.filters || Object.entries(expected).some(([key, value]) => data.meta.filters?.[key as keyof Filters] !== value))) {
          setLoaded({ key: loadKey, error: 'Filter server belum terverifikasi. Hasil tidak ditampilkan; muat ulang setelah API diperbarui.' })
        } else setLoaded({ key: loadKey, result: data })
      })
      .catch(failure => {
        if (controller.signal.aborted) return
        if (failure instanceof ApiError && failure.status === 401) onExpired()
        else setLoaded({ key: loadKey, error: errorMessage(failure) })
      })
    return () => controller.abort()
  }, [open, loadKey, participantId, session.token, onExpired, queryString, filters])
  function apply() {
    if (draft.from && draft.to && draft.from > draft.to) { setValidation('Tanggal akhir tidak boleh mendahului tanggal awal.'); return }
    setValidation(''); setPage(1); setFilters({ ...draft }); setRetry(value => value + 1)
  }
  return <section className="form-section">
    <h3>Assessment pasien · hanya baca</h3>
    <button type="button" className="secondary" onClick={() => { setLoaded(null); setOpen(value => !value) }}>
      {open ? 'Tutup riwayat assessment' : 'Lihat riwayat assessment'}
    </button>
    {open && <div>
      <p>Peserta mengikuti pilihan pada daftar penelitian. Rentang tanggal WIB bersifat inklusif.</p>
      <div className="assessment-filters" onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); apply() } }}>
        <label>Dari tanggal WIB<input type="date" value={draft.from} onChange={event => setDraft({ ...draft, from: event.target.value })} /></label>
        <label>Sampai tanggal WIB<input type="date" value={draft.to} onChange={event => setDraft({ ...draft, to: event.target.value })} /></label>
        <label>Jenis catatan<select value={draft.type} onChange={event => setDraft({ ...draft, type: event.target.value })}>
          <option value="">Semua jenis tersimpan</option><option value="HARIAN">Harian</option><option value="ULANG">Ulang (catatan historis)</option>
        </select></label>
        <button type="button" className="secondary" onClick={apply}>Terapkan filter assessment</button>
        <button type="button" className="secondary" onClick={() => { setDraft(empty); setFilters(empty); setPage(1); setValidation(''); setRetry(value => value + 1) }}>Hapus filter assessment</button>
      </div>
      {validation && <p role="alert">{validation}</p>}
      <p role="status">Filter diterapkan: {filters.from || 'awal'} sampai {filters.to || 'akhir'} · {filters.type || 'semua jenis'}.</p>
      <button type="button" className="secondary" onClick={() => setRetry(value => value + 1)}>Muat ulang assessment</button>
      {error ? <><p role="alert">{error}</p><button type="button" onClick={() => setRetry(value => value + 1)}>Coba lagi</button></>
        : !result ? <p role="status">Memuat assessment…</p> : <>
          {result.data.length === 0 ? <p>Belum ada assessment tersimpan.</p> : result.data.map(entry => <article key={entry.id}>
            <h4>Hari {entry.study_day} · {entry.date_wib} WIB</h4>
            <p>Tersimpan (server) · {entry.type || 'jenis historis'}</p>
            <p>VAS {entry.vas_mm} mm · {entry.filled_by} · versi {entry.version ?? 'catatan sebelumnya'}</p>
            <p>Diterima {wib(entry.received_at)}</p>
            {entry.device_created_at && <p>Waktu perangkat {wib(entry.device_created_at)} (bukan waktu penerimaan)</p>}
            <ul>{entry.answers.map(answer => <li key={answer.code}>{answer.question_label || answer.code}: {answer.answer_label || answer.value}</li>)}</ul>
          </article>)}
          <p>{result.meta.total ?? result.data.length} catatan tersimpan dalam hasil · halaman {page} dari {result.meta.last_page}.</p>
          <button type="button" disabled={page <= 1} onClick={() => setPage(value => value - 1)}>Assessment sebelumnya</button>
          <button type="button" disabled={page >= result.meta.last_page} onClick={() => setPage(value => value + 1)}>Assessment berikutnya</button>
          <p>Jawaban tersimpan tidak dapat diedit. Tidak ada klasifikasi atau skor yang dihitung.</p>
        </>}
      <p>Hasil hanya mencakup penerimaan server. Antrean lokal pasien tidak terlihat. Tidak ada penilaian kepatuhan atau kelengkapan tanpa jadwal yang disetujui.</p>
    </div>}
  </section>
}
