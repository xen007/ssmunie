import { useEffect, useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import { useAuth } from '../providers/AuthProvider';
import { useT } from '../../i18n';
import Modal from '../../shared/ui/Modal';
import Button from '../../shared/ui/Button';

function format(seconds) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Modale d'avertissement de session (F17).
 * Countdown = temps réel restant jusqu'au timeout idle (30 min).
 */
export default function SessionWarningModal() {
  const { showWarning, remaining, refreshSession, logout } = useAuth();
  const t = useT();
  const [countdown, setCountdown] = useState(remaining);

  useEffect(() => {
    if (!showWarning) return undefined;
    setCountdown(remaining);
    const id = setInterval(() => setCountdown((c) => Math.max(0, c - 1)), 1000);
    return () => clearInterval(id);
  }, [showWarning, remaining]);

  useEffect(() => {
    if (showWarning && countdown <= 0) {
      logout();
    }
  }, [showWarning, countdown, logout]);

  return (
    <Modal open={showWarning} onClose={null} title={t('session.warningTitle')} size="sm">
      <div className="flex flex-col items-center gap-3 text-center">
        <AlertTriangle className="h-9 w-9 text-warning" aria-hidden="true" />
        <p className="text-sm text-muted">{t('session.warningText')}</p>
        <p className="text-3xl font-bold tabular-nums text-foreground">{format(countdown)}</p>
      </div>
      <div className="mt-4 flex justify-center gap-2">
        <Button variant="secondary" onClick={logout}>
          {t('session.logout')}
        </Button>
        <Button onClick={refreshSession}>{t('session.stayConnected')}</Button>
      </div>
    </Modal>
  );
}
