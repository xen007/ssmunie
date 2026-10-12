import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { useT } from '../../i18n';
import Card from '../../shared/ui/Card';
import Select from '../../shared/ui/Select';
const f = (n) => Number(n ?? 0).toLocaleString('fr-FR');

export default function InscriptionsPage() {
  const t = useT(); const [classe, setClasse] = useState('');
  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: rows = [] } = useQuery({ queryKey: ['inscriptions', classe], queryFn: async () => (await api.get('/annee/inscriptions.php?classe=' + classe)).data.data, enabled: !!classe });
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('inscriptions.title')}</h1><p className="mt-1 text-sm text-muted">{t('inscriptions.subtitle')}</p></div>
      <div className="mb-4 max-w-xs"><Select label="Classe" value={classe} onChange={(e) => setClasse(e.target.value)}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select></div>
      <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('scolarite.montant')}</th><th className="px-4 py-3">{t('scolarite.code')}</th><th className="px-4 py-3">{t('scolarite.date')}</th><th className="px-4 py-3">{t('scolarite.statut')}</th></tr></thead>
        <tbody>{rows.map((r) => (<tr key={r.id_ins} className="border-b border-border last:border-0"><td className="px-4 py-2 text-foreground">{r.nom} {r.prenom}</td><td className="px-4 py-2 text-foreground">{f(r.montant)} F</td><td className="px-4 py-2 text-muted">{r.code_versement}</td><td className="px-4 py-2 text-muted">{r.date_inscription}</td><td className="px-4 py-2 text-muted">{r.valide ? '✓' : t('common.deleted')}</td></tr>))}{rows.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}</tbody>
      </table></div></Card>
    </div>
  );
}