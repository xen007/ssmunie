import { useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Camera, Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import config from '../../core/config';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
import Select from '../../shared/ui/Select';
import EleveForm from './EleveForm';

export default function ElevesPage() {
  const t = useT();
  const { lang } = useLang();
  const qc = useQueryClient();
  const navigate = useNavigate();
  const { sections, filieres, niveaux } = usePerimetre();
  const [search, setSearch] = useState('');
  const [fSection, setFSection] = useState('');
  const [fFiliere, setFFiliere] = useState('');
  const [fNiveau, setFNiveau] = useState('');
  const [fClasse, setFClasse] = useState('');
  const [fStatut, setFStatut] = useState('');
  const [page, setPage] = useState(1);
  const perPage = 50;
  const [form, setForm] = useState(null);
  const [photoTarget, setPhotoTarget] = useState(null);
  const [confirmDel, setConfirmDel] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [importResult, setImportResult] = useState(null);
  const [selected, setSelected] = useState([]);
  const [exportOpen, setExportOpen] = useState(false);
  const fileRef = useRef(null);
  const photoFileRef = useRef(null);

  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });

  const qs = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (search) qs.set('search', search);
  if (fSection) qs.set('section', fSection);
  if (fFiliere) qs.set('filiere', fFiliere);
  if (fNiveau) qs.set('niveau', fNiveau);
  if (fClasse) qs.set('classe', fClasse);
  if (fStatut) qs.set('statut', fStatut);
  const q = qs.toString();
  const { data, isLoading } = useQuery({ queryKey: ['eleves', q], queryFn: async () => (await api.get('/annee/eleves.php?' + q)).data });
  const list = data?.data ?? []; const total = data?.total ?? 0; const pages = data?.pages ?? 0;

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);
  const classeLabel = (e) => e.code_classe || (e.classe_id ? '#' + e.classe_id : '—');

  const invalidate = () => qc.invalidateQueries({ queryKey: ['eleves'] });
  const del = useMutation({ mutationFn: async (id) => api.delete('/annee/eleve.php?id=' + id), onSuccess: () => { invalidate(); setConfirmDel(null); toast.success(t('common.deleted')); } });

  const doImport = useMutation({
    mutationFn: async (file) => { const fd = new FormData(); fd.append('file', file); return api.post('/annee/eleve_import.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); },
    onSuccess: (res) => { invalidate(); setImportResult(res.data.data); },
    onError: () => toast.error(t('errors.generic')),
  });

  const doPhotoUpload = async (eleve, file) => {
    const fd = new FormData(); fd.append('photo', file); fd.append('matricule', eleve.matricule);
    try { await api.post('/annee/eleve_photo_upload.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); invalidate(); setPhotoTarget(null); toast.success(t('common.updated')); } catch { toast.error(t('errors.generic')); }
  };

  const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
  const allSelected = list.length > 0 && list.every((e) => selected.includes(e.id_eleve));
  const toggleAll = () => setSelected(allSelected ? [] : list.map((e) => e.id_eleve));

  const doExport = (format) => {
    let url = '/annee/eleve_export.php?format=' + format;
    if (selected.length) url += '&ids=' + selected.join(',');
    else { if (search) url += '&search=' + encodeURIComponent(search); if (fSection) url += '&section=' + fSection; if (fFiliere) url += '&filiere=' + fFiliere; if (fNiveau) url += '&niveau=' + fNiveau; if (fClasse) url += '&classe=' + fClasse; if (fStatut) url += '&statut=' + fStatut; }
    window.open(config.apiBaseUrl + url, '_blank');
    setExportOpen(false);
  };

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('students.title')}</h1><p className="mt-1 text-sm text-muted">{t('common.showing', { count: total })}</p></div>
        <div className="flex gap-2">
          <Button variant="secondary" icon={Upload} onClick={() => setImportOpen(true)}>{t('common.import')}</Button>
          <Button variant="secondary" icon={Download} onClick={() => setExportOpen(true)}>{t('common.export')}</Button>
          <Button icon={Plus} onClick={() => setForm({ open: true, initial: null })}>{t('students.new')}</Button>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <Input className="lg:col-span-2" label={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} placeholder={t('students.searchPlaceholder')} />
        <Select label={t('classes.section')} value={fSection} onChange={(e) => { setFSection(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
        <Select label={t('classes.filiere')} value={fFiliere} onChange={(e) => { setFFiliere(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
        <Select label={t('students.niveau')} value={fNiveau} onChange={(e) => { setFNiveau(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{niveaux.map((n) => <option key={n.id_niveau} value={n.id_niveau}>{n.libellee_niveau}</option>)}</Select>
        <Select label={t('students.classe')} value={fClasse} onChange={(e) => { setFClasse(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select>
        <Select label={t('students.statut')} value={fStatut} onChange={(e) => { setFStatut(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{['actif','inactif','diplome','transfere','decede'].map((s) => <option key={s} value={s}>{t('statut.' + s)}</option>)}</Select>
      </div>

      <Card className="overflow-hidden">
        <div className="hidden overflow-x-auto md:block">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="w-8 px-3 py-3"><input type="checkbox" checked={allSelected} onChange={toggleAll} /></th>
              <th className="px-3 py-3"></th><th className="px-3 py-3">{t('students.matricule')}</th><th className="px-3 py-3">{t('students.nom')}</th><th className="px-3 py-3">{t('students.classe')}</th><th className="px-3 py-3">{t('students.statut')}</th><th className="px-3 py-3">{t('common.actions')}</th>
            </tr></thead>
            <tbody>
              {list.map((e) => (
                <tr key={e.id_eleve} className="border-b border-border last:border-0">
                  <td className="px-3 py-2"><input type="checkbox" checked={selected.includes(e.id_eleve)} onChange={() => toggle(e.id_eleve)} /></td>
                  <td className="px-3 py-2">{e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-9 w-9 rounded-full border border-border object-cover" /> : <div className="h-9 w-9 rounded-full bg-primary-soft" />}</td>
                  <td className="px-3 py-2 font-medium text-foreground">{e.matricule}</td>
                  <td className="px-3 py-2 text-foreground">{e.nom} {e.prenom}</td>
                  <td className="px-3 py-2 text-muted">{classeLabel(e)}</td>
                  <td className="px-3 py-2 text-muted">{t('statut.' + e.statut)}</td>
                  <td className="px-3 py-2"><div className="flex gap-1">
                    <Button size="sm" variant="ghost" icon={Eye} onClick={() => navigate('/eleves/' + e.matricule)} aria-label={t('common.view')} />
                    <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm({ open: true, initial: e })} aria-label={t('common.edit')} />
                    <Button size="sm" variant="ghost" icon={Camera} onClick={() => setPhotoTarget(e)} aria-label={t('students.photo')} />
                    <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => setConfirmDel(e)} aria-label={t('common.delete')} />
                  </div></td>
                </tr>
              ))}
              {list.length === 0 && !isLoading && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col gap-2 p-3 md:hidden">
          {list.map((e) => (
            <div key={e.id_eleve} className="rounded-md border border-border p-3">
              <div className="flex items-center gap-3">
                {e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-10 w-10 rounded-full border border-border object-cover" /> : <div className="h-10 w-10 rounded-full bg-primary-soft" />}
                <div className="flex-1"><div className="font-medium text-foreground">{e.nom} {e.prenom}</div><div className="text-xs text-muted">{e.matricule} · {classeLabel(e)}</div></div>
                <Button size="sm" variant="ghost" onClick={() => navigate('/eleves/' + e.matricule)}>{t('common.view')}</Button>
              </div>
            </div>
          ))}
        </div>
      </Card>

      {pages > 1 && (
        <div className="mt-4 flex items-center justify-between">
          <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</Button>
          <span className="text-sm text-muted">{t('common.page')} {page} / {pages}</span>
          <Button size="sm" variant="secondary" disabled={page >= pages} onClick={() => setPage(page + 1)}>{t('common.next')}</Button>
        </div>
      )}

      <Modal open={!!form?.open} onClose={() => setForm(null)} title={form?.initial ? t('common.edit') + ' — ' + form.initial.nom + ' ' + form.initial.prenom : t('students.new')} size="lg">
        {form?.open && <EleveForm initial={form.initial} onClose={() => setForm(null)} onSaved={() => { setForm(null); invalidate(); }} />}
      </Modal>

      <Modal open={!!photoTarget} onClose={() => setPhotoTarget(null)} title={t('students.photo')} size="sm" footer={(
        <><Button variant="secondary" onClick={() => setPhotoTarget(null)}>{t('common.close')}</Button><Button onClick={() => photoFileRef.current?.files?.[0] && doPhotoUpload(photoTarget, photoFileRef.current.files[0])}>{t('common.save')}</Button></>
      )}>
        {photoTarget && (
          <div className="flex flex-col items-center gap-3">
            {photoTarget.photo_path ? <img src={config.uploadsBaseUrl + '/' + photoTarget.photo_path} alt="" className="h-40 w-40 rounded-md border border-border object-cover" /> : <div className="h-40 w-40 rounded-md border border-border bg-primary-soft" />}
            <Input ref={photoFileRef} type="file" accept="image/*" label={t('students.form.photo')} />
          </div>
        )}
      </Modal>

      <Modal open={!!confirmDel} onClose={() => setConfirmDel(null)} title={t('students.deleteTitle')} footer={(
        <><Button variant="secondary" onClick={() => setConfirmDel(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => del.mutate(confirmDel.id_eleve)}>{t('common.delete')}</Button></>
      )}><p className="text-sm">{t('students.deleteConfirm', { name: (confirmDel?.nom || '') + ' ' + (confirmDel?.prenom || '') })}</p></Modal>

      <Modal open={importOpen} onClose={() => setImportOpen(false)} title={t('students.importTitle')} size="lg" footer={(
        <><Button variant="secondary" onClick={() => setImportOpen(false)}>{t('common.cancel')}</Button><Button disabled={doImport.isPending} onClick={() => fileRef.current?.files?.[0] && doImport.mutate(fileRef.current.files[0])}>{t('common.import')}</Button></>
      )}>
        <div className="flex flex-col gap-3">
          <Input ref={fileRef} type="file" accept=".csv" label={t('import.file')} />
          {importResult && (
            <div className="rounded-md border border-border p-3 text-sm">
              <div className="text-emerald-500">{t('import.created', { count: importResult.created })}</div>
              <div className="text-amber-500">{t('import.duplicates', { count: importResult.duplicates?.length ?? 0 })}</div>
              <div className="text-danger">{t('import.errors', { count: importResult.errors?.length ?? 0 })}</div>
            </div>
          )}
        </div>
      </Modal>

      <Modal open={exportOpen} onClose={() => setExportOpen(false)} title={t('students.exportTitle')} size="sm" footer={(
        <><Button variant="secondary" onClick={() => setExportOpen(false)}>{t('common.cancel')}</Button></>
      )}>
        <div className="flex flex-col gap-2">
          {['csv', 'xlsx', 'pdf', 'vcf'].map((fmt) => <Button key={fmt} variant="secondary" onClick={() => doExport(fmt)}>{fmt.toUpperCase()}</Button>)}
          <p className="text-xs text-muted">{selected.length ? selected.length + ' sélectionné(s)' : t('common.all')}</p>
        </div>
      </Modal>
    </div>
  );
}