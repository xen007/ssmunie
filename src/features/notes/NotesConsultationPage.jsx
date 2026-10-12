import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Select from '../../shared/ui/Select';

export default function NotesConsultationPage() {
  const t = useT();
  const { sections } = usePerimetre();
  const [section, setSection] = useState('2');
  const { data } = useQuery({ queryKey: ['notes-consult', section], queryFn: async () => (await api.get('/annee/notes_consultation.php?section=' + section)).data, enabled: !!section });
  const rows = data?.data ?? [];
  const classes = data?.classes ?? [];

  const seqs = Array.from({ length: 9 }, (_, i) => i + 1);
  return (
    <div className="mx-auto max-w-6xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('consultation.title')}</h1><p className="mt-1 text-sm text-muted">{t('consultation.subtitle')}</p></div>
        <Button variant="secondary" onClick={() => window.print()}>{t('consultation.print')}</Button>
      </div>
      <div className="mb-4 max-w-xs"><Select label={t('perimetre.section')} value={section} onChange={(e) => setSection(e.target.value)}>{sections.map((s) => <option key={s.id} value={s.id}>{s.libelle_fr}</option>)}</Select></div>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-3 py-2">{t('consultation.class')}</th><th className="px-3 py-2">{t('notes.eleve')}</th><th className="px-3 py-2">{t('notes.matiere')}</th><th className="px-3 py-2">{t('notes.evaluation')}</th>
              {seqs.map((s) => <th key={s} className="px-1 py-2 text-center">S{s}</th>)}
            </tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2 text-muted">{r.code_classe}</td>
                  <td className="px-3 py-2 text-foreground">{r.nom} {r.prenom}</td>
                  <td className="px-3 py-2 text-muted">{r.nom_matiere}</td>
                  <td className="px-3 py-2 text-muted">{r.nom_evaluation || '—'}</td>
                  {seqs.map((s) => <td key={s} className="px-1 py-2 text-center text-foreground">{r['m' + s] ?? '·'}</td>)}
                </tr>
              ))}
              {rows.length === 0 && <tr><td colSpan={13} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}