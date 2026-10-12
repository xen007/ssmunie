import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';
import RefsTabs from './RefsTabs';

export default function MoisJoursPage() {
  const t = useT();

  const { data: mois = [] } = useQuery({
    queryKey: ['mois'],
    queryFn: async () => (await api.get('/annee/mois.php')).data.data,
  });
  const { data: jours = [] } = useQuery({
    queryKey: ['jours'],
    queryFn: async () => (await api.get('/annee/jours.php')).data.data,
  });

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4">
        <h1 className="text-2xl font-bold tracking-tight">{t('refs.moisJours')}</h1>
        <p className="mt-1 text-sm text-muted">{t('refs.moisJoursHint')}</p>
      </div>
      <RefsTabs />

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="p-4">
          <h2 className="mb-3 text-sm font-semibold text-foreground">{t('refs.mois')}</h2>
          <div className="flex flex-wrap gap-2">
            {mois.map((m) => (
              <span key={m.id_mois} className="rounded-md bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">
                {m.ordre_scolaire}. {m.libellee_mois}
              </span>
            ))}
          </div>
        </Card>

        <Card className="p-4">
          <h2 className="mb-3 text-sm font-semibold text-foreground">{t('refs.jours')}</h2>
          <div className="flex flex-wrap gap-2">
            {jours.map((j) => (
              <span key={j.id_jour} className="rounded-md bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary">
                {j.libellee_jour}
              </span>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
