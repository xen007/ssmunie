import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';
import RefsTabs from './RefsTabs';

const SECTEURS = ['industriel', 'commercial', 'tertiaire', 'artistique', 'autres'];

export default function OptionsPage() {
  const t = useT();
  const [secteur, setSecteur] = useState('');

  const { data: options = [], isLoading } = useQuery({
    queryKey: ['options', secteur],
    queryFn: async () => (await api.get('/annee/options.php', { params: secteur ? { secteur } : {} })).data.data,
  });

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{t('refs.options')}</h1>
          <p className="mt-1 text-sm text-muted">{t('refs.optionsHint', { count: options.length })}</p>
        </div>
        <select
          value={secteur}
          onChange={(e) => setSecteur(e.target.value)}
          className="h-9 rounded-md border border-border bg-background px-2 text-sm text-foreground focus:border-primary focus:outline-none"
        >
          <option value="">{t('refs.allSecteurs')}</option>
          {SECTEURS.map((s) => (
            <option key={s} value={s}>
              {t(`refs.secteur.${s}`)}
            </option>
          ))}
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
                  <th className="px-4 py-3 font-medium">{t('refs.secteur')}</th>
                  <th className="px-4 py-3 font-medium">{t('refs.domaine')}</th>
                </tr>
              </thead>
              <tbody>
                {options.map((o) => (
                  <tr key={o.id_option} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 font-medium text-foreground">{o.code_option}</td>
                    <td className="px-4 py-3 text-foreground">{o.libellee_option}</td>
                    <td className="px-4 py-3">
                      <span className="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-medium text-primary">
                        {t(`refs.secteur.${o.secteur}`)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-muted">{o.domaine || '—'}</td>
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
