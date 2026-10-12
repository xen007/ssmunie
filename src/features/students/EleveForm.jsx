import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../core/api';
import config from '../../core/config';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';
import Button from '../../shared/ui/Button';
import Input from '../../shared/ui/Input';
import Select from '../../shared/ui/Select';
import Textarea from '../../shared/ui/Textarea';

const MODES = ['standard', 'section', 'classe'];

function Accordion({ title, open, onToggle, children }) {
  return (
    <div className="rounded-md border border-border">
      <button type="button" onClick={onToggle} className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-foreground">
        <span>{title}</span><span className="text-muted">{open ? '−' : '+'}</span>
      </button>
      {open && <div className="border-t border-border px-4 py-4">{children}</div>}
    </div>
  );
}

export default function EleveForm({ initial, onClose, onSaved }) {
  const t = useT();
  const { lang } = useLang();
  const { sections, filieres, niveaux } = usePerimetre();
  const [open, setOpen] = useState(0);
  const [saving, setSaving] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(initial?.photo_path ? config.uploadsBaseUrl + '/' + initial.photo_path : null);
  const [webcamOn, setWebcamOn] = useState(false);
  const videoRef = useRef(null);
  const streamRef = useRef(null);

  const [f, setF] = useState({
    id_eleve: initial?.id_eleve ?? null,
    nom: initial?.nom ?? '', prenom: initial?.prenom ?? '',
    date_naissance: initial?.date_naissance ?? '', lieu_naissance: initial?.lieu_naissance ?? '',
    genre: initial?.genre ?? '', nationalite: initial?.nationalite ?? 'Camerounaise',
    matricule_mode: initial?.matricule_mode ?? 'standard',
    section_id: initial?.section_id ? String(initial.section_id) : '',
    filiere_id: initial?.filiere_id ? String(initial.filiere_id) : '',
    niveau_id: initial?.niveau_id ? String(initial.niveau_id) : '',
    classe_id: initial?.classe_id ? String(initial.classe_id) : '',
    adresse: initial?.adresse ?? '',
    nom_tuteur: initial?.nom_tuteur ?? '', telephone_tuteur: initial?.telephone_tuteur ?? '', profession_tuteur: initial?.profession_tuteur ?? '',
    nom_mere: initial?.nom_mere ?? '', telephone_mere: initial?.telephone_mere ?? '', profession_mere: initial?.profession_mere ?? '',
    email_parent: initial?.email_parent ?? '', acte_naissance: initial?.acte_naissance ?? '', code_parent: initial?.code_parent ?? '',
    redoublant: initial?.redoublant ?? 0, sante: initial?.sante ?? '', malade: initial?.malade ?? 0, ethnie: initial?.ethnie ?? '', infos_supplementaire: initial?.infos_supplementaire ?? '',
    statut: initial?.statut ?? 'actif', motif_statut: initial?.motif_statut ?? '',
  });

  const set = (k) => (e) => setF((p) => ({ ...p, [k]: e.target.value }));
  const setNum = (k) => (e) => setF((p) => ({ ...p, [k]: Number(e.target.value) }));

  const { data: classes = [] } = useQuery({ queryKey: ['classes-all'], queryFn: async () => (await api.get('/annee/classes.php?actif=all')).data.data });

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);
  const niveauxFiltres = useMemo(() => niveaux.filter((n) => (!f.section_id || n.section_id === Number(f.section_id)) && (!f.filiere_id || n.filiere_id === Number(f.filiere_id))), [niveaux, f.section_id, f.filiere_id]);
  const classesFiltrees = useMemo(() => classes.filter((c) => (!f.section_id || c.section_id === Number(f.section_id)) && (!f.filiere_id || c.filiere_id === Number(f.filiere_id))), [classes, f.section_id, f.filiere_id]);

  // Aperçu matricule
  const [preview, setPreview] = useState('');
  useEffect(() => {
    let ok = true;
    const qs = new URLSearchParams({ mode: f.matricule_mode });
    if (f.matricule_mode === 'section' && f.section_id) qs.set('section', f.section_id);
    if (f.matricule_mode === 'classe' && f.classe_id) qs.set('classe', f.classe_id);
    api.get('/annee/matricule_preview.php?' + qs.toString()).then((r) => { if (ok) setPreview(r.data.data?.matricule ?? ''); }).catch(() => {});
    return () => { ok = false; };
  }, [f.matricule_mode, f.section_id, f.classe_id]);

  const pickClasse = (classeId) => {
    const c = classes.find((x) => x.id_classe === Number(classeId));
    if (c) setF((p) => ({ ...p, classe_id: classeId, niveau_id: String(c.niveau_id), section_id: String(c.section_id), filiere_id: String(c.filiere_id) }));
    else setF((p) => ({ ...p, classe_id: classeId }));
  };

  const onPhoto = (e) => { const file = e.target.files?.[0]; if (file) { setPhotoFile(file); setPhotoPreview(URL.createObjectURL(file)); } };

  const startWebcam = async () => {
    try { const stream = await navigator.mediaDevices.getUserMedia({ video: true }); streamRef.current = stream; setWebcamOn(true); setTimeout(() => { if (videoRef.current) { videoRef.current.srcObject = stream; videoRef.current.play(); } }, 50); } catch { toast.error(t('errors.generic')); }
  };
  const capture = () => {
    const video = videoRef.current; if (!video) return;
    const canvas = document.createElement('canvas'); canvas.width = 400; canvas.height = 400;
    const ctx = canvas.getContext('2d');
    const s = Math.min(video.videoWidth, video.videoHeight);
    const sx = (video.videoWidth - s) / 2, sy = (video.videoHeight - s) / 2;
    ctx.drawImage(video, sx, sy, s, s, 0, 0, 400, 400);
    canvas.toBlob((blob) => { if (blob) { setPhotoFile(new File([blob], 'photo.jpg', { type: 'image/jpeg' })); setPhotoPreview(URL.createObjectURL(blob)); } }, 'image/jpeg', 0.85);
    stopWebcam();
  };
  const stopWebcam = () => { streamRef.current?.getTracks().forEach((tr) => tr.stop()); streamRef.current = null; setWebcamOn(false); };
  useEffect(() => () => stopWebcam(), []);

  const save = async () => {
    if (!f.nom || !f.prenom || !f.section_id || !f.filiere_id) { toast.error(t('errors.validation_error')); return; }
    setSaving(true);
    try {
      const body = { ...f, redoublant: Number(f.redoublant), malade: Number(f.malade) };
      if (!body.classe_id) body.classe_id = null;
      const res = body.id_eleve ? await api.put('/annee/eleve.php', body) : await api.post('/annee/eleve.php', body);
      const eleve = res.data.data;
      if (photoFile && eleve?.matricule) {
        const fd = new FormData(); fd.append('photo', photoFile); fd.append('matricule', eleve.matricule);
        await api.post('/annee/eleve_photo_upload.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      }
      toast.success(t('common.updated')); onSaved?.();
    } catch { toast.error(t('errors.generic')); } finally { setSaving(false); }
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-2 gap-3">
        <Input label={t('students.nom')} required value={f.nom} onChange={set('nom')} />
        <Input label={t('students.prenom')} required value={f.prenom} onChange={set('prenom')} />
      </div>

      <Accordion title={t('students.form.identite')} open={open === 0} onToggle={() => setOpen(open === 0 ? -1 : 0)}>
        <div className="grid grid-cols-2 gap-3">
          <Input type="date" label={t('students.dateNaissance')} value={f.date_naissance} onChange={set('date_naissance')} />
          <Input label={t('students.lieuNaissance')} value={f.lieu_naissance} onChange={set('lieu_naissance')} />
          <Select label={t('students.genre')} value={f.genre} onChange={set('genre')}><option value="">—</option><option value="M">{t('genre.M')}</option><option value="F">{t('genre.F')}</option></Select>
          <Input label={t('students.nationalite')} value={f.nationalite} onChange={set('nationalite')} />
          <Input label={t('students.adresse')} value={f.adresse} onChange={set('adresse')} />
          <Input label={t('students.emailParent')} value={f.email_parent} onChange={set('email_parent')} />
          <Input label={t('students.acteNaissance')} value={f.acte_naissance} onChange={set('acte_naissance')} />
          <Input label={t('students.codeParent')} value={f.code_parent} onChange={set('code_parent')} />
        </div>
      </Accordion>

      <Accordion title={t('students.form.parents')} open={open === 1} onToggle={() => setOpen(open === 1 ? -1 : 1)}>
        <div className="grid grid-cols-3 gap-3">
          <Input label={t('students.tuteur')} value={f.nom_tuteur} onChange={set('nom_tuteur')} />
          <Input label={t('students.telephone')} value={f.telephone_tuteur} onChange={set('telephone_tuteur')} />
          <Input label={t('students.profession')} value={f.profession_tuteur} onChange={set('profession_tuteur')} />
          <Input label={t('students.mere')} value={f.nom_mere} onChange={set('nom_mere')} />
          <Input label={t('students.telephone')} value={f.telephone_mere} onChange={set('telephone_mere')} />
          <Input label={t('students.profession')} value={f.profession_mere} onChange={set('profession_mere')} />
        </div>
      </Accordion>

      <Accordion title={t('students.form.scolarite')} open={open === 2} onToggle={() => setOpen(open === 2 ? -1 : 2)}>
        <div className="flex flex-col gap-3">
          <Select label={t('students.form.matriculeMode')} value={f.matricule_mode} onChange={set('matricule_mode')}>{MODES.map((m) => <option key={m} value={m}>{t('students.form.' + m)}</option>)}</Select>
          <div className="grid grid-cols-2 gap-3">
            <Select label={t('students.form.section')} required value={f.section_id} onChange={(e) => setF((p) => ({ ...p, section_id: e.target.value, niveau_id: '', classe_id: '' }))}><option value="">—</option>{sections.map((s) => <option key={s.id} value={s.id}>{lab(s)}</option>)}</Select>
            <Select label={t('classes.filiere')} required value={f.filiere_id} onChange={(e) => setF((p) => ({ ...p, filiere_id: e.target.value, niveau_id: '', classe_id: '' }))}><option value="">—</option>{filieres.map((fl) => <option key={fl.id} value={fl.id}>{fl.code}</option>)}</Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Select label={t('students.niveau')} value={f.niveau_id} onChange={(e) => { const n = niveaux.find((x) => x.id_niveau === Number(e.target.value)); setF((p) => ({ ...p, niveau_id: e.target.value, ...(n ? { section_id: String(n.section_id), filiere_id: String(n.filiere_id) } : {}) })); }}><option value="">—</option>{niveauxFiltres.map((n) => <option key={n.id_niveau} value={n.id_niveau}>{n.libellee_niveau}</option>)}</Select>
            <Select label={t('students.classe')} value={f.classe_id} onChange={(e) => pickClasse(e.target.value)}><option value="">—</option>{classesFiltrees.map((c) => <option key={c.id_classe} value={c.id_classe}>{c.code_classe} — {c.libellee_classe}</option>)}</Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Select label={t('students.statut')} value={f.statut} onChange={set('statut')}>
              {['actif','inactif','diplome','transfere','decede'].map((s) => <option key={s} value={s}>{t('statut.' + s)}</option>)}
            </Select>
            <Select label={t('students.redoublant')} value={f.redoublant} onChange={setNum('redoublant')}><option value={0}>{t('common.no')}</option><option value={1}>{t('common.yes')}</option></Select>
          </div>
          <Textarea label={t('students.sante')} value={f.sante} onChange={set('sante')} />
          <div className="text-xs text-muted">{t('students.matricule')} : <span className="font-semibold text-foreground">{preview || '…'}</span></div>
          <div className="rounded-md border border-border p-3">
            <div className="mb-2 text-sm font-medium">{t('students.form.photo')}</div>
            <div className="flex items-start gap-3">
              {photoPreview && <img src={photoPreview} alt="" className="h-24 w-24 rounded-md border border-border object-cover" />}
              <div className="flex flex-col gap-2">
                <label className="cursor-pointer"><span className="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm">{t('students.form.photo')}</span><input type="file" accept="image/*" className="hidden" onChange={onPhoto} /></label>
                {!webcamOn ? <Button variant="secondary" size="sm" onClick={startWebcam}>{t('students.form.useWebcam')}</Button> : (
                  <div className="flex items-center gap-2"><video ref={videoRef} className="h-24 w-24 rounded-md border border-border object-cover" muted /><Button size="sm" onClick={capture}>{t('students.form.capture')}</Button><Button size="sm" variant="ghost" onClick={stopWebcam}>{t('common.cancel')}</Button></div>
                )}
              </div>
            </div>
          </div>
        </div>
      </Accordion>

      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
        <Button disabled={saving} onClick={save}>{t('common.save')}</Button>
      </div>
    </div>
  );
}