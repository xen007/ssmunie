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
import Modal from '../../shared/ui/Modal';

const f = (n) => Number(n ?? 0).toLocaleString('fr-FR');

export default function ScolariteFichePage() {
  const t = useT(); const qc = useQueryClient(); const nav = useNavigate(); const { matricule } = useParams();
  const [pay, setPay] = useState(false); const [annul, setAnnul] = useState(null);
  const [form, setForm] = useState({ montant: '', date_paye: new Date().toISOString().slice(0, 10), observation: '' });
  const [motif, setMotif] = useState('');
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-all'], queryFn: async () => (await api.get('/annee/eleves.php?per_page=500')).data.data });
  const eleve = eleves.find((e) => e.matricule === matricule);
  const { data: fic } = useQuery({ queryKey: ['sco-fiche', eleve?.id_eleve], queryFn: async () => (await api.get('/annee/scolarite_eleve.php?eleve=' + eleve.id_eleve)).data.data, enabled: !!eleve });
  const sit = fic?.situation;
  const encaisser = useMutation({ mutationFn: async () => api.post('/annee/scolarite.php', { eleve_id: eleve.id_eleve, montant: Number(form.montant), date_paye: form.date_paye, observation: form.observation }), onSuccess: () => { qc.invalidateQueries(); setPay(false); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });
  const annuler = useMutation({ mutationFn: async () => api.delete('/annee/scolarite.php?code=' + annul.code_versement, { data: { motif_annulation: motif } }), onSuccess: () => { qc.invalidateQueries(); setAnnul(null); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });

  return (
    <div className="mx-auto max-w-4xl">
      <Button variant="ghost" size="sm" icon={ArrowLeft} onClick={() => nav('/scolarite')} className="mb-3">{t('scolarite.title')}</Button>
      {sit && (<Card className="overflow-hidden">
        <div className="border-b border-border p-5"><h1 className="text-xl font-bold text-foreground">{sit.nom} {sit.prenom}</h1><p className="text-sm text-muted">{sit.matricule}</p></div>
        <div className="grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
          <div><div className="text-xs uppercase text-muted">{t('scolarite.net')}</div><div className="text-lg font-bold text-foreground">{f(sit.total_net)} F</div></div>
          <div><div className="text-xs uppercase text-muted">{t('scolarite.paye')}</div><div className="text-lg font-bold text-success">{f(sit.cumul_global)} F</div></div>
          <div><div className="text-xs uppercase text-muted">{t('scolarite.reste')}</div><div className="text-lg font-bold text-warning">{f(sit.reste_global)} F</div></div>
          <div><div className="text-xs uppercase text-muted">{t('scolarite.tranche')}</div><div className="text-lg font-bold text-foreground">{sit.tranche_courante}</div></div>
        </div>
        <div className="border-t border-border p-5"><div className="mb-2 text-sm font-medium text-foreground">{t('scolarite.progression')}</div>
          {sit.tranches_eff.map((tr, i) => tr > 0 && (<div key={i} className="mb-1 flex items-center gap-2 text-sm"><span className="w-16 text-muted">T{i + 1}</span><div className="h-2 flex-1 overflow-hidden rounded bg-muted/30"><div className="h-2 bg-primary" style={{ width: Math.min(100, (sit.cumul_sco / (tr + (i === sit.tranches_eff.length - 1 ? 0 : sit.tranches_eff.slice(0, i + 1).reduce((a, b) => a + b, 0)))) * 100) + '%' }} /></div><span className="text-muted">{f(tr)}</span></div>))}
        </div>
        <div className="grid gap-4 border-t border-border p-5 sm:grid-cols-2">
          <div><div className="mb-2 text-sm font-medium text-foreground">{t('scolarite.historique')}</div>{fic.historique.map((h) => (<div key={h.id_sco} className="mb-1 flex justify-between border-b border-border py-1 text-sm"><span className="text-muted">{h.code_versement} · {h.type_action} · {h.date_paye}</span><span className={h.montant < 0 ? 'text-danger' : 'text-success'}>{h.montant < 0 ? '-' : '+'}{f(Math.abs(h.montant))}</span></div>))}</div>
          <div><div className="mb-2 text-sm font-medium text-foreground">{t('scolarite.reductions')}</div>{fic.reductions.map((r) => (<div key={r.id} className="mb-1 border-b border-border py-1 text-sm"><span className="text-muted">{r.code_type} — {f(r.montant_applique)} F</span></div>))}</div>
        </div>
        <div className="flex justify-end gap-2 border-t border-border p-5"><Button onClick={() => { setPay(true); setForm({ montant: sit.reste_global, date_paye: new Date().toISOString().slice(0, 10), observation: '' }); }}>{t('scolarite.encaisser')}</Button></div>
      </Card>)}
      <Modal open={pay} onClose={() => setPay(false)} title={t('scolarite.encaisser')} footer={<><Button variant="secondary" onClick={() => setPay(false)}>{t('common.cancel')}</Button><Button onClick={() => encaisser.mutate()}>{t('common.save')}</Button></>}>
        <div className="flex flex-col gap-3"><Input type="number" label={t('scolarite.montant')} value={form.montant} onChange={(e) => setForm({ ...form, montant: e.target.value })} /><Input type="date" label={t('scolarite.date')} value={form.date_paye} onChange={(e) => setForm({ ...form, date_paye: e.target.value })} /><Input label={t('scolarite.observation')} value={form.observation} onChange={(e) => setForm({ ...form, observation: e.target.value })} /></div>
      </Modal>
      <Modal open={!!annul} onClose={() => setAnnul(null)} title={t('scolarite.annuler')} footer={<><Button variant="secondary" onClick={() => setAnnul(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => annuler.mutate()}>{t('scolarite.annuler')}</Button></>}>
        <Input label={t('scolarite.motifAnnulation')} value={motif} onChange={(e) => setMotif(e.target.value)} />
      </Modal>
    </div>
  );
}