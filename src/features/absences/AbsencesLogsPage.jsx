import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';

export default function AbsencesLogsPage() {
  const t = useT();
  const { data: rows = [] } = useQuery({ queryKey: ['absences-logs'], queryFn: async () => (await api.get('/annee/absences_logs.php')).data.data });
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('absences.logs')}</h1></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">Date</th><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">Action</th><th className="px-4 py-3">{t('absences.motif')}</th><th className="px-4 py-3">{t('absences.total')}</th><th className="px-4 py-3">{t('absences.justifies')}</th></tr></thead>
        <tbody>{rows.map((r) => (<tr key={r.id_log} className="border-b border-border last:border-0"><td className="px-4 py-2 text-muted">{r.date_action}</td><td className="px-4 py-2 text-foreground">{r.nom} {r.prenom}</td><td className="px-4 py-2 text-muted">{r.action}</td><td className="px-4 py-2 text-muted">{r.motif || '—'}</td><td className="px-4 py-2 text-muted">{r.ancien_total}→{r.nouveau_total}</td><td className="px-4 py-2 text-muted">{r.ancien_justifies}→{r.nouveau_justifies}</td></tr>))}{rows.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
    </div>
  );
}