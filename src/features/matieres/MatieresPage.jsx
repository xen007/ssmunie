import { useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
import Select from '../../shared/ui/Select';

const TYPES = ['generale', 'technique', 'optionnelle', 'competence'];
const EMPTY = { id_matiere: null, code_matiere: '', nom_matiere: '', section_id: '', filiere_id: '', niveau_id: '', coefficient: 1, type: 'generale', actif: 1 };

export default function MatieresPage() {
  const t = useT();
  const { lang } = useLang();
  const qc = useQueryClient();
  const { sections, filieres, niveaux } = usePerimetre();
  const [fSection, setFSection] = useState('');
  const [fFiliere, setFFiliere] = useState('');
  const [fType, setFType] = useState('');
  const [fActif, setFActif] = useState('1');
  const [search, setSearch] = useState('');
  const [form, setForm] = useState(null);
  const [confirmDel, setConfirmDel] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const fileRef = useRef(null);

  const qs = new URLSearchParams();
  if (fSection) qs.set('section', fSection);
  if (fFiliere) qs.set('filiere', fFiliere);
  if (fType) qs.set('type', fType);
  if (fActif) qs.set('actif', fActif);
  if (search) qs.set('search', search);
  const q = qs.toString();
  const { data: list = [] } = useQuery({ queryKey: ['matieres', q], queryFn: async () => (await api.get('/annee/matieres.php?' + q)).data.data });

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);
  const secLabel = (id) => { const s = sections.find((x) => x.id === id); return s ? (lang === 'fr' ? s.libelle_fr : s.libelle_en) : id; };
  const filLabel = (id) => { const f = filieres.find((x) => x.id === id); return f ? f.code : id; };

  const invalidate = () => qc.invalidateQueries({ queryKey: ['matieres'] });
  const save = useMutation({
    mutationFn: async (p) => (p.id_matiere ? api.put('/annee/matieres.php', p) : api.post('/annee/matieres.php', p)),
    onSuccess: () => { invalidate(); setForm(null); toast.success(t('common.updated')); },
    onError: () => toast.error(t('errors.generic')),
  });
  const del = useMutation({ mutationFn: async (id) => api.delete('/annee/matieres.php?id_matiere=' + id), onSuccess: () => { invalidate(); setConfirmDel(null); toast.success(t('common.deleted')); } });

  const importMat = useMutation({
    mutationFn: async (file) => { const fd = new FormData(); fd.append('file', file); return api.post('/annee/matieres_import.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); },
    onSuccess: (res) => { invalidate(); setImportOpen(false); toast.success(t('import.created', { count: res.data.data.created })); },
    onError: () => toast.error(t('errors.generic')),
  });

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('matieres.title')}</h1><p className="mt-1 text-sm text-muted">{t('matieres.subtitle')}</p></div>
        <div className="flex gap-2">
          <Button variant="secondary" icon={Upload} onClick={() => setImportOpen(true)}>{t('common.import')}</Button>
          <Button icon={Plus} onClick={() => setForm({ ...EMPTY })}>{t('matieres.new')}</Button>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <Input label={t('common.search')} value={search} onChange={(e) => setSearch(e.target.value)} />
        <Select label={t('matieres.section')} value={fSection} onChange={(e) => setFSection(e.target.value)}><option value="">{t('common.all')}</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
        <Select label={t('matieres.filiere')} value={fFiliere} onChange={(e) => setFFiliere(e.target.value)}><option value="">{t('common.all')}</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
        <Select label={t('matieres.type')} value={fType} onChange={(e) => setFType(e.target.value)}><option value="">{t('common.all')}</option>{TYPES.map((ty) => <option key={ty} value={ty}>{t('matieres.type.' + ty)}</option>)}</Select>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-3">{t('matieres.code')}</th><th className="px-4 py-3">{t('matieres.nom')}</th><th className="px-4 py-3">{t('matieres.section')}</th><th className="px-4 py-3">{t('matieres.filiere')}</th><th className="px-4 py-3">{t('matieres.coefficient')}</th><th className="px-4 py-3">{t('matieres.type')}</th><th className="px-4 py-3">{t('matieres.actif')}</th><th className="px-4 py-3">{t('common.actions')}</th>
            </tr></thead>
            <tbody>
              {list.map((m) => (
                <tr key={m.id_matiere} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{m.code_matiere}</td>
                  <td className="px-4 py-3 text-foreground">{m.nom_matiere}</td>
                  <td className="px-4 py-3 text-muted">{secLabel(m.section_id)}</td>
                  <td className="px-4 py-3 text-muted">{filLabel(m.filiere_id)}</td>
                  <td className="px-4 py-3 text-foreground">{m.coefficient}</td>
                  <td className="px-4 py-3 text-muted">{t('matieres.type.' + m.type)}</td>
                  <td className="px-4 py-3">{m.actif ? <span className="text-emerald-500">●</span> : <span className="text-muted">○</span>}</td>
                  <td className="px-4 py-3"><div className="flex gap-1">
                    <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm({ ...m, id_matiere: m.id_matiere, section_id: String(m.section_id), filiere_id: String(m.filiere_id), niveau_id: m.niveau_id ? String(m.niveau_id) : '' })} aria-label={t('common.edit')} />
                    <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => setConfirmDel(m)} aria-label={t('common.delete')} />
                  </div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id_matiere ? t('common.edit') : t('matieres.new')} size="lg" footer={(
        <><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button disabled={save.isPending} onClick={() => save.mutate(form)}>{t('common.save')}</Button></>
      )}>
        {form && (
          <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-3">
              <Input label={t('matieres.code')} required value={form.code_matiere} onChange={(e) => setForm({ ...form, code_matiere: e.target.value })} />
              <Input label={t('matieres.nom')} required value={form.nom_matiere} onChange={(e) => setForm({ ...form, nom_matiere: e.target.value })} />
            </div>
            <div className="grid grid-cols-3 gap-3">
              <Select label={t('matieres.section')} required value={form.section_id} onChange={(e) => setForm({ ...form, section_id: e.target.value })}><option value="">—</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
              <Select label={t('matieres.filiere')} required value={form.filiere_id} onChange={(e) => setForm({ ...form, filiere_id: e.target.value })}><option value="">—</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
              <Input type="number" step="0.01" label={t('matieres.coefficient')} value={form.coefficient} onChange={(e) => setForm({ ...form, coefficient: e.target.value })} />
            </div>
            <Select label={t('matieres.type')} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>{TYPES.map((ty) => <option key={ty} value={ty}>{t('matieres.type.' + ty)}</option>)}</Select>
            <Select label={t('matieres.actif')} value={form.actif} onChange={(e) => setForm({ ...form, actif: Number(e.target.value) })}><option value={1}>{t('common.active')}</option><option value={0}>{t('common.inactive')}</option></Select>
          </div>
        )}
      </Modal>

      <Modal open={!!confirmDel} onClose={() => setConfirmDel(null)} title={t('common.confirmDelete')} footer={(
        <><Button variant="secondary" onClick={() => setConfirmDel(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => del.mutate(confirmDel.id_matiere)}>{t('common.delete')}</Button></>
      )}><p className="text-sm">{t('common.confirmDelete')} — {confirmDel?.nom_matiere} ?</p></Modal>

      <Modal open={importOpen} onClose={() => setImportOpen(false)} title={t('matieres.importTitle')} footer={(
        <><Button variant="secondary" onClick={() => setImportOpen(false)}>{t('common.cancel')}</Button><Button disabled={importMat.isPending} onClick={() => fileRef.current?.files?.[0] && importMat.mutate(fileRef.current.files[0])}>{t('common.import')}</Button></>
      )}>
        <div className="flex flex-col gap-3"><Input ref={fileRef} type="file" accept=".csv" label={t('import.file')} /></div>
      </Modal>
    </div>
  );
}