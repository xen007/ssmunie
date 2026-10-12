import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import config from '../../core/config';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Modal from '../../shared/ui/Modal';
import Select from '../../shared/ui/Select';
import EnseignantForm from './EnseignantForm';

export default function EnseignantsPage() {
  const t = useT();
  const qc = useQueryClient();
  const navigate = useNavigate();
  const [search, setSearch] = useState('');
  const [fStatut, setFStatut] = useState('');
  const [fEmploi, setFEmploi] = useState('');
  const [page, setPage] = useState(1);
  const perPage = 30;
  const [form, setForm] = useState(null);
  const [confirmDel, setConfirmDel] = useState(null);
  const [exportOpen, setExportOpen] = useState(false);

  const qs = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (search) qs.set('search', search);
  if (fStatut) qs.set('statut', fStatut);
  if (fEmploi) qs.set('statut_emploi', fEmploi);
  const q = qs.toString();
  const { data } = useQuery({ queryKey: ['enseignants', q], queryFn: async () => (await api.get('/annee/enseignants.php?' + q)).data });
  const list = data?.data ?? []; const total = data?.total ?? 0; const pages = data?.pages ?? 0;

  const invalidate = () => qc.invalidateQueries({ queryKey: ['enseignants'] });
  const del = useMutation({ mutationFn: async (id) => api.delete('/annee/enseignant.php?id=' + id), onSuccess: () => { invalidate(); setConfirmDel(null); toast.success(t('common.deleted')); } });

  const doExport = (format) => {
    let url = '/annee/enseignant_export.php?format=' + format;
    if (search) url += '&search=' + encodeURIComponent(search);
    if (fStatut) url += '&statut=' + fStatut;
    if (fEmploi) url += '&statut_emploi=' + fEmploi;
    window.open(config.apiBaseUrl + url, '_blank');
    setExportOpen(false);
  };

  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('teachers.title')}</h1><p className="mt-1 text-sm text-muted">{t('common.showing', { count: total })}</p></div>
        <div className="flex gap-2"><Button variant="secondary" icon={Download} onClick={() => setExportOpen(true)}>{t('common.export')}</Button><Button icon={Plus} onClick={() => setForm({ open: true, initial: null })}>{t('teachers.new')}</Button></div>
      </div>
      <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Input label={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        <Select label={t('teachers.statut')} value={fStatut} onChange={(e) => { setFStatut(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{['actif','inactif','suspendu','parti'].map((s) => <option key={s} value={s}>{t('statut_ens.' + s)}</option>)}</Select>
        <Select label={t('teachers.statutEmploi')} value={fEmploi} onChange={(e) => { setFEmploi(e.target.value); setPage(1); }}><option value="">{t('common.all')}</option>{['permanent','contractuel','vacataire','stagiaire'].map((s) => <option key={s} value={s}>{t('emploi.' + s)}</option>)}</Select>
      </div>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-3">{t('teachers.matricule')}</th><th className="px-4 py-3">{t('teachers.nom')}</th><th className="px-4 py-3">{t('teachers.telephone')}</th><th className="px-4 py-3">{t('teachers.email')}</th><th className="px-4 py-3">{t('teachers.statutEmploi')}</th><th className="px-4 py-3">{t('teachers.statut')}</th><th className="px-4 py-3">{t('common.actions')}</th>
            </tr></thead>
            <tbody>
              {list.map((e) => (
                <tr key={e.id_enseignant} className="border-b border-border last:border-0">
                  <td className="px-4 py-3 font-medium text-foreground">{e.matricule_ens}</td>
                  <td className="px-4 py-3 text-foreground">{e.civilite ? e.civilite + ' ' : ''}{e.nom} {e.prenom}</td>
                  <td className="px-4 py-3 text-muted">{e.telephone || '—'}</td>
                  <td className="px-4 py-3 text-muted">{e.email || '—'}</td>
                  <td className="px-4 py-3 text-muted">{t('emploi.' + e.statut_emploi)}</td>
                  <td className="px-4 py-3 text-muted">{t('statut_ens.' + e.statut)}</td>
                  <td className="px-4 py-3"><div className="flex gap-1">
                    <Button size="sm" variant="ghost" icon={Eye} onClick={() => navigate('/enseignants/' + e.matricule_ens)} aria-label={t('common.view')} />
                    <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setForm({ open: true, initial: e })} aria-label={t('common.edit')} />
                    <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => setConfirmDel(e)} aria-label={t('common.delete')} />
                  </div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
      {pages > 1 && (
        <div className="mt-4 flex items-center justify-between">
          <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</Button>
          <span className="text-sm text-muted">{t('common.page')} {page} / {pages}</span>
          <Button size="sm" variant="secondary" disabled={page >= pages} onClick={() => setPage(page + 1)}>{t('common.next')}</Button>
        </div>
      )}
      <Modal open={!!form?.open} onClose={() => setForm(null)} title={form?.initial ? t('common.edit') : t('teachers.new')} size="lg">
        {form?.open && <EnseignantForm initial={form.initial} onClose={() => setForm(null)} onSaved={() => { setForm(null); invalidate(); }} />}
      </Modal>
      <Modal open={!!confirmDel} onClose={() => setConfirmDel(null)} title={t('common.confirmDelete')} footer={(
        <><Button variant="secondary" onClick={() => setConfirmDel(null)}>{t('common.cancel')}</Button><Button variant="danger" onClick={() => del.mutate(confirmDel.id_enseignant)}>{t('common.delete')}</Button></>
      )}><p className="text-sm">{t('teachers.deleteConfirm', { name: (confirmDel?.nom || '') + ' ' + (confirmDel?.prenom || '') })}</p></Modal>
      <Modal open={exportOpen} onClose={() => setExportOpen(false)} title={t('students.exportTitle')} size="sm" footer={<><Button variant="secondary" onClick={() => setExportOpen(false)}>{t('common.cancel')}</Button></>}>
        <div className="flex flex-col gap-2">{['csv', 'xlsx', 'pdf', 'vcf'].map((fmt) => <Button key={fmt} variant="secondary" onClick={() => doExport(fmt)}>{fmt.toUpperCase()}</Button>)}</div>
      </Modal>
    </div>
  );
}