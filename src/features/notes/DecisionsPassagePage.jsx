import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';
import Select from '../../shared/ui/Select';

export default function DecisionsPassagePage() {
  const t = useT(); const [classe, setClasse] = useState('');
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: rows = [] } = useQuery({ queryKey: ['decision-passage', classe], queryFn: async () => (await api.get('/annee/decision_passage.php?classe=' + classe)).data.data, enabled: !!classe });
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('decisions.title')}</h1><p className="mt-1 text-sm text-muted">{t('decisions.subtitle')}</p></div>
      <div className="mb-4 max-w-xs"><Select label="Classe" value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('bulletins.moyenneGenerale')}</th><th className="px-4 py-3">{t('decisions.decisionAuto')}</th><th className="px-4 py-3">{t('decisions.heures')}</th><th className="px-4 py-3">{t('decisions.sanction')}</th><th className="px-4 py-3">{t('decisions.decisionFinale')}</th></tr></thead>
        <tbody>{rows.map((r) => (<tr key={r.eleve_id} className="border-b border-border last:border-0"><td className="px-4 py-2 text-foreground">{r.nom} {r.prenom}</td><td className="px-4 py-2 text-muted">{r.moyenne != null ? Number(r.moyenne).toFixed(2) : '·'}</td><td className="px-4 py-2 text-muted">{r.decision_auto ? t('decision.' + r.decision_auto) : '·'}</td><td className="px-4 py-2 text-warning">{r.heures_non_justifiees}h</td><td className="px-4 py-2 text-muted">{r.sanction?.sanction || '—'}</td><td className={`px-4 py-2 font-medium ${r.decision_finale === 'exclu' ? 'text-danger' : 'text-success'}`}>{t('decision.' + r.decision_finale)}</td></tr>))}{rows.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
    </div>
  );
}