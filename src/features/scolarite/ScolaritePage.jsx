import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';
import Modal from '../../shared/ui/Modal';

const SCOLOR = { neant: 'text-muted', impaye: 'text-danger', partiel: 'text-warning', solde: 'text-success' };
const f = (n) => Number(n ?? 0).toLocaleString('fr-FR');

export default function ScolaritePage() {
  const t = useT();
  const qc = useQueryClient();
  const nav = useNavigate();
  const [classe, setClasse] = useState('');
  const [pay, setPay] = useState(null);
  const [form, setForm] = useState({ montant: '', date_paye: new Date().toISOString().slice(0, 10), observation: '' });
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: dash } = useQuery({ queryKey: ['sco-dashboard'], queryFn: async () => (await api.get('/annee/scolarite_dashboard.php')).data.data });
  const { data: rows = [] } = useQuery({ queryKey: ['sco-list', classe], queryFn: async () => (await api.get('/annee/scolarite_eleve.php?classe=' + classe)).data.data, enabled: !!classe });
  const encaisser = useMutation({ mutationFn: async () => api.post('/annee/scolarite.php', { eleve_id: pay.eleve_id, montant: Number(form.montant), date_paye: form.date_paye, observation: form.observation }), onSuccess: () => { qc.invalidateQueries(); setPay(null); toast.success(t('common.updated')); }, onError: (e) => toast.error(e?.response?.data?.message || t('errors.generic')) });

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('scolarite.title')}</h1><p className="mt-1 text-sm text-muted">{t('scolarite.subtitle')}</p></div>
      <div className="mb-4 grid grid-cols-3 gap-3">
        <Card className="p-4"><div className="text-xs uppercase text-muted">{t('scolarite.attendu')}</div><div className="text-xl font-bold text-foreground">{f(dash?.attendu)} F</div></Card>
        <Card className="p-4"><div className="text-xs uppercase text-muted">{t('scolarite.percu')}</div><div className="text-xl font-bold text-success">{f(dash?.percu)} F</div></Card>
        <Card className="p-4"><div className="text-xs uppercase text-muted">{t('scolarite.reste')}</div><div className="text-xl font-bold text-warning">{f(dash?.reste)} F</div></Card>
      </div>
      <div className="mb-4 max-w-xs"><Select label={t('scolarite.class') || 'Classe'} value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('scolarite.frais')}</th><th className="px-4 py-3">{t('scolarite.paye')}</th><th className="px-4 py-3">{t('scolarite.reste')}</th><th className="px-4 py-3">{t('scolarite.tranche')}</th><th className="px-4 py-3">{t('scolarite.statut')}</th><th className="px-4 py-3"></th></tr></thead>
        <tbody>{rows.map((s) => (<tr key={s.eleve_id} className="border-b border-border last:border-0">
          <td className="px-4 py-2 text-foreground">{s.nom} {s.prenom}</td>
          <td className="px-4 py-2 text-foreground">{f(s.total_net)}</td>
          <td className="px-4 py-2 text-foreground">{f(s.cumul_global)}</td>
          <td className="px-4 py-2 text-warning">{f(s.reste_global)}</td>
          <td className="px-4 py-2 text-muted">{s.tranche_courante}</td>
          <td className={`px-4 py-2 font-medium ${SCOLOR[s.statut_global] || ''}`}>{t('statut.' + s.statut_global)}</td>
          <td className="px-4 py-2"><div className="flex gap-1"><Button size="sm" variant="ghost" onClick={() => nav('/scolarite/' + s.matricule)}>{t('common.view')}</Button><Button size="sm" variant="secondary" onClick={() => { setPay(s); setForm({ montant: s.reste_global, date_paye: new Date().toISOString().slice(0, 10), observation: '' }); }}>{t('scolarite.encaisser')}</Button></div></td>
        </tr>))}{rows.length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
      <Modal open={!!pay} onClose={() => setPay(null)} title={t('scolarite.encaisser') + ' — ' + pay?.nom + ' ' + pay?.prenom} footer={<><Button variant="secondary" onClick={() => setPay(null)}>{t('common.cancel')}</Button><Button onClick={() => encaisser.mutate()}>{t('scolarite.encaisser')}</Button></>}>
        <div className="flex flex-col gap-3">
          <Input type="number" label={t('scolarite.montant')} value={form.montant} onChange={(e) => setForm({ ...form, montant: e.target.value })} />
          <Input type="date" label={t('scolarite.date')} value={form.date_paye} onChange={(e) => setForm({ ...form, date_paye: e.target.value })} />
          <Input label={t('scolarite.observation')} value={form.observation} onChange={(e) => setForm({ ...form, observation: e.target.value })} />
        </div>
      </Modal>
    </div>
  );
}