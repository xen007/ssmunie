import { useEffect, useRef, useState } from 'react';
import { ChevronDown, GraduationCap, KeyRound, LogOut, Menu, Moon, Sun, UserRound } from 'lucide-react';
import { useAnnee } from '../../core/providers/AnneeProvider';
import { useAuth } from '../../core/providers/AuthProvider';
import { useTheme } from '../../core/providers/ThemeProvider';
import { useT, useLang } from '../../i18n';
import Modal from '../ui/Modal';
import ChangePassword from '../../features/profile/ChangePassword';

/**
 * En-tête SSMUNIE : logo + établissement + bascules + menu profil.
 */
export default function Header({ onToggleSidebar }) {
  const t = useT();
  const { lang, setLang } = useLang();
  const { theme, toggleTheme } = useTheme();
  const { etablissement } = useAnnee();
  const { user, logout } = useAuth();

  const [menuOpen, setMenuOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [changePwOpen, setChangePwOpen] = useState(false);
  const menuRef = useRef(null);

  const nextLang = lang === 'fr' ? 'en' : 'fr';

  useEffect(() => {
    const onClick = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const roleLabel = user?.role ? t(`role.${user.role}`, null) : '';

  return (
    <>
      <header className="flex h-14 shrink-0 items-center justify-between border-b border-border bg-surface px-4">
        <div className="flex items-center gap-3">
          {onToggleSidebar && (
            <button
              type="button"
              onClick={onToggleSidebar}
              className="flex h-9 w-9 items-center justify-center rounded-md text-muted transition-colors hover:bg-primary-soft hover:text-primary md:hidden"
              aria-label={t('nav.menu')}
              title={t('nav.menu')}
            >
              <Menu className="h-5 w-5" aria-hidden="true" />
            </button>
          )}
          <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-white shadow-sm">
            <GraduationCap className="h-5 w-5" aria-hidden="true" />
          </div>
          <div className="leading-tight">
            <div className="text-sm font-semibold tracking-tight text-foreground">{t('app.name')}</div>
            <div className="text-xs text-muted">{etablissement?.nom ?? 'SSMUNIE'}</div>
          </div>
        </div>

        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => setLang(nextLang)}
            className="flex h-9 items-center rounded-md px-2.5 text-xs font-semibold uppercase text-muted transition-colors hover:bg-primary-soft hover:text-primary"
            title={nextLang === 'en' ? 'English' : 'Français'}
          >
            {nextLang}
          </button>

          <button
            type="button"
            onClick={toggleTheme}
            className="flex h-9 w-9 items-center justify-center rounded-md text-muted transition-colors hover:bg-primary-soft hover:text-primary"
            aria-label={theme === 'dark' ? t('theme.light') : t('theme.dark')}
            title={theme === 'dark' ? t('theme.light') : t('theme.dark')}
          >
            {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </button>

          {user && (
            <div className="relative ml-1" ref={menuRef}>
              <button
                type="button"
                onClick={() => setMenuOpen((o) => !o)}
                className="flex items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-primary-soft"
              >
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent">
                  {(user.username ?? '?').slice(0, 2).toUpperCase()}
                </div>
                <div className="hidden text-left leading-tight sm:block">
                  <div className="text-sm font-medium text-foreground">{user.username}</div>
                  <div className="text-[11px] text-muted">{roleLabel}</div>
                </div>
                <ChevronDown className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
              </button>

              {menuOpen && (
                <div className="absolute right-0 top-full z-40 mt-1 w-52 overflow-hidden rounded-md border border-border bg-surface shadow-lg">
                  <button
                    type="button"
                    onClick={() => {
                      setProfileOpen(true);
                      setMenuOpen(false);
                    }}
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-foreground transition-colors hover:bg-primary-soft hover:text-primary"
                  >
                    <UserRound className="h-4 w-4" />
                    {t('profile.myProfile')}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setChangePwOpen(true);
                      setMenuOpen(false);
                    }}
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-foreground transition-colors hover:bg-primary-soft hover:text-primary"
                  >
                    <KeyRound className="h-4 w-4" />
                    {t('profile.changePassword')}
                  </button>
                  <div className="my-1 h-px bg-border" />
                  <button
                    type="button"
                    onClick={logout}
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-danger transition-colors hover:bg-danger/10"
                  >
                    <LogOut className="h-4 w-4" />
                    {t('nav.logout')}
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </header>

      {/* Modale profil (lecture seule) */}
      <Modal open={profileOpen} onClose={() => setProfileOpen(false)} title={t('profile.myProfile')} size="sm">
        <div className="flex flex-col gap-3 text-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-base font-semibold text-accent">
              {(user?.username ?? '?').slice(0, 2).toUpperCase()}
            </div>
            <div>
              <div className="font-semibold text-foreground">{user?.username}</div>
              <div className="text-muted">{roleLabel}</div>
            </div>
          </div>
          <div className="rounded-md bg-background p-3 text-muted">
            <div className="text-xs uppercase tracking-wide">Email</div>
            <div className="text-foreground">{user?.email || '—'}</div>
          </div>
          <div className="rounded-md bg-background p-3 text-muted">
            <div className="text-xs uppercase tracking-wide">{t('profile.roles')}</div>
            <div className="text-foreground">
              {(user?.roles ?? []).map((r) => t(`role.${r}`, null)).join(', ') || '—'}
            </div>
          </div>
        </div>
      </Modal>

      <ChangePassword open={changePwOpen} onClose={() => setChangePwOpen(false)} />
    </>
  );
}
