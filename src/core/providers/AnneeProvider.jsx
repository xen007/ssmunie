import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api';

const AnneeContext = createContext(null);

/**
 * Établissement + année scolaire courante (D22).
 * - Charge GET /api/meta/getEtablissement.php (public).
 * - setAnneeCourante(id) : POST setAnneeCourante.php + invalidation des caches.
 * - createAnnee(payload) : POST createAnnee.php (admin).
 */
export function AnneeProvider({ children }) {
  const queryClient = useQueryClient();
  const [anneeCouranteId, setAnneeCouranteId] = useState(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['etablissement'],
    queryFn: async () => {
      const res = await api.get('/meta/getEtablissement.php');
      return res.data;
    },
  });

  const etablissement = data?.etablissement ?? null;
  const anneesDisponibles = data?.anneesDisponibles ?? [];

  const anneeCourante = useMemo(() => {
    const id = anneeCouranteId ?? etablissement?.annee_courante_id;
    return (
      anneesDisponibles.find((a) => a.id === id) ??
      anneesDisponibles[0] ??
      null
    );
  }, [anneeCouranteId, etablissement, anneesDisponibles]);

  const setAnneeCourante = useCallback(
    async (id) => {
      await api.post('/meta/setAnneeCourante.php', { anneeId: id });
      setAnneeCouranteId(id);
      queryClient.invalidateQueries(); // D22 : invalidation de tous les caches
    },
    [queryClient]
  );

  const createAnnee = useCallback(
    async (payload) => {
      const res = await api.post('/setup/createAnnee.php', payload);
      await queryClient.invalidateQueries({ queryKey: ['etablissement'] });
      return res.data;
    },
    [queryClient]
  );

  return (
    <AnneeContext.Provider
      value={{
        etablissement,
        anneeCourante,
        anneesDisponibles,
        isLoading,
        error,
        setAnneeCourante,
        createAnnee,
      }}
    >
      {children}
    </AnneeContext.Provider>
  );
}

export function useAnnee() {
  const ctx = useContext(AnneeContext);
  if (!ctx) {
    throw new Error('useAnnee doit être utilisé dans <AnneeProvider>');
  }
  return ctx;
}
