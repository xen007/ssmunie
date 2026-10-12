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

const EMPTY = { id: null, code: '', libelle_fr: '', libelle_en: '', matiere_id: 0, niveau_id: 0, ordre: 0 };

export default function CompetencesApcPage() {
  const t = useT();
  const qc = useQueryClient();
  const [form, setForm] = useState(null);
  const { data: list = [] } = useQuery({ queryKey: ['competences-apc'], queryFn: async () => (await api.get('/annee/competences_apc.php')).data.data });
  const invalidate = () => qc.invalidateQueries({ queryKey: ['competences-apc'] });
  const save = useMutation({ mutationFn: async (p) => (p.id ? api.put('/annee/competences_apc.php', p) : api.post('/annee/competences_apc.php', p)), onSuccess: () => { invalidate(); setForm(null); toast.success(t('common.updated')); } });
  const del = useMutation({ mutationFn: async (id) => api.delete('/annee/competences_apc.php?id=' + id), onSuccess: () => { invalidate(); toast.success(t('common.deleted')); } });
  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4 flex items-center justify-between"><div><h1 className="text-2xl font-bold tracking-tight">{t('competences.title')}</h1><p className="mt-1 text-sm text-muted">{t('competences.subtitle')}</p></div><Button icon={Plus} onClick={() => setForm({ ...EMPTY })}>{t('competences.add')}</Button></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('competences.code')}</th><th className="px-4 py-3">{t('competences.libelleFr')}</th><th className="px-4 py-3">{t('competences.libelleEn')}</th><th className="px-4 py-3">{t('competences.ordre')}</th><th className="px-4 py-3"></th></tr></thead>
        <tbody>{list.map((c) => (<tr key={c.id} className="border-b border-border last:border-0"><td className="px-4 py-3 font-medium text-foreground">{c.code}</td><td className="px-4 py-3 text-foreground">{c.libelle_fr}</td><td className="px-4 py-3 text-muted">{c.libelle_en}</td><td className="px-4 py-3 text-muted">{c.ordre}</td><td className="px-4 py-3"><div className="flex gap-1"><Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm(c)} /><Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => del.mutate(c.id)} /></div></td></tr>))}</tbody>
      </table></div></Card>
      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id ? t('common.edit') : t('competences.add')} footer={<><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button onClick={() => save.mutate(form)}>{t('common.save')}</Button></>}>
        {form && <div className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-3"><Input label={t('competences.code')} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} /><Input type="number" label={t('competences.ordre')} value={form.ordre} onChange={(e) => setForm({ ...form, ordre: e.target.value })} /></div>
          <Input label={t('competences.libelleFr')} value={form.libelle_fr} onChange={(e) => setForm({ ...form, libelle_fr: e.target.value })} />
          <Input label={t('competences.libelleEn')} value={form.libelle_en} onChange={(e) => setForm({ ...form, libelle_en: e.target.value })} />
        </div>}
      </Modal>
    </div>
  );
}