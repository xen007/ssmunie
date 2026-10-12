import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

const DEC = ['admis','admis_conditionnel','redouble','exclu','transfere'];

export default function ConseilClassePage() {
  const t = useT();
  const qc = useQueryClient();
  const [classe, setClasse] = useState('');
  const [decisions, setDecisions] = useState({});
  const [motifs, setMotifs] = useState({});
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-classe', classe], queryFn: async () => (await api.get('/annee/eleves.php?classe=' + classe + '&per_page=200')).data.data, enabled: !!classe });
  const { data: bull } = useQuery({ queryKey: ['bulletins-annual', classe], queryFn: async () => (await api.get('/annee/bulletins.php?classe=' + classe + '&report_type=annual')).data.data, enabled: !!classe });
  const { data: existing } = useQuery({ queryKey: ['decisions', classe], queryFn: async () => (await api.get('/annee/decisions.php?classe=' + classe)).data.data, enabled: !!classe });

  const pickClasse = (id) => { setClasse(id); const map = {}; const mm = {}; for (const d of existing || []) { map[d.eleve_id] = d.decision; mm[d.eleve_id] = d.motif || ''; } setDecisions(map); setMotifs(mm); };
  const autoOf = (id) => (bull?.students?.[id]?.decision) || '';

  const save = useMutation({ mutationFn: async () => api.post('/annee/conseil_classe.php', { classe_id: Number(classe), decisions: (eleves || []).map((e) => ({ eleve_id: e.id_eleve, decision: decisions[e.id_eleve] || autoOf(e.id_eleve) || 'admis', motif: motifs[e.id_eleve] || '' })) }), onSuccess: (r) => { qc.invalidateQueries({ queryKey: ['decisions'] }); toast.success(t('conseil.saved', { run: r.data.data.run_id })); } });
  const rollback = useMutation({ mutationFn: async () => api.delete('/annee/conseil_classe.php?run_id=' + (existing?.[0]?.run_id || 0)), onSuccess: () => { qc.invalidateQueries({ queryKey: ['decisions'] }); toast.success(t('conseil.rolled')); } });

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('conseil.title')}</h1><p className="mt-1 text-sm text-muted">{t('conseil.subtitle')}</p></div>
      <div className="mb-4 max-w-xs"><Select label={t('conseil.class')} value={classe} onChange={(e) => pickClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('bulletins.moyenneGenerale')}</th><th className="px-4 py-3">{t('conseil.decision')}</th><th className="px-4 py-3">{t('conseil.motif')}</th></tr></thead>
        <tbody>{(eleves || []).map((e) => { const auto = autoOf(e.id_eleve); return (<tr key={e.id_eleve} className="border-b border-border last:border-0"><td className="px-4 py-2 text-foreground">{e.nom} {e.prenom}</td><td className="px-4 py-2 text-muted">{bull?.students?.[e.id_eleve]?.moyenne_generale != null ? Number(bull.students[e.id_eleve].moyenne_generale).toFixed(2) : '·'}</td><td className="px-4 py-2"><Select value={decisions[e.id_eleve] || auto || ''} onChange={(ev) => setDecisions((p) => ({ ...p, [e.id_eleve]: ev.target.value }))} className="w-44"><option value="">—</option>{DEC.map((d) => <option key={d} value={d}>{t('decision.' + d)}</option>)}</Select></td><td className="px-4 py-2"><Input value={motifs[e.id_eleve] || ''} onChange={(ev) => setMotifs((p) => ({ ...p, [e.id_eleve]: ev.target.value }))} /></td></tr>); })}</tbody>
      </table></div></Card>
      <div className="mt-4 flex items-center justify-between">
        <Button variant="danger" onClick={() => rollback.mutate()}>{t('conseil.rollback')}</Button>
        <Button disabled={!classe} onClick={() => save.mutate()}>{t('conseil.save')}</Button>
      </div>
    </div>
  );
}