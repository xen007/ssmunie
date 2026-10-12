import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Select from '../../shared/ui/Select';

const PERIODES = ['trim1','trim2','trim3','annual'];

export default function BulletinsPage() {
  const t = useT();
  const navigate = useNavigate();
  const [classe, setClasse] = useState('');
  const [periode, setPeriode] = useState('trim1');
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data, isLoading } = useQuery({ queryKey: ['bulletins', classe, periode], queryFn: async () => (await api.get('/annee/bulletins.php?classe=' + classe + '&report_type=' + periode)).data, enabled: !!classe });
  const students = Object.entries(data?.data?.students ?? {}).map(([id, v]) => ({ id: Number(id), ...v }));
  const eleveById = {};
  // on recharge la liste des élèves pour avoir matricule/nom
  const { data: eleves = [] } = useQuery({ queryKey: ['eleves-classe', classe], queryFn: async () => (await api.get('/annee/eleves.php?classe=' + classe + '&per_page=200')).data.data, enabled: !!classe });
  students.forEach((s) => { const e = eleves.find((x) => x.id_eleve === s.id); if (e) { s.matricule = e.matricule; s.nom = e.nom; s.prenom = e.prenom; } });
  students.sort((a, b) => (a.rang ?? 999) - (b.rang ?? 999));

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('bulletins.title')}</h1><p className="mt-1 text-sm text-muted">{t('bulletins.subtitle')}</p></div>
      <div className="mb-4 grid grid-cols-2 gap-3 sm:max-w-md">
        <Select label={t('bulletins.class')} value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select>
        <Select label={t('bulletins.trimestre')} value={periode} onChange={(e) => setPeriode(e.target.value)}>{PERIODES.map((p) => <option key={p} value={p}>{t('reportType.' + p)}</option>)}</Select>
      </div>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-3">{t('bulletins.rang')}</th><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('bulletins.moyenneGenerale')}</th><th className="px-4 py-3">{t('bulletins.appreciation')}</th><th className="px-4 py-3">{t('bulletins.decision')}</th><th className="px-4 py-3"></th>
            </tr></thead>
            <tbody>
              {students.map((s) => (
                <tr key={s.id} className="border-b border-border last:border-0">
                  <td className="px-4 py-2 font-medium text-foreground">{s.rang ?? '·'}</td>
                  <td className="px-4 py-2 text-foreground">{s.nom} {s.prenom}</td>
                  <td className="px-4 py-2 text-foreground">{s.moyenne_generale != null ? Number(s.moyenne_generale).toFixed(2) : '·'}</td>
                  <td className="px-4 py-2 text-muted">{s.appreciation ? t('decision.' + s.appreciation, null) : '·'}</td>
                  <td className="px-4 py-2 text-muted">{s.decision ? t('decision.' + s.decision) : '·'}</td>
                  <td className="px-4 py-2"><Button size="sm" variant="ghost" onClick={() => navigate('/bulletins/' + s.matricule + '?classe=' + classe + '&report=' + periode)}>{t('common.view')}</Button></td>
                </tr>
              ))}
              {students.length === 0 && !isLoading && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}