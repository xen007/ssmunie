import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
import Select from '../../shared/ui/Select';

const EMPTY = { id_classe: null, code_classe: '', libellee_classe: '', niveau_id: '', section_id: '', filiere_id: '', capacite_accueil: 60, actif: 1, enseignant_principal_id: '' };

export default function ClassesPage() {
  const t = useT();
  const { lang } = useLang();
  const qc = useQueryClient();
  const { sections, filieres, niveaux } = usePerimetre();
  const [fSection, setFSection] = useState('');
  const [fFiliere, setFFiliere] = useState('');
  const [fNiveau, setFNiveau] = useState('');
  const [form, setForm] = useState(null);
  const [confirmDel, setConfirmDel] = useState(null);

  const { data: list = [] } = useQuery({ queryKey: ['classes'], queryFn: async () => (await api.get('/annee/classes.php')).data.data });
  const { data: teachers = [] } = useQuery({ queryKey: ['enseignants-list'], queryFn: async () => (await api.get('/annee/enseignants.php?per_page=200')).data.data });

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);
  const secLabel = (id) => { const s = sections.find((x) => x.id === id); return s ? (lang === 'fr' ? s.libelle_fr : s.libelle_en) : id; };
  const filLabel = (id) => { const f = filieres.find((x) => x.id === id); return f ? f.code : id; };

  const filtered = useMemo(() => list.filter((c) => (!fSection || c.section_id === Number(fSection)) && (!fFiliere || c.filiere_id === Number(fFiliere)) && (!fNiveau || c.niveau_id === Number(fNiveau))), [list, fSection, fFiliere, fNiveau]);

  const invalidate = () => qc.invalidateQueries({ queryKey: ['classes'] });
  const save = useMutation({
    mutationFn: async (p) => { const body = { ...p }; if (body.id_classe) return api.put('/annee/classes.php', body); return api.post('/annee/classes.php', body); },
    onSuccess: () => { invalidate(); setForm(null); toast.success(t('common.updated')); },
    onError: () => toast.error(t('errors.generic')),
  });
  const del = useMutation({ mutationFn: async (id) => api.delete('/annee/classes.php?id_classe=' + id), onSuccess: () => { invalidate(); setConfirmDel(null); toast.success(t('common.deleted')); } });

  const openForm = () => setForm({ ...EMPTY });
  const niveauxFiltered = useMemo(() => niveaux.filter((n) => (!form?.section_id || n.section_id === Number(form.section_id)) && (!form?.filiere_id || n.filiere_id === Number(form.filiere_id))), [niveaux, form?.section_id, form?.filiere_id]);

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('classes.title')}</h1><p className="mt-1 text-sm text-muted">{t('classes.subtitle')}</p></div>
        <Button icon={Plus} onClick={openForm}>{t('classes.new')}</Button>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Select label={t('classes.section')} value={fSection} onChange={(e) => setFSection(e.target.value)}><option value="">{t('common.all')}</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
        <Select label={t('classes.filiere')} value={fFiliere} onChange={(e) => setFFiliere(e.target.value)}><option value="">{t('common.all')}</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
        <Select label={t('classes.niveau')} value={fNiveau} onChange={(e) => setFNiveau(e.target.value)}><option value="">{t('common.all')}</option>{niveaux.filter((n) => (!fSection || n.section_id === Number(fSection)) && (!fFiliere || n.filiere_id === Number(fFiliere))).map((n) => <option key={n.id_niveau} value={n.id_niveau}>{n.libellee_niveau}</option>)}</Select>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-3">{t('classes.code')}</th><th className="px-4 py-3">{t('classes.libelle')}</th><th className="px-4 py-3">{t('classes.niveau')}</th><th className="px-4 py-3">{t('classes.section')}</th><th className="px-4 py-3">{t('classes.filiere')}</th><th className="px-4 py-3">{t('classes.capacite')}</th><th className="px-4 py-3">{t('classes.effectif')}</th><th className="px-4 py-3">{t('classes.actif')}</th><th className="px-4 py-3">{t('common.actions')}</th>
            </tr></thead>
            <tbody>
              {filtered.map((c) => (
                <tr key={c.id_classe} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{c.code_classe}</td>
                  <td className="px-4 py-3 text-foreground">{c.libellee_classe}</td>
                  <td className="px-4 py-3 text-muted">{c.code_niveau || c.libellee_niveau}</td>
                  <td className="px-4 py-3 text-muted">{secLabel(c.section_id)}</td>
                  <td className="px-4 py-3 text-muted">{filLabel(c.filiere_id)}</td>
                  <td className="px-4 py-3 text-muted">{c.capacite_accueil}</td>
                  <td className="px-4 py-3 text-foreground">{c.effectif}</td>
                  <td className="px-4 py-3">{c.actif ? <span className="text-emerald-500">●</span> : <span className="text-muted">○</span>}</td>
                  <td className="px-4 py-3"><div className="flex gap-1">
                    <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm({ ...c, id_classe: c.id_classe, niveau_id: String(c.niveau_id), section_id: String(c.section_id), filiere_id: String(c.filiere_id), enseignant_principal_id: c.enseignant_principal_id ?? '' })} aria-label={t('common.edit')} />
                    <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => setConfirmDel(c)} aria-label={t('common.delete')} />
                  </div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id_classe ? t('common.edit') : t('classes.new')} size="lg" footer={(
        <><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button disabled={save.isPending} onClick={() => save.mutate(form)}>{t('common.save')}</Button></>
      )}>
        {form && (
          <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-3">
              <Input label={t('classes.code') + ' (' + t('common.optional') + ')'} value={form.code_classe} onChange={(e) => setForm({ ...form, code_classe: e.target.value })} />
              <Input label={t('classes.libelle')} required value={form.libellee_classe} onChange={(e) => setForm({ ...form, libellee_classe: e.target.value })} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Select label={t('classes.section')} required value={form.section_id} onChange={(e) => setForm({ ...form, section_id: e.target.value, niveau_id: '' })}><option value="">—</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
              <Select label={t('classes.filiere')} required value={form.filiere_id} onChange={(e) => setForm({ ...form, filiere_id: e.target.value, niveau_id: '' })}><option value="">—</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
            </div>
            <Select label={t('classes.niveau')} required value={form.niveau_id} onChange={(e) => { const n = niveaux.find((x) => x.id_niveau === Number(e.target.value)); setForm({ ...form, niveau_id: e.target.value, ...(n ? { section_id: String(n.section_id), filiere_id: String(n.filiere_id) } : {}) }); }}><option value="">—</option>{niveauxFiltered.map((n) => <option key={n.id_niveau} value={n.id_niveau}>{n.libellee_niveau}</option>)}</Select>
            <div className="grid grid-cols-2 gap-3">
              <Input type="number" label={t('classes.capacite')} value={form.capacite_accueil} onChange={(e) => setForm({ ...form, capacite_accueil: e.target.value })} />
              <Select label={t('classes.enseignantPrincipal')} value={form.enseignant_principal_id} onChange={(e) => setForm({ ...form, enseignant_principal_id: e.target.value })}><option value="">—</option>{teachers.map((ens) => <option key={ens.id_enseignant} value={ens.id_enseignant}>{ens.civilite ? ens.civilite + ' ' : ''}{ens.nom} {ens.prenom}</option>)}</Select>
            </div>
            <Select label={t('classes.actif')} value={form.actif} onChange={(e) => setForm({ ...form, actif: Number(e.target.value) })}><option value={1}>{t('common.active')}</option><option value={0}>{t('common.inactive')}</option></Select>
          </div>
        )}
      </Modal>

      <Modal open={!!confirmDel} onClose={() => setConfirmDel(null)} title={t('common.confirmDelete')} footer={(
        <><Button variant="secondary" onClick={() => setConfirmDel(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => del.mutate(confirmDel.id_classe)}>{t('common.delete')}</Button></>
      )}><p className="text-sm">{t('classes.deleteConfirm', { name: confirmDel?.libellee_classe })}</p></Modal>
    </div>
  );
}