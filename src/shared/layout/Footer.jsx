import config from '../../core/config';
import { useT } from '../../i18n';

/**
 * Pied de page : copyright + version + mention SSMUNIE.
 */
export default function Footer() {
  const t = useT();
  const year = new Date().getFullYear();

  return (
    <footer className="shrink-0 border-t border-border bg-surface px-4 py-3 text-center text-xs text-muted">
      © {year} {t('app.name')} · v{config.version} — {t('footer.rights')}
    </footer>
  );
}
