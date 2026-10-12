import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';

export default function AbsenceFichePage() {
  const t = useT(); const qc = useQueryClient(); const nav = useNavigate(); const { matricule } = useParams();
  const [justif, setJustif] = useState(null); const [jform, setJform] = useState({ abs_justifies: 0, motif: '' });
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-all'], queryFn: async () => (await api.get('/annee/eleves.php?per_page=500')).data.data });
  const eleve = eleves.find((e) => e.matricule === matricule);
  const { data: abs = [] } = useQuery({ queryKey: ['abs-eleve', eleve?.id_eleve], queryFn: async () => (await api.get('/annee/absences.php?eleve=' + eleve.id_eleve)).data.data, enabled: !!eleve });
  const { data: sanc } = useQuery({ queryKey: ['abs-sanc', eleve?.id_eleve], queryFn: async () => (await api.get('/annee/absences_sanction.php?eleve=' + eleve.id_eleve)).data.data, enabled: !!eleve });
  const justifier = useMutation({ mutationFn: async () => api.post('/annee/absences_justifier.php', { eleve_id: eleve.id_eleve, mois_id: Number(justif), abs_justifies: Number(jform.abs_justifies), motif: jform.motif }), onSuccess: () => { qc.invalidateQueries(); setJustif(null); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });
  const nj = (a) => (a.abs_total || 0) - (a.abs_justifies || 0);
  return (
    <div className="mx-auto max-w-4xl">
      <Button variant="ghost" size="sm" icon={ArrowLeft} onClick={() => nav('/absences')} className="mb-3">{t('absences.title')}</Button>
      <Card className="mb-4 p-5"><h1 className="text-xl font-bold text-foreground">{eleve?.nom} {eleve?.prenom}</h1><p className="text-sm text-muted">{eleve?.matricule}</p>
        <div className="mt-3 grid grid-cols-2 gap-3"><div><div className="text-xs uppercase text-muted">{t('absences.heures')}</div><div className="text-xl font-bold text-danger">{sanc?.heures_non_justifiees}h</div></div><div><div className="text-xs uppercase text-muted">{t('absences.sanction')}</div><div className="text-xl font-bold text-foreground">{sanc?.sanction?.sanction || '—'}</div></div></div>
      </Card>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('absences.mois')}</th><th className="px-4 py-3">{t('absences.total')}</th><th className="px-4 py-3">{t('absences.justifies')}</th><th className="px-4 py-3">{t('absences.heures')}</th><th className="px-4 py-3"></th></tr></thead>
        <tbody>{abs.map((a) => (<tr key={a.id_abs} className="border-b border-border last:border-0"><td className="px-4 py-2 text-muted">{t('mois.' + a.mois_id)}</td><td className="px-4 py-2 text-foreground">{a.abs_total}h</td><td className="px-4 py-2 text-foreground">{a.abs_justifies}h</td><td className="px-4 py-2 text-warning">{nj(a)}h</td><td className="px-4 py-2"><Button size="sm" variant="secondary" onClick={() => { setJustif(a.mois_id); setJform({ abs_justifies: a.abs_total, motif: '' }); }}>{t('absences.justifier')}</Button></td></tr>))}{abs.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
      <div className="mt-4 grid grid-cols-1 gap-3 sm:max-w-sm"><Input type="number" label={t('absences.justifies')} value={jform.abs_justifies} onChange={(e) => setJform({ ...jform, abs_justifies: e.target.value })} /><Input label={t('absences.motif')} value={jform.motif} onChange={(e) => setJform({ ...jform, motif: e.target.value })} /></div>
      {justif && <div className="mt-3"><Button onClick={() => justifier.mutate()}>{t('absences.justifier')}</Button></div>}
    </div>
  );
}