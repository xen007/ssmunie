import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
const f = (n) => Number(n ?? 0).toLocaleString('fr-FR');
const EMPTY = { id_categorie: null, code_cat: '', libellee_cat: '', frais_ins: 0, frais_sco: 0, tranche1: 0, tranche2: 0, tranche3: 0, tranche4: 0 };

export default function CategoriesPage() {
  const t = useT(); const qc = useQueryClient(); const [form, setForm] = useState(null);
  const { data: rows = [] } = useQuery({ queryKey: ['categories'], queryFn: async () => (await api.get('/annee/categories.php')).data.data });
  const save = useMutation({ mutationFn: async (p) => (p.id_categorie ? api.put('/annee/categories.php', p) : api.post('/annee/categories.php', p)), onSuccess: () => { qc.invalidateQueries({ queryKey: ['categories'] }); setForm(null); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4 flex items-center justify-between"><div><h1 className="text-2xl font-bold tracking-tight">{t('categories.title')}</h1><p className="mt-1 text-sm text-muted">{t('categories.subtitle')}</p></div><Button icon={Plus} onClick={() => setForm({ ...EMPTY })}>{t('competences.add')}</Button></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('categories.code')}</th><th className="px-4 py-3">{t('categories.libelle')}</th><th className="px-4 py-3">{t('categories.fraisIns')}</th><th className="px-4 py-3">{t('categories.fraisSco')}</th><th className="px-4 py-3">{t('categories.tranches')}</th><th className="px-4 py-3"></th></tr></thead>
        <tbody>{rows.map((c) => (<tr key={c.id_categorie} className="border-b border-border last:border-0"><td className="px-4 py-3 font-medium text-foreground">{c.code_cat}</td><td className="px-4 py-3 text-foreground">{c.libellee_cat}</td><td className="px-4 py-3 text-muted">{f(c.frais_ins)}</td><td className="px-4 py-3 text-muted">{f(c.frais_sco)}</td><td className="px-4 py-3 text-muted">{f(c.tranche1)}/{f(c.tranche2)}/{f(c.tranche3)}/{f(c.tranche4)}</td><td className="px-4 py-3"><Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm(c)} /></td></tr>))}</tbody>
      </table></div></Card>
      <Modal open={!!form} onClose={() => setForm(null)} title={form?.id_categorie ? t('common.edit') : t('competences.add')} footer={<><Button variant="secondary" onClick={() => setForm(null)}>{t('common.cancel')}</Button><Button onClick={() => save.mutate(form)}>{t('common.save')}</Button></>}>
        {form && <div className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-3"><Input label={t('categories.code')} value={form.code_cat} onChange={(e) => setForm({ ...form, code_cat: e.target.value })} /><Input label={t('categories.libelle')} value={form.libellee_cat} onChange={(e) => setForm({ ...form, libellee_cat: e.target.value })} /></div>
          <div className="grid grid-cols-2 gap-3"><Input type="number" label={t('categories.fraisIns')} value={form.frais_ins} onChange={(e) => setForm({ ...form, frais_ins: e.target.value })} /><Input type="number" label={t('categories.fraisSco')} value={form.frais_sco} onChange={(e) => setForm({ ...form, frais_sco: e.target.value })} /></div>
          <div className="grid grid-cols-4 gap-2">{['tranche1','tranche2','tranche3','tranche4'].map((k) => <Input key={k} type="number" label={k} value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} />)}</div>
        </div>}
      </Modal>
    </div>
  );
}