import { useEffect } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { toast } from 'sonner';
import { useAuth } from '../core/providers/AuthProvider';
import { useT } from '../i18n';

function FullPageLoader() {
  const t = useT();
  return (
    <div className="flex min-h-screen items-center justify-center bg-background">
      <div className="flex flex-col items-center gap-3 text-muted">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        <span className="text-sm">{t('common.loading')}</span>
      </div>
    </div>
  );
}

/**
 * Protection de route.
 * - !bootstrapped → loader (évite le bounce avant restauration de session)
 * - !user → redirect /login
 * - requirePermission / requireRole → si absent, toast + redirect /
 * - Sans `children` (route layout) → rend <Outlet /> (routes imbriquées B1.1)
 */
export default function RequireAuth({ children, requirePermission, requireRole }) {
  const { user, bootstrapped, hasPermission, hasRole } = useAuth();
  const t = useT();
  const location = useLocation();

  const denied =
    bootstrapped &&
    user &&
    ((requirePermission && !hasPermission(requirePermission)) ||
      (requireRole && !hasRole(requireRole)));

  useEffect(() => {
    if (denied) {
      toast.error(t('errors.forbidden'));
    }
  }, [denied, t]);

  if (!bootstrapped) return <FullPageLoader />;
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />;
  if (denied) return <Navigate to="/" replace />;

  return children ?? <Outlet />;
}
