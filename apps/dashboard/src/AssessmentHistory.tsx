import { useEffect, useState } from 'react'
import { ApiError, errorMessage, request, type Session } from './api'

type Entry = {
  id: string; date_wib: string; study_day: number; vas_mm: number;
  filled_by: string; received_at: string; version: string | null;
  answers: { code: string; value: string }[];
}
export function AssessmentHistory({ participantId, session, onExpired }: {
  participantId: string; session: Session; onExpired(): void
}) {
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(1)
  const [retry, setRetry] = useState(0)
  const loadKey = `${participantId}:${session.token}:${page}:${retry}`
  const [loaded, setLoaded] = useState<{ key: string; result?: { data: Entry[]; meta: { last_page: number } }; error?: string } | null>(null)
  const result = loaded?.key === loadKey ? loaded.result : null
  const error = loaded?.key === loadKey ? loaded.error : ''
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    void request<{ data: Entry[]; meta: { last_page: number } }>(
      `/participants/${participantId}/assessments?page=${page}`, session.token, 'GET', undefined, controller.signal,
    ).then(data => { if (!controller.signal.aborted) setLoaded({ key: loadKey, result: data }) })
      .catch(failure => {
        if (controller.signal.aborted) return
        if (failure instanceof ApiError && failure.status === 401) onExpired()
        else setLoaded({ key: loadKey, error: errorMessage(failure) })
      })
    return () => controller.abort()
  }, [open, page, loadKey, participantId, session.token, onExpired])
  return <section className="form-section">
    <h3>Assessment pasien · hanya baca</h3>
    <button type="button" className="secondary" onClick={() => { setLoaded(null); setOpen(value => !value) }}>
      {open ? 'Tutup riwayat assessment' : 'Lihat riwayat assessment'}
    </button>
    {open && <div>
      {error ? <><p role="alert">{error}</p><button type="button" onClick={() => setRetry(value => value + 1)}>Coba lagi</button></>
        : !result ? <p role="status">Memuat assessment…</p> : <>
          {result.data.length === 0 ? <p>Belum ada assessment tersimpan.</p> : result.data.map(entry => <article key={entry.id}>
            <h4>Hari {entry.study_day} · {entry.date_wib}</h4>
            <p>VAS {entry.vas_mm} mm · {entry.filled_by} · versi {entry.version ?? 'catatan sebelumnya'}</p>
            <p>Diterima {new Date(entry.received_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB</p>
            <ul>{entry.answers.map(answer => <li key={answer.code}>{answer.code}: {answer.value}</li>)}</ul>
          </article>)}
          <button type="button" disabled={page <= 1} onClick={() => setPage(value => value - 1)}>Assessment sebelumnya</button>
          <button type="button" disabled={page >= result.meta.last_page} onClick={() => setPage(value => value + 1)}>Assessment berikutnya</button>
          <p>Jawaban tersimpan tidak dapat diedit. Tidak ada klasifikasi atau skor yang dihitung.</p>
        </>}
    </div>}
  </section>
}
