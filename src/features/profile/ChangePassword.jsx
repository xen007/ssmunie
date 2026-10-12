import { useState } from 'react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';

/**
 * Modale de changement de mot de passe (self-service, F16).
 * Accessible depuis le menu profil du Header.
 */
export default function ChangePassword({ open, onClose }) {
  const t = useT();
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    if (newPassword !== confirm) {
      toast.error(t('setup.errors.confirm'));
      return;
    }
    setSaving(true);
    try {
      await api.post('/changePassword.php', { old_password: oldPassword, new_password: newPassword });
      toast.success(t('profile.passwordChanged'));
      setOldPassword('');
      setNewPassword('');
      setConfirm('');
      onClose();
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal open={open} onClose={onClose} title={t('profile.changePassword')} size="sm">
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Input
          label={t('profile.oldPassword')}
          type="password"
          autoComplete="current-password"
          value={oldPassword}
          onChange={(e) => setOldPassword(e.target.value)}
        />
        <Input
          label={t('profile.newPassword')}
          type="password"
          autoComplete="new-password"
          value={newPassword}
          onChange={(e) => setNewPassword(e.target.value)}
        />
        <Input
          label={t('profile.confirmPassword')}
          type="password"
          autoComplete="new-password"
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? t('common.loading') : t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
