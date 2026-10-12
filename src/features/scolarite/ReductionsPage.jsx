import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';
import Modal from '../../shared/ui/Modal';
const EMPTY = { id_type: null, code_type: '', libelle: '', application: 'scolarite', taux: 0, montant: 0, statut: 1 };

export default function ReductionsPage() {
  const t = useT(); const qc = useQueryClient(); const [form, setForm] = useState(null);
  const { data: rows = [] } = useQuery({ queryKey: ['reductions'], queryFn: async () => (await api.get('/annee/reductions.php')).data.data });
  const save = useMutation({ mutationFn: async (p) => (p.id_type ? api.put('/annee/reductions.php', p) : api.post('/annee/reductions.php', p)), onSuccess: () => { qc.invalidateQueries({ queryKey: ['reductions'] }); setForm(null); toast.success(t('common.updated')); } });
  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4 flex items-center justify-between"><div><h1 className="text-2xl font-bold tracking-tight">{t('reductions.title')}</h1><p className="mt-1 text-sm text-muted">{t('reductions.subtitle')}</p></div><Button icon={Plus} onClick={() => setForm({ ...EMPTY })}>{t('competences.add')}</Button></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('categories.code')}</th><th className="px-4 py-3">{t('categories.libelle')}</th><th className="px-4 py-3">{t('reductions.application')}</th><th className="px-4 py-3">{t('reductions.taux')}</th><th className="px-4 py-3">{t('reductions.montant')}</th><th className="px-4 py-3"></th></tr></thead>
        <tbody>{rows.map((r) => (<tr key={r.id_type} className="border-b border-border last:border-0"><td className="px-4 py-3 font-medium text-foreground">{r.code_type}</td><td className="px-4 py-3 text-foreground">{r.libelle}</td><td className="px-4 py-3 text-muted">{t('application.' + r.application)}</td><td className="px-4 py-3 text-muted">{r.taux ?? '—'}</td><td className="px-4 py-3 text-muted">{r.montant ?? '—'}</td><td className="px-4 py-3"><Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm(r)} /></td></tr>))}</tbody>
      </table></div></Card>
      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id_type ? t('common.edit') : t('competences.add')} footer={<><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button onClick={() => save.mutate(form)}>{t('common.save')}</Button></>}>
        {form && <div className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-3"><Input label={t('categories.code')} value={form.code_type} onChange={(e) => setForm({ ...form, code_type: e.target.value })} /><Input label={t('categories.libelle')} value={form.libelle} onChange={(e) => setForm({ ...form, libelle: e.target.value })} /></div>
          <Select label={t('reductions.application')} value={form.application} onChange={(e) => setForm({ ...form, application: e.target.value })}>{['inscription','scolarite','total'].map((a) => <option key={a} value={a}>{t('application.' + a)}</option>)}</Select>
          <div className="grid grid-cols-2 gap-3"><Input type="number" label={t('reductions.taux')} value={form.taux} onChange={(e) => setForm({ ...form, taux: e.target.value })} /><Input type="number" label={t('reductions.montant')} value={form.montant} onChange={(e) => setForm({ ...form, montant: e.target.value })} /></div>
        </div>}
      </Modal>
    </div>
  );
}