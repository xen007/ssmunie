import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Card from '../../shared/ui/Card';
import RefsTabs from './RefsTabs';

export default function SeriesPage() {
  const t = useT();
  const { lang } = useLang();
  const { sections } = usePerimetre();
  const [cycle, setCycle] = useState(0);

  const { data: series = [], isLoading } = useQuery({
    queryKey: ['series', cycle],
    queryFn: async () => (await api.get('/annee/series.php', { params: cycle ? { cycle } : {} })).data.data,
  });

  const secLabel = (id) => {
    const s = sections.find((x) => x.id === id);
    return s ? (lang === 'fr' ? s.libelle_fr : s.libelle_en) : id;
  };

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{t('refs.series')}</h1>
          <p className="mt-1 text-sm text-muted">{t('refs.seriesHint', { count: series.length })}</p>
        </div>
        <select
          value={cycle}
          onChange={(e) => setCycle(Number(e.target.value))}
          className="h-9 rounded-md border border-border bg-background px-2 text-sm text-foreground focus:border-primary focus:outline-none"
        >
          <option value={0}>{t('refs.allCycles')}</option>
          <option value={1}>{t('refs.cycle1')}</option>
          <option value={2}>{t('refs.cycle2')}</option>
        </select>
      </div>
      <RefsTabs />
      <Card className="overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center text-sm text-muted">{t('common.loading')}</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-3 font-medium">{t('refs.code')}</th>
                  <th className="px-4 py-3 font-medium">{t('refs.libelle')}</th>
                  <th className="px-4 py-3 font-medium">{t('refs.section')}</th>
                  <th className="px-4 py-3 font-medium">{t('refs.cycle')}</th>
                </tr>
              </thead>
              <tbody>
                {series.map((s) => (
                  <tr key={s.id_serie} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 font-medium text-foreground">{s.code_serie}</td>
                    <td className="px-4 py-3 text-foreground">{s.libellee_serie}</td>
                    <td className="px-4 py-3 text-muted">{secLabel(s.section_id)}</td>
                    <td className="px-4 py-3 text-muted">{s.cycle}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
