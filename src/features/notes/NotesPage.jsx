import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

export default function NotesPage() {
  const t = useT();
  const qc = useQueryClient();
  const [classe, setClasse] = useState('');
  const [matiere, setMatiere] = useState('');
  const [seq, setSeq] = useState('1');
  const [evalId, setEvalId] = useState('1');
  const [marks, setMarks] = useState({});

  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });
  const { data: matieres = [] } = useQuery({ queryKey: ['matieres-all'], queryFn: async () => (await api.get('/annee/matieres.php?actif=1')).data.data });
  const { data: evals = [] } = useQuery({ queryKey: ['evaluations'], queryFn: async () => (await api.get('/annee/evaluations.php')).data.data });
  const { data: students = [] } = useQuery({ queryKey: ['eleves-classe', classe], queryFn: async () => (await api.get('/annee/eleves.php?classe=' + classe + '&per_page=200')).data.data, enabled: !!classe });
  const { data: existing = [] } = useQuery({ queryKey: ['notes', classe, matiere], queryFn: async () => (await api.get('/annee/notes.php?classe=' + classe + '&matiere=' + matiere)).data.data, enabled: !!classe && !!matiere });

  const classeObj = classes.find((c) => c.id_classe === Number(classe));
  const section = classeObj?.section_id;
  const isPrimaire = section === 1 || section === 2;
  const maxSeq = isPrimaire ? 9 : 6;
  const matieresF = useMemo(() => matieres.filter((m) => m.section_id === section), [matieres, section]);

  useEffect(() => {
    const map = {};
    for (const n of existing) { if (isPrimaire && n.eval_id !== Number(evalId)) continue; map[n.eleve_id] = n['m' + seq] ?? ''; }
    setMarks(map);
  }, [existing, seq, evalId, isPrimaire]);

  const save = useMutation({
    mutationFn: async () => {
      const body = { classe: Number(classe), matiere: Number(matiere), seq: Number(seq), notes: Object.entries(marks).filter(([, v]) => v !== '').map(([id, v]) => ({ eleve_id: Number(id), note: Number(v), eval_id: Number(evalId) })) };
      return api.post('/annee/notes.php', body);
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['notes'] }); toast.success(t('notes.saved')); },
    onError: () => toast.error(t('errors.generic')),
  });

  const setMark = (id, v) => setMarks((p) => ({ ...p, [id]: v }));

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold tracking-tight">{t('notes.title')}</h1><p className="mt-1 text-sm text-muted">{t('notes.subtitle')}</p></div>
        <Link to="/notes/consultation"><Button variant="secondary">{t('notes.consultation')}</Button></Link>
      </div>
      <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Select label={t('notes.classe')} value={classe} onChange={(e) => { setClasse(e.target.value); setMatiere(''); }}><option value="">—</option>{classes.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe}</option>)}</Select>
        <Select label={t('notes.matiere')} value={matiere} onChange={(e) => setMatiere(e.target.value)} disabled={!classe}><option value="">—</option>{matieresF.map((m) => <option key={m.id_matiere} value={m.id_matiere}>{m.nom_matiere}</option>)}</Select>
        <Select label={t('notes.sequence')} value={seq} onChange={(e) => setSeq(e.target.value)}>{Array.from({ length: maxSeq }, (_, i) => i + 1).map((s) => <option key={s} value={s}>S{s}</option>)}</Select>
        {isPrimaire && <Select label={t('notes.evaluation')} value={evalId} onChange={(e) => setEvalId(e.target.value)}>{evals.map((ev) => <option key={ev.id_eval} value={ev.id_eval}>{ev.nom_evaluation}</option>)}</Select>}
      </div>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted"><th className="px-4 py-3">{t('notes.eleve')}</th><th className="px-4 py-3">{t('notes.note')} / 20</th></tr></thead>
            <tbody>
              {students.map((s) => (
                <tr key={s.id_eleve} className="border-b border-border last:border-0">
                  <td className="px-4 py-2 text-foreground">{s.nom} {s.prenom} <span className="text-xs text-muted">({s.matricule})</span></td>
                  <td className="px-4 py-2"><Input type="number" step="0.5" min="0" max="20" className="w-24" value={marks[s.id_eleve] ?? ''} onChange={(e) => setMark(s.id_eleve, e.target.value)} /></td>
                </tr>
              ))}
              {students.length === 0 && <tr><td colSpan={2} className="px-4 py-8 text-center text-muted">{t('common.noResults')}</td></tr>}
            </tbody>
          </table>
        </div>
      </Card>
      {classe && matiere && (
        <div className="mt-4 flex justify-end"><Button disabled={save.isPending} onClick={() => save.mutate()}>{t('notes.save')}</Button></div>
      )}
    </div>
  );
}