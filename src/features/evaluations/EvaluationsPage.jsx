import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
import Select from '../../shared/ui/Select';

const TYPES = ['interrogation', 'devoir', 'composition', 'examen', 'oral', 'tp', 'projet'];
const EMPTY = { id_eval: null, nom_evaluation: '', bareme: 20, type: 'devoir', ordre: 0, actif: 1 };

export default function EvaluationsPage() {
  const t = useT();
  const qc = useQueryClient();
  const [form, setForm] = useState(null);
  const [confirmDel, setConfirmDel] = useState(null);
  const { data: list = [] } = useQuery({ queryKey: ['evaluations'], queryFn: async () => (await api.get('/annee/evaluations.php?actif=all')).data.data });
  const invalidate = () => qc.invalidateQueries({ queryKey: ['evaluations'] });
  const save = useMutation({
    mutationFn: async (p) => (p.id_eval ? api.put('/annee/evaluations.php', p) : api.post('/annee/evaluations.php', p)),
    onSuccess: () => { invalidate(); setForm(null); toast.success(t('common.updated')); },
    onError: () => toast.error(t('errors.generic')),
  });
  const del = useMutation({
    mutationFn: async (id) => api.delete('/annee/evaluations.php?id_eval=' + id),
    onSuccess: () => { invalidate(); setConfirmDel(null); toast.success(t('common.deleted')); },
  });

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('evaluations.title')}</h1><p className="mt-1 text-sm text-muted">{t('evaluations.subtitle')}</p></div>
        <Button icon={Plus} onClick={() => setForm({ ...EMPTY })}>{t('evaluations.new')}</Button>
      </div>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-3 font-medium">{t('evaluations.nom')}</th>
              <th className="px-4 py-3 font-medium">{t('evaluations.bareme')}</th>
              <th className="px-4 py-3 font-medium">{t('evaluations.type')}</th>
              <th className="px-4 py-3 font-medium">{t('evaluations.ordre')}</th>
              <th className="px-4 py-3 font-medium">{t('evaluations.actif')}</th>
              <th className="px-4 py-3 font-medium">{t('common.actions')}</th>
            </tr></thead>
            <tbody>
              {list.map((e) => (
                <tr key={e.id_eval} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{e.nom_evaluation}</td>
                  <td className="px-4 py-3 text-foreground">{e.bareme}</td>
                  <td className="px-4 py-3 text-muted">{t('evaluations.type.' + e.type)}</td>
                  <td className="px-4 py-3 text-muted">{e.ordre}</td>
                  <td className="px-4 py-3">{e.actif ? <span className="text-emerald-500">●</span> : <span className="text-muted">○</span>}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm(e)} aria-label={t('common.edit')} />
                      <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => setConfirmDel(e)} aria-label={t('common.delete')} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id_eval ? t('common.edit') : t('evaluations.new')} footer={(
        <><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button disabled={save.isPending} onClick={() => save.mutate(form)}>{t('common.save')}</Button></>
      )}>
        {form && (
          <div className="flex flex-col gap-3">
            <Input label={t('evaluations.nom')} required value={form.nom_evaluation} onChange={(e) => setForm({ ...form, nom_evaluation: e.target.value })} />
            <div className="grid grid-cols-2 gap-3">
              <Input type="number" step="0.01" label={t('evaluations.bareme')} value={form.bareme} onChange={(e) => setForm({ ...form, bareme: e.target.value })} />
              <Input type="number" label={t('evaluations.ordre')} value={form.ordre} onChange={(e) => setForm({ ...form, ordre: e.target.value })} />
            </div>
            <Select label={t('evaluations.type')} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              {TYPES.map((ty) => <option key={ty} value={ty}>{t('evaluations.type.' + ty)}</option>)}
            </Select>
            <Select label={t('evaluations.actif')} value={form.actif} onChange={(e) => setForm({ ...form, actif: Number(e.target.value) })}>
              <option value={1}>{t('common.active')}</option><option value={0}>{t('common.inactive')}</option>
            </Select>
          </div>
        )}
      </Modal>

      <Modal open={!!confirmDel} onClose={() => setConfirmDel(null)} title={t('common.confirmDelete')} footer={(
        <><Button variant="secondary" onClick={() => setConfirmDel(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => del.mutate(confirmDel.id_eval)}>{t('common.delete')}</Button></>
      )}>
        <p className="text-sm">{t('common.confirmDelete')} — {confirmDel?.nom_evaluation} ?</p>
      </Modal>
    </div>
  );
}