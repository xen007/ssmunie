import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Header from './Header';
import Sidebar from './Sidebar';
import Footer from './Footer';

/**
 * Layout partagé de TOUTES les pages protégées (B1.1).
 * Header + Sidebar + <main><Outlet/></main> + Footer.
 * Sidebar fixe ≥ md ; drawer < md (ouverture via le hamburger du Header).
 */
export default function AppLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      <Header onToggleSidebar={() => setSidebarOpen((o) => !o)} />

      <div className="flex flex-1 overflow-hidden">
        <Sidebar mobileOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>

      <Footer />
    </div>
  );
}
