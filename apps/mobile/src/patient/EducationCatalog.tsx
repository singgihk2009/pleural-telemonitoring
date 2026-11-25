import {useEffect, useState} from 'react';
import {Linking} from 'react-native';
import {usePatient} from './context';
import {canOpenEducationUrl} from './educationLinks';
import {Button, Card, Copy, Frame, Heading, Notice} from './ui';

type Reference = {type: 'ARTICLE'|'JOURNAL'|'DOI'|'PDF'|'YOUTUBE'; label: string; url: string|null};
type Summary = {code: string; title: string; description: string; has_video: boolean};
type Detail = Summary & {references: Reference[]};
type Page = {data: Summary[]; meta: {last_page: number}};

export function EducationCatalog() {
  const {api, offline} = usePatient();
  const [selected, setSelected] = useState<string|null>(null);
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const [list, setList] = useState<Page|null>(null);
  const [detail, setDetail] = useState<Detail|null>(null);
  const [failed, setFailed] = useState(false);
  const [linkError, setLinkError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (offline) return;
    let active = true;
    // Hide stale results while this network request is in flight.
    // eslint-disable-next-line react/set-state-in-effect
    setLoading(true);
    setFailed(false);
    const load = selected === null
      ? api<Page>(`/patient/education?page=${page}`).then(value => { if (active) setList(value); })
      : api<{data: Detail}>(`/patient/education/${encodeURIComponent(selected)}`).then(value => { if (active) setDetail(value.data); });
    void load.catch(() => { if (active) { setFailed(true); setDetail(null); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [api, offline, page, retry, selected]);

  function select(code: string|null) {
    setDetail(null); setLinkError(''); setFailed(false); setSelected(code);
  }

  async function open(reference: Reference) {
    setLinkError('');
    const video = reference.type === 'YOUTUBE';
    if (offline) { setLinkError('Koneksi internet diperlukan untuk membuka materi eksternal.'); return; }
    if (!reference.url || !canOpenEducationUrl(reference.url, video)) {
      setLinkError(video ? 'Video tidak dapat dibuka. Silakan coba kembali.' : 'Tautan materi tidak dapat dibuka.');
      return;
    }
    try {
      await Linking.openURL(reference.url);
    } catch {
      setLinkError(video ? 'Video tidak dapat dibuka. Silakan coba kembali.' : 'Tautan materi tidak dapat dibuka. Silakan coba kembali.');
    }
  }

  const showDetail = !offline && !loading && !failed && selected !== null && detail?.code === selected;
  return <Frame title={showDetail ? detail.title : 'Materi Edukasi'} step="F7 · MATERI PENELITIAN"
    onBack={selected === null ? undefined : () => select(null)}>
    {offline ? <Notice>Koneksi internet diperlukan untuk membuka materi eksternal. Katalog dimuat kembali saat koneksi tersedia.</Notice>
      : failed ? <><Notice>Materi belum dapat dimuat.</Notice><Button title="Coba lagi" onPress={() => setRetry(n=>n+1)} /></>
      : loading || (selected === null ? !list : !showDetail) ? <Copy accessibilityLiveRegion="polite">Memuat materi…</Copy>
      : showDetail ? <Card>
        <Copy>{detail.description}</Copy>
        {linkError ? <Notice>{linkError}</Notice> : null}
        {detail.references.map((reference,index) => {
          const video = reference.type === 'YOUTUBE';
          if (video && (!reference.url || !canOpenEducationUrl(reference.url,true))) return null;
          const label = video ? 'Tonton Video' : reference.type === 'PDF' ? 'Buka Dokumen'
            : reference.type === 'ARTICLE' ? 'Baca Materi' : 'Lihat Referensi';
          return <Card key={index}><Copy>{reference.label}</Copy>
            {reference.url && <Button title={label} secondary onPress={() => { void open(reference); }} />}
          </Card>;
        })}
        <Button title="Kembali" secondary onPress={() => select(null)} />
      </Card> : <>
        {list?.data.length === 0 && <Notice>Belum ada materi yang dipublikasikan.</Notice>}
        {list?.data.map(item => <Card key={item.code}>
          <Heading>{item.title}</Heading>
          <Copy>{item.description}</Copy>
          <Copy>▤ Materi tersedia{item.has_video ? ' · Video tersedia' : ''}</Copy>
          <Button title="Baca Materi" onPress={() => select(item.code)} />
        </Card>)}
        {(list?.meta.last_page ?? 1) > 1 && <>
          <Button title="Halaman sebelumnya" secondary disabled={page<=1} onPress={() => setPage(n=>n-1)} />
          <Button title="Halaman berikutnya" secondary disabled={page>=(list?.meta.last_page ?? 1)} onPress={() => setPage(n=>n+1)} />
        </>}
      </>}
  </Frame>;
}
