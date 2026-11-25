import {render, screen, fireEvent, waitFor} from '@testing-library/react-native';
import {Linking} from 'react-native';
import {EducationCatalog} from './src/patient/EducationCatalog';
import {canOpenEducationUrl} from './src/patient/educationLinks';
import {PatientContext, type Context} from './src/patient/context';

jest.mock('expo-router', () => ({router:{push:jest.fn(),replace:jest.fn(),canGoBack:()=>false}}));
const video = 'https://youtu.be/QDgrjQTLdB0?si=2jHEYWwPSG03reOS';
const topic = {code:'EDU-02',title:'Topik sintetis',description:'Hanya uji',has_video:true};
const context = (fetch: (path: string, body?: object) => Promise<unknown>, offline = false): Context => ({
  api: async <T,>(path: string, body?: object) => await fetch(path, body) as T, offline, session:null, profile:null, busy:false, ready:true, acknowledged:true, message:'',
  login:jest.fn(), logout:jest.fn(), retry:jest.fn(), acknowledge:jest.fn(),
});
afterEach(() => jest.restoreAllMocks());

test.each(['javascript:alert(1)', 'https://youtu.be.example.com/QDgrjQTLdB0', 'https://youtube.com@evil.com/watch?v=QDgrjQTLdB0', 'file:///video.mp4'])('rejects unsafe video link %s', url => {
  expect(canOpenEducationUrl(url,true)).toBe(false);
});

test('hands off the supplied video and HTTPS documents verbatim and offers back navigation', async () => {
  const pdf = 'https://healthonline.washington.edu/example.pdf';
  const doi = 'https://doi.org/10.55681/sentri.v3i2.2298';
  const api = jest.fn(async (path: string) => path.includes('?page=')
    ? {data:[topic],meta:{last_page:1}}
    : {data:{...topic,references:[{type:'YOUTUBE',label:'Video',url:video},{type:'PDF',label:'PDF',url:pdf},{type:'DOI',label:'DOI',url:doi}]}});
  const link = jest.spyOn(Linking,'openURL').mockResolvedValue(undefined);
  await render(<PatientContext.Provider value={context(api)}><EducationCatalog /></PatientContext.Provider>);
  await fireEvent.press(await screen.findByRole('button',{name:'Baca Materi'}));
  await fireEvent.press(await screen.findByRole('button',{name:'Tonton Video'}));
  await fireEvent.press(screen.getByRole('button',{name:'Buka Dokumen'}));
  await fireEvent.press(screen.getByRole('button',{name:'Lihat Referensi'}));
  expect(link.mock.calls).toEqual([[video],[pdf],[doi]]);
  await fireEvent.press(screen.getAllByRole('button',{name:'Kembali'}).at(-1)!);
  await screen.findByText('Materi Edukasi');
});

test('reports external app failures and does not show a video button for missing video', async () => {
  const api = jest.fn(async (path: string) => path.includes('?page=')
    ? {data:[{...topic,has_video:false}],meta:{last_page:1}}
    : {data:{...topic,references:[{type:'ARTICLE',label:'Referensi',url:'https://www.example.com/material'}]}});
  jest.spyOn(Linking,'openURL').mockRejectedValue(new Error('Synthetic failure'));
  await render(<PatientContext.Provider value={context(api)}><EducationCatalog /></PatientContext.Provider>);
  await fireEvent.press(await screen.findByRole('button',{name:'Baca Materi'}));
  await screen.findByText('Referensi');
  expect(screen.queryByRole('button',{name:'Tonton Video'})).toBeNull();
  await fireEvent.press(screen.getByRole('button',{name:'Baca Materi'}));
  await screen.findByText('Tautan materi tidak dapat dibuka. Silakan coba kembali.');
});

test('offline does not fetch and reconnect reloads the list', async () => {
  const api = jest.fn().mockResolvedValue({data:[],meta:{last_page:1}});
  const mounted = await render(<PatientContext.Provider value={context(api,true)}><EducationCatalog /></PatientContext.Provider>);
  expect(api).not.toHaveBeenCalled();
  await mounted.rerender(<PatientContext.Provider value={context(api)}><EducationCatalog /></PatientContext.Provider>);
  await waitFor(() => expect(api).toHaveBeenCalledWith('/patient/education?page=1', undefined));
  await screen.findByText('Belum ada materi yang dipublikasikan.');
});
