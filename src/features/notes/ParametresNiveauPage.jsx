import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../core/api';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Card from '../../shared/ui/Card';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

export default function ParametresNiveauPage() {
  const t = useT();
  const qc = useQueryClient();
  const { sections } = usePerimetre();
  const [section, setSection] = useState('2');
  const { data } = useQuery({ queryKey: ['parametres-niveau', section], queryFn: async () => (await api.get('/annee/parametres_niveau.php?section=' + section)).data.data });
  const [params, setParams] = useState({});
  const [loaded, setLoaded] = useState(false);
  if (data && !loaded) { const m = {}; (data.parametres || []).forEach((p) => (m[p.cle] = p.valeur)); setParams(m); setLoaded(true); }

  const save = useMutation({ mutationFn: async () => { for (const [cle, valeur] of Object.entries(params)) await api.put('/annee/parametres_niveau.php', { section_id: Number(section), cle, valeur: String(valeur) }); }, onSuccess: () => toast.success(t('parametres.saved')) });
  const reset = useMutation({ mutationFn: async () => api.post('/annee/reset_test_data.php'), onSuccess: () => { qc.invalidateQueries(); toast.success(t('common.deleted')); } });

  return (
    <div className="mx-auto max-w-3xl">
      <div className="mb-4"><h1 className="text-2xl font-bold tracking-tight">{t('parametres.title')}</h1><p className="mt-1 text-sm text-muted">{t('parametres.subtitle')}</p></div>
      <div className="mb-4 max-w-xs"><Select label={t('parametres.section')} value={section} onChange={(e) => { setSection(e.target.value); setLoaded(false); }}>{sections.map((s) => <option key={s.id} value={s.id}>{s.libelle_fr}</option>)}</Select></div>
      <Card className="mb-4 overflow-hidden">
        <div className="overflow-x-auto"><table className="w-full text-sm"><tbody>
          {Object.entries(params).map(([cle, valeur]) => (
            <tr key={cle} className="border-b border-border last:border-0">
              <td className="px-4 py-2 font-medium text-foreground">{cle}</td>
              <td className="px-4 py-2"><Input className="w-40" value={valeur} onChange={(e) => setParams((p) => ({ ...p, [cle]: e.target.value }))} /></td>
            </tr>
          ))}
        </tbody></table></div>
      </Card>
      <div className="flex items-center justify-between">
        <Button variant="danger" onClick={() => { if (confirm(t('common.confirmDelete'))) reset.mutate(); }}>{t('common.reset')}</Button>
        <Button onClick={() => save.mutate()}>{t('parametres.save')}</Button>
      </div>
    </div>
  );
}