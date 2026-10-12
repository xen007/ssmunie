import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useAuth } from '../../core/providers/AuthProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';

const EMPTY_FORM = { id: null, username: '', email: '', password: '', roles: [], statut: 1 };

/**
 * CRUD des comptes utilisateurs (permission accounts.manage).
 */
export default function AccountManage() {
  const t = useT();
  const { lang } = useLang();
  const { user: me } = useAuth();

  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const roleLabel = useCallback(
    (code) => {
      const r = roles.find((x) => x.code === code);
      if (!r) return code;
      return lang === 'fr' ? r.libelle_fr : r.libelle_en;
    },
    [roles, lang]
  );

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/accountM.php');
      setUsers(res.data.users ?? []);
      setRoles(res.data.roles ?? []);
    } catch {
      toast.error(t('errors.generic'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = () => {
    setForm(EMPTY_FORM);
    setModalOpen(true);
  };

  const openEdit = (u) => {
    setForm({
      id: u.id,
      username: u.username,
      email: u.email ?? '',
      password: '',
      roles: u.roles ?? [],
      statut: u.statut,
    });
    setModalOpen(true);
  };

  const toggleRole = (code) => {
    setForm((f) => ({
      ...f,
      roles: f.roles.includes(code) ? f.roles.filter((c) => c !== code) : [...f.roles, code],
    }));
  };

  const save = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (form.id == null) {
        await api.post('/accountM.php', {
          username: form.username,
          email: form.email,
          password: form.password,
          roles: form.roles,
          statut: form.statut,
        });
        toast.success(t('accounts.created'));
      } else {
        const payload = {
          id: form.id,
          email: form.email,
          roles: form.roles,
          statut: form.statut,
        };
        if (form.password) payload.password = form.password;
        await api.put('/accountM.php', payload);
        toast.success(t('accounts.updated'));
      }
      setModalOpen(false);
      load();
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setSaving(true);
    try {
      await api.delete('/accountM.php', { data: { id: deleteTarget.id } });
      toast.success(t('accounts.deleted'));
      setDeleteTarget(null);
      load();
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    } finally {
      setSaving(false);
    }
  };

  const isMe = useMemo(() => (id) => id === me?.id, [me]);

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{t('accounts.title')}</h1>
          <p className="mt-1 text-sm text-muted">{t('accounts.subtitle')}</p>
        </div>
        <Button icon={Plus} onClick={openCreate}>
          {t('accounts.add')}
        </Button>
      </div>

      <Card className="overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-sm text-muted">{t('common.loading')}</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-3 font-medium">{t('accounts.username')}</th>
                  <th className="px-4 py-3 font-medium">{t('accounts.email')}</th>
                  <th className="px-4 py-3 font-medium">{t('accounts.roles')}</th>
                  <th className="px-4 py-3 font-medium">{t('accounts.status')}</th>
                  <th className="px-4 py-3 text-right font-medium">{t('accounts.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 font-medium text-foreground">
                      {u.username}
                      {isMe(u.id) && (
                        <span className="ml-2 rounded bg-primary-soft px-1.5 py-0.5 text-[10px] font-semibold text-primary">
                          {t('accounts.you')}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-muted">{u.email || '—'}</td>
                    <td className="px-4 py-3 text-muted">
                      {(u.roles ?? []).map(roleLabel).join(', ') || '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={[
                          'rounded-full px-2 py-0.5 text-[11px] font-medium',
                          u.statut === 1 ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger',
                        ].join(' ')}
                      >
                        {u.statut === 1 ? t('accounts.active') : t('accounts.inactive')}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          onClick={() => openEdit(u)}
                          className="rounded p-1.5 text-muted transition-colors hover:bg-primary-soft hover:text-primary"
                          title={t('accounts.edit')}
                        >
                          <Pencil className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => setDeleteTarget(u)}
                          disabled={isMe(u.id) || u.id === 1}
                          className="rounded p-1.5 text-muted transition-colors hover:bg-danger/15 hover:text-danger disabled:pointer-events-none disabled:opacity-30"
                          title={t('accounts.delete')}
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Modale create/edit */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={form.id == null ? t('accounts.add') : t('accounts.edit')}
      >
        <form onSubmit={save} className="flex flex-col gap-4">
          <Input
            label={t('accounts.username')}
            value={form.username}
            disabled={form.id != null}
            onChange={(e) => setForm((f) => ({ ...f, username: e.target.value }))}
          />
          <Input
            label={t('accounts.email')}
            type="email"
            value={form.email}
            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
          />
          <Input
            label={form.id == null ? t('accounts.password') : t('accounts.passwordOptional')}
            type="password"
            value={form.password}
            onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
          />
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium text-foreground">{t('accounts.roles')}</span>
            <div className="grid grid-cols-2 gap-1.5 rounded-md border border-border p-3">
              {roles.map((r) => (
                <label key={r.code} className="flex items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    checked={form.roles.includes(r.code)}
                    onChange={() => toggleRole(r.code)}
                    className="h-4 w-4 accent-[var(--color-primary)]"
                  />
                  {roleLabel(r.code)}
                </label>
              ))}
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input
              type="checkbox"
              checked={form.statut === 1}
              onChange={(e) => setForm((f) => ({ ...f, statut: e.target.checked ? 1 : 0 }))}
              className="h-4 w-4 accent-[var(--color-primary)]"
            />
            {t('accounts.active')}
          </label>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? t('common.loading') : t('common.save')}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Modale delete */}
      <Modal
        open={deleteTarget != null}
        onClose={() => setDeleteTarget(null)}
        title={t('accounts.deleteTitle')}
        size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setDeleteTarget(null)}>
              {t('common.cancel')}
            </Button>
            <Button variant="danger" onClick={confirmDelete} disabled={saving}>
              {t('common.confirm')}
            </Button>
          </>
        }
      >
        <p className="text-sm text-muted">
          {t('accounts.deleteConfirm', { username: deleteTarget?.username ?? '' })}
        </p>
      </Modal>
    </div>
  );
}
