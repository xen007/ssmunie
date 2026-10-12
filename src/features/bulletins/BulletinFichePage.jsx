import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';

export default function BulletinFichePage() {
  const t = useT();
  const navigate = useNavigate();
  const { matricule } = useParams();
  const [sp] = useSearchParams();
  const classe = sp.get('classe') || '';
  const report = sp.get('report') || 'trim1';
  const { data } = useQuery({ queryKey: ['bulletins', classe, report], queryFn: async () => (await api.get('/annee/bulletins.php?classe=' + classe + '&report_type=' + report)).data, enabled: !!classe });
  const students = data?.data?.students ?? {};
  // trouver l'élève par matricule via la liste des élèves
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-classe', classe], queryFn: async () => (await api.get('/annee/eleves.php?classe=' + classe + '&per_page=200')).data.data, enabled: !!classe });
  const eleve = eleves.find((e) => e.matricule === matricule);
  const s = eleve ? students[eleve.id_eleve] : null;
  const ca = data?.data?.class_averages ?? {};

  return (
    <div className="mx-auto max-w-4xl">
      <Button variant="ghost" size="sm" icon={ArrowLeft} onClick={() => navigate('/bulletins')} className="mb-3">{t('bulletins.title')}</Button>
      {s ? (
        <Card className="overflow-hidden">
          <div className="border-b border-border p-5">
            <h1 className="text-xl font-bold text-foreground">{eleve.nom} {eleve.prenom}</h1>
            <p className="text-sm text-muted">{eleve.matricule} · {eleve.code_classe} · {t('reportType.' + report)}</p>
          </div>
          <div className="overflow-x-auto p-3">
            <table className="w-full text-sm">
              <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
                <th className="px-3 py-2">{t('bulletins.matieres')}</th><th className="px-3 py-2">{t('bulletins.note')}</th><th className="px-3 py-2">{t('bulletins.coef')}</th><th className="px-3 py-2">{t('bulletins.total')}</th><th className="px-3 py-2">{t('bulletins.rang')}</th><th className="px-3 py-2">{t('bulletins.appreciation')}</th>
              </tr></thead>
              <tbody>
                {(s.matieres || []).map((m) => (
                  <tr key={m.matiere_id} className="border-b border-border last:border-0">
                    <td className="px-3 py-2 text-foreground">{m.nom}</td>
                    <td className="px-3 py-2 text-foreground">{m.moy != null ? Number(m.moy).toFixed(2) : '·'}</td>
                    <td className="px-3 py-2 text-muted">{m.coef}</td>
                    <td className="px-3 py-2 text-muted">{m.total != null ? Number(m.total).toFixed(2) : '·'}</td>
                    <td className="px-3 py-2 text-muted">{m.rang_matiere ?? '·'}</td>
                    <td className="px-3 py-2 text-muted">{m.appreciation ? t('decision.' + m.appreciation, null) : '·'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="grid grid-cols-2 gap-4 border-t border-border p-5 sm:grid-cols-4">
            <div><div className="text-xs uppercase text-muted">{t('bulletins.moyenneGenerale')}</div><div className="text-lg font-bold text-foreground">{s.moyenne_generale != null ? Number(s.moyenne_generale).toFixed(2) : '·'}</div></div>
            <div><div className="text-xs uppercase text-muted">{t('bulletins.rang')}</div><div className="text-lg font-bold text-foreground">{s.rang ?? '·'}</div></div>
            <div><div className="text-xs uppercase text-muted">{t('bulletins.moyClasse')}</div><div className="text-lg font-bold text-foreground">{ca.middle != null ? Number(ca.middle).toFixed(2) : '·'}</div></div>
            <div><div className="text-xs uppercase text-muted">{t('bulletins.decision')}</div><div className="text-lg font-bold text-foreground">{s.decision ? t('decision.' + s.decision) : '·'}</div></div>
          </div>
          {s.motif && <p className="border-t border-border px-5 py-3 text-sm text-muted">{t('bulletins.motif')} : {s.motif}</p>}
        </Card>
      ) : <p className="text-sm text-muted">{t('common.loading')}</p>}
    </div>
  );
}