import { NavLink } from 'react-router-dom';
import { useT } from '../../i18n';

const tabs = [
  { to: '/refs/sections', key: 'refs.sections' },
  { to: '/refs/niveaux', key: 'refs.niveaux' },
  { to: '/refs/series', key: 'refs.series' },
  { to: '/refs/options', key: 'refs.options' },
  { to: '/refs/mois-jours', key: 'refs.moisJours' },
];

/** Onglets de navigation entre les pages référentiels. */
export default function RefsTabs() {
  const t = useT();
  return (
    <div className="mb-4 flex flex-wrap gap-1 border-b border-border">
      {tabs.map(({ to, key }) => (
        <NavLink
          key={to}
          to={to}
          className={({ isActive }) =>
            [
              '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors',
              isActive ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-foreground',
            ].join(' ')
          }
        >
          {t(key)}
        </NavLink>
      ))}
    </div>
  );
}
