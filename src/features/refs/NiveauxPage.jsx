import { useMemo } from 'react';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Card from '../../shared/ui/Card';
import RefsTabs from './RefsTabs';

export default function NiveauxPage() {
  const t = useT();
  const { lang } = useLang();
  const { niveaux, sections, filieres, perimetre } = usePerimetre();

  const secLabel = (id) => {
    const s = sections.find((x) => x.id === id);
    return s ? (lang === 'fr' ? s.libelle_fr : s.libelle_en) : id;
  };
  const filLabel = (id) => {
    const f = filieres.find((x) => x.id === id);
    return f ? f.code : id;
  };

  const filtered = useMemo(
    () =>
      niveaux.filter(
        (n) =>
          (!perimetre.section || n.section_id === perimetre.section) &&
          (!perimetre.filiere || n.filiere_id === perimetre.filiere)
      ),
    [niveaux, perimetre.section, perimetre.filiere]
  );

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4">
        <h1 className="text-2xl font-bold tracking-tight">{t('refs.niveaux')}</h1>
        <p className="mt-1 text-sm text-muted">
          {t('refs.niveauxHint', { count: filtered.length })}
        </p>
      </div>
      <RefsTabs />
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                <th className="px-4 py-3 font-medium">{t('refs.code')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.libelle')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.section')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.filiere')}</th>
                <th className="px-4 py-3 font-medium">{t('refs.ordre')}</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((n) => (
                <tr key={n.id_niveau} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{n.code_niveau}</td>
                  <td className="px-4 py-3 text-foreground">{n.libellee_niveau}</td>
                  <td className="px-4 py-3 text-muted">{secLabel(n.section_id)}</td>
                  <td className="px-4 py-3 text-muted">{filLabel(n.filiere_id)}</td>
                  <td className="px-4 py-3 text-muted">{n.ordre}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
