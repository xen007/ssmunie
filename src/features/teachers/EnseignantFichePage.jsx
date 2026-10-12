import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Trash2, Upload } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import config from '../../core/config';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

const TABS = ['identite', 'emploi', 'documents', 'habilitations', 'photo'];
const DOC_TYPES = ['cni', 'contrat', 'cv', 'diplome', 'attestation', 'autre'];

function Row({ label, value }) { return <div className="flex flex-col"><div className="text-xs uppercase tracking-wide text-muted">{label}</div><div className="text-sm text-foreground">{value || '—'}</div></div>; }

export default function EnseignantFichePage() {
  const t = useT();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { matricule } = useParams();
  const [tab, setTab] = useState('identite');
  const [docType, setDocType] = useState('diplome');
  const [dragging, setDragging] = useState(false);
  const photoRef = useRef(null);

  const { data: e } = useQuery({ queryKey: ['enseignant', matricule], queryFn: async () => (await api.get('/annee/enseignant.php?matricule=' + encodeURIComponent(matricule))).data.data });
  const ensId = e?.id_enseignant;
  const { data: docs = [] } = useQuery({ queryKey: ['enseignant-docs', ensId], queryFn: async () => (await api.get('/annee/enseignant_docs.php?enseignant_id=' + ensId)).data.data, enabled: !!ensId });
  const { data: habs = [] } = useQuery({ queryKey: ['enseignant-habs', ensId], queryFn: async () => (await api.get('/annee/habilitations.php?enseignant=' + ensId)).data.data, enabled: !!ensId });

  const invalidate = () => { qc.invalidateQueries({ queryKey: ['enseignant'] }); qc.invalidateQueries({ queryKey: ['enseignant-docs'] }); qc.invalidateQueries({ queryKey: ['enseignant-habs'] }); };

  const upload = useMutation({
    mutationFn: async ({ file, type }) => { const fd = new FormData(); fd.append('file', file); fd.append('enseignant_id', String(ensId)); fd.append('type', type); return api.post('/annee/enseignant_docs.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); },
    onSuccess: () => { invalidate(); toast.success(t('common.updated')); },
    onError: () => toast.error(t('errors.generic')),
  });
  const delDoc = useMutation({ mutationFn: async (id) => api.delete('/annee/enseignant_docs.php?id=' + id), onSuccess: () => { invalidate(); toast.success(t('common.deleted')); } });

  const onDrop = (ev) => { ev.preventDefault(); setDragging(false); const file = ev.dataTransfer.files?.[0]; if (file) upload.mutate({ file, type: docType }); };
  const onFile = (ev) => { const file = ev.target.files?.[0]; if (file) upload.mutate({ file, type: docType }); };

  return (
    <div className="mx-auto max-w-4xl">
      <Button variant="ghost" size="sm" icon={ArrowLeft} onClick={() => navigate('/enseignants')} className="mb-3">{t('teachers.title')}</Button>
      {e && (
        <Card className="overflow-hidden">
          <div className="flex items-center gap-4 border-b border-border p-5">
            {e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-16 w-16 rounded-full border border-border object-cover" /> : <div className="h-16 w-16 rounded-full bg-primary-soft" />}
            <div><h1 className="text-xl font-bold text-foreground">{e.civilite ? e.civilite + ' ' : ''}{e.nom} {e.prenom}</h1><p className="text-sm text-muted">{e.matricule_ens} · {t('emploi.' + e.statut_emploi)}</p></div>
          </div>
          <div className="flex flex-wrap gap-1 border-b border-border px-3">
            {TABS.map((tb) => <button key={tb} type="button" onClick={() => setTab(tb)} className={['-mb-px border-b-2 px-3 py-2 text-sm font-medium', tab === tb ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-foreground'].join(' ')}>{t('teachers.tabs.' + tb)}</button>)}
          </div>
          <div className="p-5">
            {tab === 'identite' && <div className="grid grid-cols-2 gap-4 sm:grid-cols-3"><Row label={t('teachers.genre')} value={e.genre ? t('genre.' + e.genre) : '—'} /><Row label={t('teachers.dateNaissance')} value={e.date_naissance} /><Row label={t('teachers.lieuNaissance')} value={e.lieu_naissance} /><Row label={t('teachers.nationalite')} value={e.nationalite} /><Row label={t('teachers.adresse')} value={e.adresse} /><Row label={t('teachers.telephone')} value={e.telephone} /><Row label={t('teachers.email')} value={e.email} /></div>}
            {tab === 'emploi' && <div className="grid grid-cols-2 gap-4 sm:grid-cols-3"><Row label={t('teachers.statutEmploi')} value={t('emploi.' + e.statut_emploi)} /><Row label={t('teachers.grade')} value={e.grade} /><Row label={t('teachers.specialite')} value={e.specialite} /><Row label={t('teachers.dateEmbauche')} value={e.date_embauche} /><Row label={t('teachers.debutContrat')} value={e.debut_contrat} /><Row label={t('teachers.finContrat')} value={e.fin_contrat} /><Row label={t('teachers.salaire')} value={e.salaire_base} /><Row label={t('teachers.langues')} value={e.langues} /><Row label={t('teachers.statut')} value={t('statut_ens.' + e.statut)} /><Row label={t('teachers.motifStatut')} value={e.motif_statut} /></div>}
            {tab === 'documents' && (
              <div className="flex flex-col gap-4">
                <div className="flex items-end gap-2"><Select label={t('teachers.documents')} value={docType} onChange={(ev) => setDocType(ev.target.value)} className="w-48">{DOC_TYPES.map((dt) => <option key={dt} value={dt}>{t('teachers.' + dt)}</option>)}</Select></div>
                <div onDragOver={(ev) => { ev.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={onDrop} className={['flex flex-col items-center justify-center rounded-md border-2 border-dashed p-6 text-center text-sm text-muted', dragging ? 'border-primary bg-primary-soft' : 'border-border'].join(' ')}>
                  <Upload className="h-6 w-6" /><p>{t('teachers.dragDrop')}</p>
                  <label className="mt-2 cursor-pointer"><span className="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm text-foreground">{t('common.select')}</span><input type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" onChange={onFile} /></label>
                </div>
                <div className="flex flex-col gap-2">
                  {docs.map((d) => (
                    <div key={d.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2">
                      <a href={config.uploadsBaseUrl + '/' + d.chemin} target="_blank" rel="noreferrer" className="text-sm text-primary hover:underline">{t('teachers.' + d.type)} — {d.nom_original || d.chemin.split('/').pop()}</a>
                      <Button size="sm" variant="ghost" icon={Trash2} className="text-danger" onClick={() => delDoc.mutate(d.id)} aria-label={t('common.delete')} />
                    </div>
                  ))}
                  {docs.length === 0 && <p className="text-sm text-muted">{t('common.noResults')}</p>}
                </div>
              </div>
            )}
            {tab === 'habilitations' && (
              <div className="flex flex-col gap-2">
                {habs.map((h) => <div key={h.id} className="rounded-md border border-border px-3 py-2 text-sm text-foreground">{h.nom_matiere} {h.code_classe ? '— ' + h.code_classe : ''}</div>)}
                {habs.length === 0 && <p className="text-sm text-muted">{t('common.noResults')}</p>}
              </div>
            )}
            {tab === 'photo' && (
              <div className="flex flex-col items-center gap-3">
                {e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-56 w-56 rounded-md border border-border object-cover" /> : <div className="h-56 w-56 rounded-md border border-border bg-primary-soft" />}
                <Input ref={photoRef} type="file" accept="image/*" onChange={(ev) => { const file = ev.target.files?.[0]; if (file) upload.mutate({ file, type: 'photo' }); }} />
              </div>
            )}
          </div>
        </Card>
      )}
    </div>
  );
}