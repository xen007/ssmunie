import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../api';
import { useAnnee } from './AnneeProvider';
import { useAuth } from './AuthProvider';

const PerimetreContext = createContext(null);
const STORAGE_KEY = 'ssmunie-perimetre';

const EMPTY = { section: null, filiere: null, niveau: null, classe: null };

function readStored() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const p = JSON.parse(raw);
      return {
        section: p.section ?? null,
        filiere: p.filiere ?? null,
        niveau: p.niveau ?? null,
        classe: p.classe ?? null,
      };
    }
  } catch {
    /* ignore */
  }
  return { ...EMPTY };
}

/**
 * Périmètre de travail (filtre global) : section / filière / niveau / classe.
 * Persisté dans localStorage ("ssmunie-perimetre").
 */
export function PerimetreProvider({ children }) {
  const { anneeCourante } = useAnnee();
  const { user } = useAuth();
  const [perimetre, setPerimetreState] = useState(readStored);

  const sectionsQ = useQuery({
    queryKey: ['sections'],
    queryFn: async () => (await api.get('/meta/sections.php')).data.data,
    staleTime: Infinity,
  });
  const filieresQ = useQuery({
    queryKey: ['filieres'],
    queryFn: async () => (await api.get('/meta/filieres.php')).data.data,
    staleTime: Infinity,
  });
  const niveauxQ = useQuery({
    queryKey: ['niveaux', anneeCourante?.id],
    queryFn: async () => (await api.get('/annee/niveaux.php')).data.data,
    enabled: !!anneeCourante?.id && !!user,
  });

  const sections = sectionsQ.data ?? [];
  const filieres = filieresQ.data ?? [];
  const niveaux = niveauxQ.data ?? [];

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(perimetre));
    } catch {
      /* ignore */
    }
  }, [perimetre]);

  const setPerimetre = useCallback((patch) => {
    setPerimetreState((p) => {
      const next = { ...p, ...patch };
      if (patch.section !== undefined && patch.section !== p.section) {
        next.niveau = null;
        next.classe = null;
      }
      if (patch.filiere !== undefined && patch.filiere !== p.filiere) {
        next.niveau = null;
        next.classe = null;
      }
      return next;
    });
  }, []);

  const resetPerimetre = useCallback(() => {
    setPerimetreState({ ...EMPTY });
  }, []);

  const filtresActifs = !!(perimetre.section || perimetre.filiere || perimetre.niveau || perimetre.classe);

  const value = {
    perimetre,
    setPerimetre,
    resetPerimetre,
    sections,
    filieres,
    niveaux,
    filtresActifs,
  };

  return <PerimetreContext.Provider value={value}>{children}</PerimetreContext.Provider>;
}

export function usePerimetre() {
  const ctx = useContext(PerimetreContext);
  if (!ctx) {
    throw new Error('usePerimetre doit être utilisé dans <PerimetreProvider>');
  }
  return ctx;
}
