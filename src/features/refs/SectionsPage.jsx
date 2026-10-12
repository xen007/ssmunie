import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Card from '../../shared/ui/Card';
import RefsTabs from './RefsTabs';

export default function SectionsPage() {
  const t = useT();
  const { lang } = useLang();
  const { sections } = usePerimetre();
  const lab = (s) => (lang === 'fr' ? s.libelle_fr : s.libelle_en);

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4">
        <h1 className="text-2xl font-bold tracking-tight">{t('refs.sections')}</h1>
        <p className="mt-1 text-sm text-muted">{t('refs.sectionsHint')}</p>
      </div>
      <RefsTabs />
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                <th className="px-4 py-3 font-medium">{t('refs.code')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.libelle')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.ministere')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.examen')}</th>
              </tr>
            </thead>
            <tbody>
              {sections.map((s) => (
                <tr key={s.id} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{s.code}</td>
                  <td className="px-4 py-3 text-foreground">{lab(s)}</td>
                  <td className="px-4 py-3 text-muted">{s.ministere}</td>
                  <td className="px-4 py-3 text-muted">{s.examen_final || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
