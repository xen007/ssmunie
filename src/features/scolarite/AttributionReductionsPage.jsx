import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

export default function AttributionReductionsPage() {
  const t = useT(); const qc = useQueryClient();
  const [eleveId, setEleveId] = useState(''); const [typeId, setTypeId] = useState(''); const [motif, setMotif] = useState('');
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-all'], queryFn: async () => (await api.get('/annee/eleves.php?per_page=500')).data.data });
  const { data: types = [] } = useQuery({ queryKey: ['reductions'], queryFn: async () => (await api.get('/annee/reductions.php')).data.data });
  const { data: attrs = [] } = useQuery({ queryKey: ['eleve-reductions'], queryFn: async () => (await api.get('/annee/eleve_reduction.php')).data.data });
  const save = useMutation({ mutationFn: async () => api.post('/annee/eleve_reduction.php', { eleve_id: Number(eleveId), type_id: Number(typeId), motif }), onSuccess: () => { qc.invalidateQueries({ queryKey: ['eleve-reductions'] }); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });
  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('reductions.attribution')}</h1></div>
      <Card className="mb-4 p-4"><div className="grid gap-3 sm:grid-cols-3">
        <Select label={t('reductions.eleve')} value={eleveId} onChange={(e) => setEleveId(e.target.value)}><option value="">—</option>{eleves.map((e) => <option key={e.id_eleve} value={e.id_eleve}>{e.matricule} — {e.nom} {e.prenom}</option>)}</Select>
        <Select label={t('reductions.type')} value={typeId} onChange={(e) => setTypeId(e.target.value)}><option value="">—</option>{types.map((r) => <option key={r.id_type} value={r.id_type}>{r.code_type} ({t('application.' + r.application)})</option>)}</Select>
        <Input label={t('reductions.motif')} value={motif} onChange={(e) => setMotif(e.target.value)} />
      </div><div className="mt-3 flex justify-end"><Button onClick={() => save.mutate()}>{t('common.save')}</Button></div></Card>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('reductions.type')}</th><th className="px-4 py-3">{t('reductions.montant')}</th><th className="px-4 py-3">{t('reductions.motif')}</th></tr></thead>
        <tbody>{attrs.map((a) => (<tr key={a.id} className="border-b border-border last:border-0"><td className="px-4 py-3 text-foreground">{a.eleve_id}</td><td className="px-4 py-3 text-foreground">{a.code_type}</td><td className="px-4 py-3 text-muted">{a.montant_applique}</td><td className="px-4 py-3 text-muted">{a.motif}</td></tr>))}</tbody>
      </table></div></Card>
    </div>
  );
}