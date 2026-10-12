import { useState } from 'react';
import { toast } from 'sonner';
import api from '../../core/api';
import { useT } from '../../i18n';
import Button from '../../shared/ui/Button';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';

function Accordion({ title, open, onToggle, children }) {
  return (
    <div className="rounded-md border border-border">
      <button type="button" onClick={onToggle} className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-foreground"><span>{title}</span><span className="text-muted">{open ? '−' : '+'}</span></button>
      {open && <div className="border-t border-border px-4 py-4">{children}</div>}
    </div>
  );
}

export default function EnseignantForm({ initial, onClose, onSaved }) {
  const t = useT();
  const [open, setOpen] = useState(0);
  const [saving, setSaving] = useState(false);
  const [f, setF] = useState({
    id_enseignant: initial?.id_enseignant ?? null,
    civilite: initial?.civilite ?? '', nom: initial?.nom ?? '', prenom: initial?.prenom ?? '',
    date_naissance: initial?.date_naissance ?? '', lieu_naissance: initial?.lieu_naissance ?? '', genre: initial?.genre ?? '', nationalite: initial?.nationalite ?? 'Camerounaise',
    statut_emploi: initial?.statut_emploi ?? 'contractuel', grade: initial?.grade ?? '', specialite: initial?.specialite ?? '',
    date_embauche: initial?.date_embauche ?? '', debut_contrat: initial?.debut_contrat ?? '', fin_contrat: initial?.fin_contrat ?? '',
    salaire_base: initial?.salaire_base ?? '', langues: initial?.langues ?? '',
    telephone: initial?.telephone ?? '', email: initial?.email ?? '', adresse: initial?.adresse ?? '',
    statut: initial?.statut ?? 'actif', motif_statut: initial?.motif_statut ?? '',
  });
  const set = (k) => (e) => setF((p) => ({ ...p, [k]: e.target.value }));

  const save = async () => {
    if (!f.nom || !f.prenom) { toast.error(t('errors.validation_error')); return; }
    setSaving(true);
    try {
      if (f.id_enseignant) await api.put('/annee/enseignant.php', f);
      else await api.post('/annee/enseignants.php', f);
      toast.success(t('common.updated')); onSaved?.();
    } catch { toast.error(t('errors.generic')); } finally { setSaving(false); }
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-3 gap-3">
        <Select label={t('teachers.civilite')} value={f.civilite} onChange={set('civilite')}><option value="">—</option><option value="M">{t('civilite.M')}</option><option value="Mme">{t('civilite.Mme')}</option><option value="Mlle">{t('civilite.Mlle')}</option></Select>
        <Input label={t('teachers.nom')} required value={f.nom} onChange={set('nom')} />
        <Input label={t('teachers.prenom')} required value={f.prenom} onChange={set('prenom')} />
      </div>
      <Accordion title={t('teachers.form.identite')} open={open === 0} onToggle={() => setOpen(open === 0 ? -1 : 0)}>
        <div className="grid grid-cols-2 gap-3">
          <Input type="date" label={t('teachers.dateNaissance')} value={f.date_naissance} onChange={set('date_naissance')} />
          <Input label={t('teachers.lieuNaissance')} value={f.lieu_naissance} onChange={set('lieu_naissance')} />
          <Select label={t('teachers.genre')} value={f.genre} onChange={set('genre')}><option value="">—</option><option value="M">{t('genre.M')}</option><option value="F">{t('genre.F')}</option></Select>
          <Input label={t('teachers.nationalite')} value={f.nationalite} onChange={set('nationalite')} />
        </div>
      </Accordion>
      <Accordion title={t('teachers.form.emploi')} open={open === 1} onToggle={() => setOpen(open === 1 ? -1 : 1)}>
        <div className="grid grid-cols-2 gap-3">
          <Select label={t('teachers.statutEmploi')} value={f.statut_emploi} onChange={set('statut_emploi')}>{['permanent','contractuel','vacataire','stagiaire'].map((s) => <option key={s} value={s}>{t('emploi.' + s)}</option>)}</Select>
          <Input label={t('teachers.grade')} value={f.grade} onChange={set('grade')} />
          <Input label={t('teachers.specialite')} value={f.specialite} onChange={set('specialite')} />
          <Input type="date" label={t('teachers.dateEmbauche')} value={f.date_embauche} onChange={set('date_embauche')} />
          <Input type="date" label={t('teachers.debutContrat')} value={f.debut_contrat} onChange={set('debut_contrat')} />
          <Input type="date" label={t('teachers.finContrat')} value={f.fin_contrat} onChange={set('fin_contrat')} />
          <Input type="number" step="0.01" label={t('teachers.salaire')} value={f.salaire_base} onChange={set('salaire_base')} />
          <Input label={t('teachers.langues')} value={f.langues} onChange={set('langues')} />
        </div>
      </Accordion>
      <Accordion title={t('teachers.form.contact')} open={open === 2} onToggle={() => setOpen(open === 2 ? -1 : 2)}>
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('teachers.telephone')} value={f.telephone} onChange={set('telephone')} />
          <Input label={t('teachers.email')} value={f.email} onChange={set('email')} />
          <Input label={t('teachers.adresse')} value={f.adresse} onChange={set('adresse')} />
          <Select label={t('teachers.statut')} value={f.statut} onChange={set('statut')}>{['actif','inactif','suspendu','parti'].map((s) => <option key={s} value={s}>{t('statut_ens.' + s)}</option>)}</Select>
        </div>
      </Accordion>
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
        <Button disabled={saving} onClick={save}>{t('common.save')}</Button>
      </div>
    </div>
  );
}