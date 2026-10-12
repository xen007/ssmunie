import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Select from '../../shared/ui/Select';

export default function HabilitationsPage() {
  const t = useT();
  const { lang } = useLang();
  const qc = useQueryClient();
  const { sections, filieres, niveaux } = usePerimetre();
  const [section, setSection] = useState('3');
  const [filiere, setFiliere] = useState('');
  const [niveau, setNiveau] = useState('');
  const [classe, setClasse] = useState('');

  const qs = new URLSearchParams();
  if (section) qs.set('section', section);
  if (filiere) qs.set('filiere', filiere);
  if (niveau) qs.set('niveau', niveau);
  if (classe) qs.set('classe', classe);
  const q = qs.toString();
  const { data } = useQuery({ queryKey: ['hab-matrix', q], queryFn: async () => (await api.get('/annee/habilitations_matrix.php?' + q)).data.data });
  const enseignants = data?.enseignants ?? [];
  const matieres = data?.matieres ?? [];
  const classes = data?.classes ?? [];
  const cells = data?.cells ?? [];

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);
  const niveauxF = useMemo(() => niveaux.filter((n) => (!section || n.section_id === Number(section)) && (!filiere || n.filiere_id === Number(filiere))), [niveaux, section, filiere]);
  const classesF = useMemo(() => classes.filter((c) => (!niveau || c.niveau_id === Number(niveau))), [classes, niveau]);

  const has = (ensId, matId) => cells.some((c) => c.enseignant_id === ensId && c.matiere_id === matId);
  const cellId = (ensId, matId) => cells.find((c) => c.enseignant_id === ensId && c.matiere_id === matId)?.id;

  const invalidate = () => qc.invalidateQueries({ queryKey: ['hab-matrix'] });

  const assign = useMutation({
    mutationFn: async ({ ensId, matId }) => api.post('/annee/habilitations.php', { enseignant_id: ensId, matiere_id: matId, classe_id: classe || null }),
    onSuccess: () => { invalidate(); },
  });
  const unassign = useMutation({
    mutationFn: async (id) => api.delete('/annee/habilitations.php?id=' + id),
    onSuccess: () => { invalidate(); },
  });

  const toggle = (ensId, matId) => {
    const id = cellId(ensId, matId);
    if (id) unassign.mutate(id); else assign.mutate({ ensId, matId });
  };

  const bulkAssign = (ensId) => {
    const missing = matieres.filter((m) => !has(ensId, m.id_matiere)).map((m) => ({ enseignant_id: ensId, matiere_id: m.id_matiere, classe_id: classe || null }));
    if (missing.length) api.post('/annee/habilitations.php', { items: missing }).then(() => { invalidate(); toast.success(t('habilitations.saved')); });
  };

  const exportMatrix = () => {
    let csv = 'Enseignant;' + matieres.map((m) => m.nom_matiere).join(';') + '\n';
    for (const e of enseignants) csv += (e.nom + ' ' + e.prenom) + ';' + matieres.map((m) => (has(e.id_enseignant, m.id_matiere) ? 'X' : '')).join(';') + '\n';
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'habilitations.csv'; a.click(); URL.revokeObjectURL(a.href);
  };

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('habilitations.title')}</h1><p className="mt-1 text-sm text-muted">{t('habilitations.subtitle')}</p></div>
        <Button variant="secondary" icon={Download} onClick={exportMatrix}>{t('habilitations.export')}</Button>
      </div>
      <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Select label={t('habilitations.section')} value={section} onChange={(e) => { setSection(e.target.value); setNiveau(''); setClasse(''); }}><option value="">{t('common.all')}</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
        <Select label={t('classes.filiere')} value={filiere} onChange={(e) => { setFiliere(e.target.value); setNiveau(''); setClasse(''); }}><option value="">{t('common.all')}</option>{filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}</Select>
        <Select label={t('habilitations.niveau')} value={niveau} onChange={(e) => { setNiveau(e.target.value); setClasse(''); }}><option value="">{t('common.all')}</option>{niveauxF.map((n) => <option key={n.id_niveau} value={n.id_niveau}>{n.libellee_niveau}</option>)}</Select>
        <Select label={t('habilitations.classe')} value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">{t('common.all')}</option>{classesF.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select>
      </div>
      <p className="mb-3 text-xs text-muted">{t('habilitations.hint')}</p>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border">
              <th className="sticky left-0 bg-surface px-3 py-2 text-left text-xs uppercase text-muted">{t('teachers.title')}</th>
              {matieres.map((m) => <th key={m.id_matiere} className="px-2 py-2 text-center text-[10px] font-medium text-muted" title={m.nom_matiere}>{m.code_matiere}</th>)}
              <th className="px-3 py-2 text-center text-xs uppercase text-muted">{t('common.all')}</th>
            </tr></thead>
            <tbody>
              {enseignants.map((e) => (
                <tr key={e.id_enseignant} className="border-b border-border last:border-0">
                  <td className="sticky left-0 bg-surface px-3 py-2 font-medium text-foreground">{e.nom} {e.prenom}</td>
                  {matieres.map((m) => (
                    <td key={m.id_matiere} className="px-2 py-2 text-center"><input type="checkbox" checked={has(e.id_enseignant, m.id_matiere)} onChange={() => toggle(e.id_enseignant, m.id_matiere)} /></td>
                  ))}
                  <td className="px-3 py-2 text-center"><button type="button" className="text-xs font-semibold text-primary hover:underline" onClick={() => bulkAssign(e.id_enseignant)}>{t('common.all')}</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}