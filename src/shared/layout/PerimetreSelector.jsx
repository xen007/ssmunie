import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, Filter, RotateCcw } from 'lucide-react';
import { usePerimetre } from '../../core/providers/PerimetreProvider';
import { useT, useLang } from '../../i18n';

/**
 * Sélecteur de périmètre (filtre global section/filière/niveau).
 * Persisté (PerimetreProvider). Badge + menu déroulant + réinitialisation.
 */
export default function PerimetreSelector() {
  const t = useT();
  const { lang } = useLang();
  const { perimetre, setPerimetre, resetPerimetre, sections, filieres, niveaux, filtresActifs } = usePerimetre();
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    const onClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const lab = (item) => (lang === 'fr' ? item.libelle_fr : item.libelle_en);

  const niveauxFiltres = useMemo(() => {
    return niveaux.filter(
      (n) =>
        (!perimetre.section || n.section_id === perimetre.section) &&
        (!perimetre.filiere || n.filiere_id === perimetre.filiere)
    );
  }, [niveaux, perimetre.section, perimetre.filiere]);

  const label = useMemo(() => {
    const parts = [];
    if (perimetre.section) {
      const s = sections.find((x) => x.id === perimetre.section);
      if (s) parts.push(lab(s));
    }
    if (perimetre.filiere) {
      const f = filieres.find((x) => x.id === perimetre.filiere);
      if (f) parts.push(f.code);
    }
    if (perimetre.niveau) {
      const n = niveaux.find((x) => x.id_niveau === perimetre.niveau);
      if (n) parts.push(n.libellee_niveau);
    }
    return parts.length ? parts.join(' · ') : t('perimetre.all');
  }, [perimetre, sections, filieres, niveaux, t, lang]);

  const option = 'flex w-full items-center justify-between rounded px-2.5 py-1.5 text-sm text-foreground transition-colors hover:bg-primary-soft hover:text-primary';

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground transition-colors hover:border-primary"
        title={t('perimetre.title')}
      >
        <span className="flex items-center gap-2 overflow-hidden">
          <Filter className="h-4 w-4 shrink-0 text-muted" />
          <span className="truncate">{label}</span>
          {filtresActifs && (
            <span className="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-white">
              {t('perimetre.badge')}
            </span>
          )}
        </span>
        <ChevronDown className="h-4 w-4 shrink-0 text-muted" />
      </button>

      {open && (
        <div className="absolute left-0 top-full z-40 mt-1 w-72 rounded-md border border-border bg-surface p-2 shadow-lg">
          <button
            type="button"
            className={option}
            onClick={() => {
              resetPerimetre();
              setOpen(false);
            }}
          >
            <span>{t('perimetre.all')}</span>
            {!filtresActifs && <span className="text-primary">✓</span>}
          </button>

          <div className="my-1 h-px bg-border" />
          <div className="px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted">
            {t('perimetre.section')}
          </div>
          {sections.map((s) => (
            <button
              key={s.id}
              type="button"
              className={option}
              onClick={() => {
                setPerimetre({ section: perimetre.section === s.id ? null : s.id });
              }}
            >
              <span>{lab(s)}</span>
              {perimetre.section === s.id && <span className="text-primary">✓</span>}
            </button>
          ))}

          <div className="my-1 h-px bg-border" />
          <div className="px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted">
            {t('perimetre.filiere')}
          </div>
          {filieres.map((f) => (
            <button
              key={f.id}
              type="button"
              className={option}
              onClick={() => {
                setPerimetre({ filiere: perimetre.filiere === f.id ? null : f.id });
              }}
            >
              <span>{f.code} — {lab(f)}</span>
              {perimetre.filiere === f.id && <span className="text-primary">✓</span>}
            </button>
          ))}

          <div className="my-1 h-px bg-border" />
          <div className="flex items-center justify-between px-2.5 pb-1">
            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted">
              {t('perimetre.niveau')}
            </span>
            {niveauxFiltres.length > 12 && (
              <span className="text-[10px] text-muted">{niveauxFiltres.length}</span>
            )}
          </div>
          <div className="max-h-44 overflow-y-auto">
            {niveauxFiltres.map((n) => (
              <button
                key={n.id_niveau}
                type="button"
                className={option}
                onClick={() => {
                  setPerimetre({ niveau: perimetre.niveau === n.id_niveau ? null : n.id_niveau });
                }}
              >
                <span className="truncate">{n.libellee_niveau}</span>
                {perimetre.niveau === n.id_niveau && <span className="text-primary">✓</span>}
              </button>
            ))}
            {niveauxFiltres.length === 0 && (
              <div className="px-2.5 py-1.5 text-xs text-muted">{t('perimetre.empty')}</div>
            )}
          </div>

          {filtresActifs && (
            <>
              <div className="my-1 h-px bg-border" />
              <button
                type="button"
                className="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-muted transition-colors hover:bg-danger/10 hover:text-danger"
                onClick={() => {
                  resetPerimetre();
                  setOpen(false);
                }}
              >
                <RotateCcw className="h-4 w-4" />
                {t('perimetre.reset')}
              </button>
            </>
          )}
        </div>
      )}
    </div>
  );
}
