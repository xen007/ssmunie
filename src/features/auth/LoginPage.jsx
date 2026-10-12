import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, Moon, Sun } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useAnnee } from '../../core/providers/AnneeProvider';
import { useAuth } from '../../core/providers/AuthProvider';
import { useTheme } from '../../core/providers/ThemeProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';

/**
 * Page de connexion (B1) : formulaire réel + redirection /setup si needsSetup.
 */
export default function LoginPage() {
  const t = useT();
  const { lang, setLang } = useLang();
  const { theme, toggleTheme } = useTheme();
  const { etablissement } = useAnnee();
  const { login, loading } = useAuth();
  const navigate = useNavigate();

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await api.get('/setup/checkSetup.php');
        if (active && res.data.needsSetup) {
          navigate('/setup', { replace: true });
        }
      } catch {
        /* ignore */
      } finally {
        if (active) setChecking(false);
      }
    })();
    return () => {
      active = false;
    };
  }, [navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await login(username, password);
      navigate('/', { replace: true });
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    }
  };

  const nextLang = lang === 'fr' ? 'en' : 'fr';

  if (checking) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-muted">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-background p-4">
      <div className="absolute right-4 top-4 flex items-center gap-1.5">
        <button
          type="button"
          onClick={() => setLang(nextLang)}
          className="flex h-9 items-center rounded-md px-2.5 text-xs font-semibold uppercase text-muted transition-colors hover:bg-primary-soft hover:text-primary"
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
      </div>

      <Card className="w-full max-w-md p-8 shadow-lg">
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-md bg-primary text-white shadow-md">
            <GraduationCap className="h-7 w-7" aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-foreground">
              {etablissement?.nom ?? t('app.name')}
            </h1>
            <p className="mt-1 text-sm text-muted">{t('app.tagline')}</p>
          </div>
          {etablissement?.devise && (
            <span className="rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent">
              {etablissement.devise}
            </span>
          )}
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <Input
            label={t('auth.username')}
            name="username"
            autoComplete="username"
            placeholder="admin"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
          />
          <Input
            label={t('auth.password')}
            name="password"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          <Button type="submit" disabled={loading} className="mt-2 w-full">
            {loading ? t('common.loading') : t('auth.login')}
          </Button>
        </form>

        <p className="mt-6 text-center text-xs text-muted">{t('auth.subtitle')}</p>
      </Card>
    </div>
  );
}
