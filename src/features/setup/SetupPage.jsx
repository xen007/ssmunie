import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Check, GraduationCap } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useAuth } from '../../core/providers/AuthProvider';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';

const STEPS = ['setup.step1', 'setup.step2', 'setup.step3'];

/**
 * Setup wizard (B1) — 3 étapes : Établissement / Année / Admin.
 * Validation client + serveur. POST complete.php → auto-login → /.
 */
export default function SetupPage() {
  const t = useT();
  const { setUser } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const [step, setStep] = useState(() => {
    const s = parseInt(searchParams.get('step') ?? '0', 10);
    return Number.isFinite(s) ? Math.min(2, Math.max(0, s)) : 0;
  });
  const [checking, setChecking] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const [etab, setEtab] = useState({
    nom: '',
    initiale: '',
    ministere: 'MINEDUB',
    contact: '',
    email: '',
    adresse: '',
    devise: '',
  });
  const [annee, setAnnee] = useState({ libellee: '', date_debut: '', date_fin: '' });
  const [admin, setAdmin] = useState({ username: '', email: '', password: '', confirm: '' });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await api.get('/setup/checkSetup.php');
        if (active && !res.data.needsSetup) {
          navigate('/login', { replace: true });
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

  const setE = (k) => (e) => setEtab((s) => ({ ...s, [k]: e.target.value }));
  const setA = (k) => (e) => setAnnee((s) => ({ ...s, [k]: e.target.value }));
  const setAd = (k) => (e) => setAdmin((s) => ({ ...s, [k]: e.target.value }));

  const validateStep = () => {
    const errs = {};
    if (step === 0) {
      if (!etab.nom.trim()) errs.nom = t('setup.errors.required');
    } else if (step === 1) {
      if (!annee.libellee.trim()) errs.libellee = t('setup.errors.required');
      if (!annee.date_debut || !annee.date_fin) errs.dates = t('setup.errors.required');
      else if (annee.date_fin <= annee.date_debut) errs.dates = t('setup.errors.dateOrder');
    } else if (step === 2) {
      if (admin.username.trim().length < 3) errs.username = t('setup.errors.username');
      if (admin.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(admin.email))
        errs.email = t('setup.errors.email');
      if (admin.password.length < 8) errs.password = t('setup.errors.password');
      if (admin.password !== admin.confirm) errs.confirm = t('setup.errors.confirm');
    }
    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const next = () => {
    if (validateStep()) setStep((s) => Math.min(2, s + 1));
  };

  const finish = async () => {
    if (!validateStep()) return;
    setSubmitting(true);
    try {
      const res = await api.post('/setup/complete.php', {
        etablissement: etab,
        annee,
        admin: { username: admin.username, email: admin.email, password: admin.password },
      });
      setUser(res.data.user);
      toast.success(t('setup.success'));
      if (res.data.dbName) {
        toast.success(t('setup.dbCreated', { name: res.data.dbName }));
      }
      navigate('/', { replace: true });
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    } finally {
      setSubmitting(false);
    }
  };

  if (checking) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-muted">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-xl p-8 shadow-lg">
        <div className="mb-6 flex flex-col items-center gap-2 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-md bg-primary text-white shadow-md">
            <GraduationCap className="h-6 w-6" aria-hidden="true" />
          </div>
          <h1 className="text-xl font-bold tracking-tight text-foreground">{t('setup.title')}</h1>
        </div>

        {/* Stepper */}
        <div className="mb-8 flex items-center">
          {STEPS.map((key, i) => (
            <div key={key} className="flex flex-1 items-center">
              <div className="flex flex-col items-center gap-1">
                <div
                  className={[
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                    i < step
                      ? 'bg-success text-white'
                      : i === step
                        ? 'bg-primary text-white'
                        : 'bg-surface text-muted border border-border',
                  ]
                    .filter(Boolean)
                    .join(' ')}
                >
                  {i < step ? <Check className="h-4 w-4" /> : i + 1}
                </div>
                <span
                  className={[
                    'text-[11px]',
                    i === step ? 'font-semibold text-primary' : 'text-muted',
                  ].join(' ')}
                >
                  {t(key)}
                </span>
              </div>
              {i < STEPS.length - 1 && (
                <div className="mx-2 h-px flex-1 bg-border" aria-hidden="true" />
              )}
            </div>
          ))}
        </div>

        {/* Étape 1 : Établissement */}
        {step === 0 && (
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Input label={t('setup.nom')} value={etab.nom} onChange={setE('nom')} error={errors.nom} />
            </div>
            <Input label={t('setup.initiale')} value={etab.initiale} onChange={setE('initiale')} maxLength={3} />
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-foreground">{t('setup.ministere')}</label>
              <select
                value={etab.ministere}
                onChange={setE('ministere')}
                className="h-10 w-full rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/40"
              >
                <option value="MINEDUB">MINEDUB</option>
                <option value="MINESEC">MINESEC</option>
              </select>
            </div>
            <Input label={t('setup.devise')} value={etab.devise} onChange={setE('devise')} />
            <Input label={t('setup.contact')} value={etab.contact} onChange={setE('contact')} />
            <div className="sm:col-span-2">
              <Input label={t('setup.adresse')} value={etab.adresse} onChange={setE('adresse')} />
            </div>
            <div className="sm:col-span-2">
              <Input label={t('setup.emailEtab')} type="email" value={etab.email} onChange={setE('email')} />
            </div>
          </div>
        )}

        {/* Étape 2 : Année scolaire */}
        {step === 1 && (
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Input label={t('setup.anneeLibellee')} value={annee.libellee} onChange={setA('libellee')} placeholder="2026-2027" error={errors.libellee} />
            </div>
            <Input label={t('setup.dateDebut')} type="date" value={annee.date_debut} onChange={setA('date_debut')} />
            <Input label={t('setup.dateFin')} type="date" value={annee.date_fin} onChange={setA('date_fin')} />
            {errors.dates && <p className="text-xs text-danger sm:col-span-2">{errors.dates}</p>}
          </div>
        )}

        {/* Étape 3 : Compte admin */}
        {step === 2 && (
          <div className="grid gap-4">
            <Input label={t('setup.adminUsername')} value={admin.username} onChange={setAd('username')} error={errors.username} />
            <Input label={t('setup.adminEmail')} type="email" value={admin.email} onChange={setAd('email')} error={errors.email} />
            <Input label={t('setup.adminPassword')} type="password" value={admin.password} onChange={setAd('password')} error={errors.password} />
            <Input label={t('setup.adminConfirm')} type="password" value={admin.confirm} onChange={setAd('confirm')} error={errors.confirm} />
          </div>
        )}

        {/* Navigation */}
        <div className="mt-8 flex justify-between">
          <Button
            variant="ghost"
            onClick={() => setStep((s) => Math.max(0, s - 1))}
            disabled={step === 0}
            icon={ArrowLeft}
          >
            {t('setup.back')}
          </Button>
          {step < 2 ? (
            <Button onClick={next} iconRight={ArrowRight}>
              {t('setup.next')}
            </Button>
          ) : (
            <Button onClick={finish} disabled={submitting} icon={Check}>
              {submitting ? t('setup.creatingDb') : t('setup.finish')}
            </Button>
          )}
        </div>
      </Card>
    </div>
  );
}
