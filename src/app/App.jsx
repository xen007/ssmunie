import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'sonner';
import { QueryProvider } from '../core/providers/QueryProvider';
import { AuthProvider } from '../core/providers/AuthProvider';
import { AnneeProvider } from '../core/providers/AnneeProvider';
import { PerimetreProvider } from '../core/providers/PerimetreProvider';
import { ThemeProvider, useTheme } from '../core/providers/ThemeProvider';
import SessionWarningModal from '../core/components/SessionWarningModal';
import AppRoutes from './router';

function ThemedToaster() {
  const { theme } = useTheme();
  return <Toaster position="top-right" richColors theme={theme} />;
}

/**
 * Ordre : QueryProvider > AuthProvider > AnneeProvider > ThemeProvider.
 * Toaster Sonner monté une seule fois + modale d'expiration de session.
 */
export default function App() {
  return (
    <QueryProvider>
      <AuthProvider>
        <AnneeProvider>
          <PerimetreProvider>
          <ThemeProvider>
            <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
              <ThemedToaster />
              <SessionWarningModal />
              <AppRoutes />
            </BrowserRouter>
          </ThemeProvider>
          </PerimetreProvider>
        </AnneeProvider>
      </AuthProvider>
    </QueryProvider>
  );
}
