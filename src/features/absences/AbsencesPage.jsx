import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

export default function AbsencesPage() {
  const t = useT(); const qc = useQueryClient();
  const [classe, setClasse] = useState(''); const [mois, setMois] = useState('1');
  const [cells, setCells] = useState({});
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-classe', classe], queryFn: async () => (await api.get('/annee/eleves.php?classe=' + classe + '&per_page=200')).data.data, enabled: !!classe });
  const { data: abs = [] } = useQuery({ queryKey: ['absences-classe', classe], queryFn: async () => (await api.get('/annee/absences.php?classe=' + classe)).data.data, enabled: !!classe });
  const cumul = useMemo(() => { const m = {}; for (const a of abs) { const nj = (a.abs_total || 0) - (a.abs_justifies || 0); m[a.eleve_id] = (m[a.eleve_id] || 0) + nj; } return m; }, [abs]);
  const save = useMutation({ mutationFn: async () => api.post('/annee/absences_bulk.php', { classe_id: Number(classe), mois_id: Number(mois), absences: eleves.map((e) => ({ eleve_id: e.id_eleve, abs_total: Number(cells[e.id_eleve + ':t'] ?? 0), abs_justifies: Number(cells[e.id_eleve + ':j'] ?? 0) })) }), onSuccess: () => { qc.invalidateQueries({ queryKey: ['absences'] }); toast.success(t('notes.saved')); } });
  const col = (h) => h > 35 ? 'text-danger font-bold' : h > 20 ? 'text-warning font-bold' : 'text-foreground';
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('absences.title')}</h1><p className="mt-1 text-sm text-muted">{t('absences.subtitle')}</p></div>
      <div className="mb-4 grid grid-cols-2 gap-3 sm:max-w-sm"><Select label="Classe" value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select><Select label={t('absences.mois')} value={mois} onChange={(e) => setMois(e.target.value)}>{Array.from({ length: 9 }, (_, i) => i + 1).map((m) => <option key={m} value={m}>{t('mois.' + m)}</option>)}</Select></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('absences.eleve')}</th><th className="px-4 py-3">{t('absences.total')}</th><th className="px-4 py-3">{t('absences.justifies')}</th><th className="px-4 py-3">{t('absences.cumul')}</th></tr></thead>
        <tbody>{eleves.map((e) => (<tr key={e.id_eleve} className="border-b border-border last:border-0"><td className="px-4 py-2 text-foreground">{e.nom} {e.prenom}</td><td className="px-4 py-2"><Input type="number" className="w-20" value={cells[e.id_eleve + ':t'] ?? ''} onChange={(ev) => setCells((p) => ({ ...p, [e.id_eleve + ':t']: ev.target.value }))} /></td><td className="px-4 py-2"><Input type="number" className="w-20" value={cells[e.id_eleve + ':j'] ?? ''} onChange={(ev) => setCells((p) => ({ ...p, [e.id_eleve + ':j']: ev.target.value }))} /></td><td className={`px-4 py-2 ${col(cumul[e.id_eleve] || 0)}`}>{cumul[e.id_eleve] || 0}h</td></tr>))}{eleves.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
      {classe && <div className="mt-4 flex justify-end"><Button onClick={() => save.mutate()}>{t('notes.save')}</Button></div>}
    </div>
  );
}