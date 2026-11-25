import {useEffect, useState} from 'react'
import {ApiError, request, type Session} from './api'

type Instrument = {
  version: string
  subscales: {label: string; items: {code: string; statement: string}[]}[]
}

export function HealthItuesReview({session, onExpired}: {session: Session; onExpired(): void}) {
  const [instrument, setInstrument] = useState<Instrument|null>(null)
  const [error, setError] = useState('')
  useEffect(() => {
    const controller = new AbortController()
    request<{data: Instrument}>('/research/health-itues/instrument', session.token, 'GET', undefined, controller.signal)
      .then(({data}) => { if (!controller.signal.aborted) setInstrument(data) })
      .catch((failure: unknown) => {
        if (controller.signal.aborted) return
        if (failure instanceof ApiError && failure.status === 401) onExpired()
        else setError(failure instanceof ApiError && failure.status === 403
          ? 'Akun ini tidak memiliki akses review Health-ITUES.'
          : 'Instrumen belum dapat dimuat. Tutup lalu buka kembali review.')
      })
    return () => controller.abort()
  }, [session.token, onExpired])

  return <section className="form-section" id="health-itues-review" aria-labelledby="health-itues-title">
    <h2 id="health-itues-title">Review Health-ITUES</h2>
    {error ? <p role="alert">{error}</p> : !instrument ? <p role="status">Memuat instrumen…</p> : <>
      <p>Versi substansi {instrument.version} · Review baca-saja</p>
      <p>Pengisian dan ekspor jawaban menunggu keputusan operasional peneliti.</p>
      {instrument.subscales.map(group => <section key={group.label}>
        <h3>{group.label}</h3>
        <ol>{group.items.map(item => <li key={item.code}><strong>{item.code}</strong> {item.statement}</li>)}</ol>
      </section>)}
    </>}
  </section>
}
