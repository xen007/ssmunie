import { useEffect, useRef, useState } from 'react';
import { CalendarDays, ChevronDown, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { useAnnee } from '../../core/providers/AnneeProvider';
import { useAuth } from '../../core/providers/AuthProvider';
import { useT } from '../../i18n';
import Modal from '../ui/Modal';
import Input from '../ui/Input';
import Button from '../ui/Button';

/**
 * Sélecteur d'année scolaire (D22) + création d'année (admin).
 */
export default function YearSelector() {
  const t = useT();
  const { anneeCourante, anneesDisponibles, setAnneeCourante, createAnnee } = useAnnee();
  const { hasRole } = useAuth();

  const [open, setOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState({ libellee: '', date_debut: '', date_fin: '' });
  const [saving, setSaving] = useState(false);
  const ref = useRef(null);

  const isAdmin = hasRole('admin');

  useEffect(() => {
    const onClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const switchYear = async (id) => {
    try {
      await setAnneeCourante(id);
    } catch (err) {
      toast.error(t(`errors.${err?.response?.data?.code || 'generic'}`));
    }
    setOpen(false);
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await createAnnee(form);
      toast.success(t('annee.created'));
      setCreateOpen(false);
      setForm({ libellee: '', date_debut: '', date_fin: '' });
    } catch (err) {
      toast.error(t(`errors.${err?.response?.data?.code || 'generic'}`));
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <div className="relative" ref={ref}>
        <button
          type="button"
          onClick={() => setOpen((o) => !o)}
          className="flex w-full items-center justify-between gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground transition-colors hover:border-primary"
          title={t('annee.title')}
        >
          <span className="flex items-center gap-2">
            <CalendarDays className="h-4 w-4 text-muted" />
            <span>{anneeCourante?.libellee ?? '—'}</span>
          </span>
          <ChevronDown className="h-4 w-4 text-muted" />
        </button>

        {open && (
          <div className="absolute left-0 top-full z-40 mt-1 w-60 rounded-md border border-border bg-surface p-2 shadow-lg">
            {anneesDisponibles.map((a) => (
              <button
                key={a.id}
                type="button"
                onClick={() => switchYear(a.id)}
                className="flex w-full items-center justify-between rounded px-2.5 py-1.5 text-sm text-foreground transition-colors hover:bg-primary-soft hover:text-primary"
              >
                <span>{a.libellee}</span>
                {a.id === anneeCourante?.id && <span className="text-primary">✓</span>}
              </button>
            ))}
            {isAdmin && (
              <>
                <div className="my-1 h-px bg-border" />
                <button
                  type="button"
                  onClick={() => {
                    setCreateOpen(true);
                    setOpen(false);
                  }}
                  className="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-primary transition-colors hover:bg-primary-soft"
                >
                  <Plus className="h-4 w-4" />
                  {t('annee.new')}
                </button>
              </>
            )}
          </div>
        )}
      </div>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title={t('annee.new')} size="sm">
        <form onSubmit={submit} className="flex flex-col gap-4">
          <Input
            label={t('annee.libellee')}
            value={form.libellee}
            placeholder="2027-2028"
            onChange={(e) => setForm((f) => ({ ...f, libellee: e.target.value }))}
          />
          <Input
            label={t('annee.dateDebut')}
            type="date"
            value={form.date_debut}
            onChange={(e) => setForm((f) => ({ ...f, date_debut: e.target.value }))}
          />
          <Input
            label={t('annee.dateFin')}
            type="date"
            value={form.date_fin}
            onChange={(e) => setForm((f) => ({ ...f, date_fin: e.target.value }))}
          />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" onClick={() => setCreateOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? t('common.loading') : t('common.save')}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
