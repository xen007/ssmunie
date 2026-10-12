import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';

export default function ParametresAbsencePage() {
  const t = useT(); const qc = useQueryClient(); const [form, setForm] = useState(null);
  const { data: rows = [] } = useQuery({ queryKey: ['parametres-absence'], queryFn: async () => (await api.get('/annee/parametres_absence.php')).data.data });
  const save = useMutation({ mutationFn: async (p) => api.put('/annee/parametres_absence.php', p), onSuccess: () => { qc.invalidateQueries({ queryKey: ['parametres-absence'] }); setForm(null); toast.success(t('common.updated')); } });
  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('absences.sanction')}</h1><p className="mt-1 text-sm text-muted">7 paliers</p></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">#</th><th className="px-4 py-3">Min (h)</th><th className="px-4 py-3">Max (h)</th><th className="px-4 py-3">{t('absences.sanction')}</th><th className="px-4 py-3">{t('common.edit')}</th></tr></thead>
        <tbody>{rows.map((r) => (<tr key={r.id} className="border-b border-border last:border-0"><td className="px-4 py-3 text-foreground">{r.palier}</td><td className="px-4 py-3 text-muted">{r.heures_min}</td><td className="px-4 py-3 text-muted">{r.heures_max ?? '∞'}</td><td className="px-4 py-3 text-foreground">{r.sanction}</td><td className="px-4 py-3"><Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm(r)} /></td></tr>))}</tbody>
      </table></div></Card>
      <Modal open={!!form} onClose={() => setForm(null)} title={t('common.edit')} footer={<><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button onClick={() => save.mutate(form)}>{t('common.save')}</Button></>}>
        {form && <div className="grid grid-cols-2 gap-3"><Input type="number" label="Min (h)" value={form.heures_min} onChange={(e) => setForm({ ...form, heures_min: e.target.value })} /><Input type="number" label="Max (h)" value={form.heures_max ?? ''} onChange={(e) => setForm({ ...form, heures_max: e.target.value })} /><Input label={t('absences.sanction')} value={form.sanction} onChange={(e) => setForm({ ...form, sanction: e.target.value })} /><Input type="number" label="Durée (j)" value={form.duree_jours} onChange={(e) => setForm({ ...form, duree_jours: e.target.value })} /></div>}
      </Modal>
    </div>
  );
}