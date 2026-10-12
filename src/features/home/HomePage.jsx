import { CalendarDays, School, Sparkles } from 'lucide-react';
import { useAnnee } from '../../core/providers/AnneeProvider';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';

/**
 * Contenu de la page d'accueil.
 * Le layout (Header + Sidebar + Footer) est fourni par AppLayout (B1.1).
 */
export default function HomePage() {
  const t = useT();
  const { etablissement, anneeCourante, isLoading } = useAnnee();

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold tracking-tight">{t('common.welcome')}</h1>
        <p className="mt-1 text-sm text-muted">{t('app.tagline')}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="p-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary-soft text-primary">
              <School className="h-5 w-5" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-muted">
                {t('home.establishment')}
              </p>
              <p className="text-sm font-semibold">{etablissement?.nom ?? 'SSMUNIE'}</p>
            </div>
          </div>
        </Card>

        <Card className="p-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-accent-soft text-accent">
              <CalendarDays className="h-5 w-5" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-muted">
                {t('home.activeYear')}
              </p>
              <p className="text-sm font-semibold">
                {isLoading ? t('common.loading') : anneeCourante?.libellee ?? '—'}
              </p>
            </div>
          </div>
        </Card>
      </div>

      <Card className="mt-4 flex items-center gap-3 border-dashed p-8">
        <Sparkles className="h-5 w-5 text-accent" aria-hidden="true" />
        <p className="text-sm text-muted">{t('home.placeholder')}</p>
      </Card>
    </div>
  );
}
