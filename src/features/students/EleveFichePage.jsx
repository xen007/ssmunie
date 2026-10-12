import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../../core/api';
import config from '../../core/config';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';

const TABS = ['identite', 'scolarite', 'parents', 'photo', 'historique'];

function Row({ label, value }) {
  return <div className="flex flex-col"><div className="text-xs uppercase tracking-wide text-muted">{label}</div><div className="text-sm text-foreground">{value || '—'}</div></div>;
}

export default function EleveFichePage() {
  const t = useT();
  const navigate = useNavigate();
  const { matricule } = useParams();
  const [tab, setTab] = useState('identite');
  const { data: e, isLoading } = useQuery({
    queryKey: ['eleve', matricule],
    queryFn: async () => (await api.get('/annee/eleve.php?matricule=' + encodeURIComponent(matricule))).data.data,
  });

  return (
    <div className="mx-auto max-w-4xl">
      <Button variant="ghost" size="sm" icon={ArrowLeft} onClick={() => navigate('/eleves')} className="mb-3">{t('students.title')}</Button>
      {isLoading && <p className="text-sm text-muted">{t('common.loading')}</p>}
      {!isLoading && e && (
        <Card className="overflow-hidden">
          <div className="flex items-center gap-4 border-b border-border p-5">
            {e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-16 w-16 rounded-full border border-border object-cover" /> : <div className="h-16 w-16 rounded-full bg-primary-soft" />}
            <div><h1 className="text-xl font-bold text-foreground">{e.nom} {e.prenom}</h1><p className="text-sm text-muted">{e.matricule} · {e.code_classe || '—'}</p></div>
          </div>
          <div className="flex flex-wrap gap-1 border-b border-border px-3">
            {TABS.map((tb) => (
              <button key={tb} type="button" onClick={() => setTab(tb)} className={['-mb-px border-b-2 px-3 py-2 text-sm font-medium', tab === tb ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-foreground'].join(' ')}>{t('students.tabs.' + tb)}</button>
            ))}
          </div>
          <div className="p-5">
            {tab === 'identite' && (
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Row label={t('students.genre')} value={e.genre ? t('genre.' + e.genre) : '—'} />
                <Row label={t('students.dateNaissance')} value={e.date_naissance} />
                <Row label={t('students.lieuNaissance')} value={e.lieu_naissance} />
                <Row label={t('students.nationalite')} value={e.nationalite} />
                <Row label={t('students.adresse')} value={e.adresse} />
                <Row label={t('students.emailParent')} value={e.email_parent} />
                <Row label={t('students.acteNaissance')} value={e.acte_naissance} />
                <Row label={t('students.codeParent')} value={e.code_parent} />
                <Row label={t('students.ethnie')} value={e.ethnie} />
                <Row label={t('students.sante')} value={e.sante} />
                <Row label={t('students.infosSupp')} value={e.infos_supplementaire} />
              </div>
            )}
            {tab === 'scolarite' && (
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Row label={t('students.matricule')} value={e.matricule} />
                <Row label={t('students.classe')} value={e.libellee_classe || e.code_classe} />
                <Row label={t('students.niveau')} value={e.libellee_niveau || e.code_niveau} />
                <Row label={t('classes.section')} value={e.section_code} />
                <Row label={t('classes.filiere')} value={e.filiere_code} />
                <Row label={t('students.statut')} value={t('statut.' + e.statut)} />
                <Row label={t('students.redoublant')} value={e.redoublant ? t('common.yes') : t('common.no')} />
                <Row label={t('students.motifStatut')} value={e.motif_statut} />
              </div>
            )}
            {tab === 'parents' && (
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Row label={t('students.tuteur')} value={e.nom_tuteur} />
                <Row label={t('students.telephone')} value={e.telephone_tuteur} />
                <Row label={t('students.profession')} value={e.profession_tuteur} />
                <Row label={t('students.mere')} value={e.nom_mere} />
                <Row label={t('students.telephone')} value={e.telephone_mere} />
                <Row label={t('students.profession')} value={e.profession_mere} />
              </div>
            )}
            {tab === 'photo' && (
              <div className="flex flex-col items-center gap-3">
                {e.photo_path ? <img src={config.uploadsBaseUrl + '/' + e.photo_path} alt="" className="h-56 w-56 rounded-md border border-border object-cover" /> : <div className="h-56 w-56 rounded-md border border-border bg-primary-soft" />}
              </div>
            )}
            {tab === 'historique' && (
              <div className="flex flex-col gap-3 text-sm">
                <Row label={t('students.dateEnregistrement')} value={e.date_enregistrement} />
                <Row label={t('students.motifStatut')} value={e.motif_statut} />
                <Row label={t('students.infosSupp')} value={e.infos_supplementaire} />
              </div>
            )}
          </div>
        </Card>
      )}
    </div>
  );
}