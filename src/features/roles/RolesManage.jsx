import { useCallback, useEffect, useMemo, useState } from 'react';
import { Lock, Settings2 } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Modal from '../../shared/ui/Modal';

/**
 * Gestion des rôles (permission roles.manage).
 * Le rôle admin (bypass) n'est pas éditable.
 */
export default function RolesManage() {
  const t = useT();
  const { lang } = useLang();

  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(null); // rôle en cours d'édition
  const [selected, setSelected] = useState([]);
  const [saving, setSaving] = useState(false);

  const label = useCallback(
    (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en),
    [lang]
  );

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [resRoles, resPerms] = await Promise.all([
        api.get('/rolesM.php'),
        api.get('/rolesM.php', { params: { permissions: 1 } }),
      ]);
      setRoles(resRoles.data.roles ?? []);
      setPermissions(resPerms.data.permissions ?? []);
    } catch {
      toast.error(t('errors.generic'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    load();
  }, [load]);

  const byCategory = useMemo(() => {
    const map = {};
    for (const p of permissions) {
      (map[p.categorie] ??= []).push(p);
    }
    return map;
  }, [permissions]);

  const openEdit = (role) => {
    setEditing(role);
    setSelected(role.permissions === '*' || role.permissions.includes('*') ? [] : role.permissions);
  };

  const toggle = (code) => {
    setSelected((s) => (s.includes(code) ? s.filter((c) => c !== code) : [...s, code]));
  };

  const save = async () => {
    setSaving(true);
    try {
      await api.put('/rolesM.php', { id: editing.id, permissions: selected });
      toast.success(t('roles.updated'));
      setEditing(null);
      load();
    } catch (err) {
      const code = err?.response?.data?.code || 'generic';
      toast.error(t(`errors.${code}`));
    } finally {
      setSaving(false);
    }
  };

  const permCount = (role) => (role.permissions.includes('*') ? t('roles.all') : role.permissions.length);

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold tracking-tight">{t('roles.title')}</h1>
        <p className="mt-1 text-sm text-muted">{t('roles.subtitle')}</p>
      </div>

      <Card className="overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-sm text-muted">{t('common.loading')}</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-3 font-medium">{t('roles.role')}</th>
                  <th className="px-4 py-3 font-medium">{t('roles.permissions')}</th>
                  <th className="px-4 py-3 text-right font-medium">{t('roles.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {roles.map((r) => (
                  <tr key={r.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">{r.code}</span>
                        {r.code === 'admin' && <Lock className="h-3.5 w-3.5 text-muted" aria-hidden="true" />}
                      </div>
                      <div className="text-xs text-muted">{label(r)}</div>
                    </td>
                    <td className="px-4 py-3 text-muted">{permCount(r)}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end">
                        <button
                          type="button"
                          onClick={() => openEdit(r)}
                          disabled={r.code === 'admin'}
                          className="rounded p-1.5 text-muted transition-colors hover:bg-primary-soft hover:text-primary disabled:pointer-events-none disabled:opacity-30"
                          title={t('roles.edit')}
                        >
                          <Settings2 className="h-4 w-4" />
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

      <Modal
        open={editing != null}
        onClose={() => setEditing(null)}
        title={`${t('roles.editRole')} — ${editing?.code ?? ''}`}
        size="lg"
        footer={
          <>
            <Button variant="ghost" onClick={() => setEditing(null)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={save} disabled={saving}>
              {saving ? t('common.loading') : t('common.save')}
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-5">
          {Object.entries(byCategory).map(([cat, perms]) => (
            <div key={cat}>
              <h3 className="mb-2 text-sm font-semibold capitalize text-foreground">{cat}</h3>
              <div className="grid gap-1.5 sm:grid-cols-2">
                {perms.map((p) => (
                  <label key={p.code} className="flex items-center gap-2 text-sm text-foreground">
                    <input
                      type="checkbox"
                      checked={selected.includes(p.code)}
                      onChange={() => toggle(p.code)}
                      className="h-4 w-4 accent-[var(--color-primary)]"
                    />
                    <span>{label(p)}</span>
                  </label>
                ))}
              </div>
            </div>
          ))}
        </div>
      </Modal>
    </div>
  );
}
