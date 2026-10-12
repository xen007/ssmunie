import { BookOpen, ClipboardList, GraduationCap, Grid3x3, Home, Layers, School, Settings, ShieldCheck, UserCog, Users, UserRound, Wallet, CalendarClock, ReceiptText } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../core/providers/AuthProvider';
import { useT } from '../../i18n';
import PerimetreSelector from './PerimetreSelector';
import YearSelector from './YearSelector';

const items = [
  { key: 'nav.home', to: '/', icon: Home, end: true },
  { key: 'nav.students', to: '/eleves', icon: Users, permission: 'students.view' },
  { key: 'nav.notes', to: '/notes', icon: BookOpen, permission: 'grades.view' },
  { key: 'nav.bulletins', to: '/bulletins', icon: ClipboardList, permission: 'grades.view' },
  { key: 'nav.conseil', to: '/conseil-de-classe', icon: UserRound, permission: 'grades.edit' },
  { key: 'nav.scolarite', to: '/scolarite', icon: Wallet, permission: 'finance.view' },
  { key: 'nav.inscriptions', to: '/inscriptions', icon: ReceiptText, permission: 'finance.view' },
  { key: 'nav.absences', to: '/absences', icon: CalendarClock, permission: 'students.view' },
  { key: 'nav.teachers', to: '/enseignants', icon: GraduationCap, permission: 'hr.view' },
  { key: 'nav.classes', to: '/classes', icon: School, permission: 'settings.view' },
  { key: 'nav.habilitations', to: '/habilitations', icon: Grid3x3, permission: 'hr.view' },
  { key: 'nav.refs', to: '/refs/sections', icon: Layers },
  { key: 'nav.settings', to: '/parametres/matieres', icon: Settings, permission: 'settings.view' },
  { key: 'nav.accounts', to: '/accountManage', icon: UserCog, permission: 'accounts.manage' },
  { key: 'nav.roles', to: '/rolesManage', icon: ShieldCheck, permission: 'roles.manage' },
];

export default function Sidebar({ mobileOpen = false, onClose }) {
  const t = useT();
  const { hasPermission } = useAuth();
  const visible = items.filter((it) => !it.permission || hasPermission(it.permission));
  const nav = (
    <nav className="flex flex-col gap-1">
      {visible.map(({ key, to, icon: Icon, end }) => (
        <NavLink key={key} to={to} end={end} onClick={onClose} className={({ isActive }) => [
          'flex items-center gap-3 rounded-md border-l-2 px-3 py-2 text-sm font-medium transition-colors',
          isActive ? 'border-primary bg-primary-soft text-primary' : 'border-transparent text-muted hover:bg-primary-soft hover:text-primary',
        ].join(' ')}>
          <Icon className="h-4 w-4" aria-hidden="true" />
          <span>{t(key)}</span>
        </NavLink>
      ))}
    </nav>
  );
  const content = (
    <div className="flex flex-col gap-3">
      <YearSelector />
      <PerimetreSelector />
      {nav}
    </div>
  );
  return (
    <>
      <aside className="hidden w-64 shrink-0 flex-col border-r border-border bg-surface p-3 md:flex">{content}</aside>
      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={onClose} aria-hidden="true" />
          <aside className="relative flex h-full w-64 flex-col overflow-y-auto border-r border-border bg-surface p-3 shadow-lg">{content}</aside>
        </div>
      )}
    </>
  );
}