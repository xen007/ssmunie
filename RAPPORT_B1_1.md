# SSMUNIE — Rapport de vérification B1.1 (layout partagé)

Correctif : **Header + Sidebar + Footer sur TOUTES les pages protégées** via un
`AppLayout` unique et des routes imbriquées React Router 6.

- Nouveau : `src/shared/layout/AppLayout.jsx`
- Modifiés : `src/app/router.jsx`, `src/app/RequireAuth.jsx`, `src/shared/layout/Sidebar.jsx`,
  `src/shared/layout/Header.jsx`, `src/features/home/HomePage.jsx`,
  `src/features/accounts/AccountManage.jsx`, `src/features/roles/RolesManage.jsx`,
  `src/i18n/locales/{fr,en}.json`
- Date : 2026-10-07

---

## 1. Critères d'acceptation — 8 / 8 ✅

Vérification par Chrome DevTools Protocol (navigation SPA réelle, pas de rechargement).

| # | Critère | Statut | Preuve (CDP) |
| --- | --- | --- | --- |
| 1 | Sidebar visible sur `/` | ✅ | `{sidebar:true, links:[/, /accountManage, /rolesManage], active:["/"]}` |
| 2 | Sidebar visible sur `/accountManage` | ✅ | `{sidebar:true, active:["/accountManage"]}` |
| 3 | Sidebar visible sur `/rolesManage` | ✅ | `{sidebar:true, active:["/rolesManage"]}` |
| 4 | Sidebar sur toutes les pages protégées futures | ✅ | structure : `RequireAuth` → `AppLayout` → `<Outlet/>` ; toute route enfant hérite du layout |
| 5 | Item actif change selon la route | ✅ | `active` : `["/"]` → `["/accountManage"]` → `["/rolesManage"]` |
| 6 | Navigation sans recharger la Sidebar (SPA) | ✅ | marqueur `data-test-tag="TAG-123"` posé puis **conservé** après 2 navigations SPA → nœud non remonté |
| 7 | Mobile < md : sidebar cachée + hamburger | ✅ | `{visibleAside:0, burgerVisible:true}` ; après clic → `{drawerOpen:true, links:3}` |
| 8 | Aucun layout dupliqué | ✅ | grep : seuls `AppLayout` (→ Header/Sidebar/Footer) et `router` (→ AppLayout) référencent le layout |

---

## 2. Non-régression B1 (30/30)

| Vérification | Résultat |
| --- | --- |
| Accès non authentifié à `/accountManage` | redirige vers `/login` ✅ |
| `enseignant` sur `/accountManage` (sans `accounts.manage`) | redirige vers `/` ✅ |
| Sidebar filtrée par permission (`enseignant`) | liens = `["/"]` seulement (Élèves/Notes désactivés, Comptes/Rôles masqués) ✅ |
| `npm run build` | OK (1711 modules, 350 KB) ✅ |

Aucune modification de la logique auth / rôles / permissions. Aucune dépendance ajoutée.

---

## 3. Captures d'écran

Dossier : `docs/screenshots/`

| Fichier | Contenu |
| --- | --- |
| `layout-home.png` | `/` — Sidebar visible, item **Accueil** actif |
| `layout-accountManage.png` | `/accountManage` — Sidebar visible, item **Comptes** actif |
| `layout-rolesManage.png` | `/rolesManage` — Sidebar visible, item **Rôles** actif |
| `layout-mobile-closed.png` | < md — sidebar masquée, hamburger visible |
| `layout-mobile-open.png` | < md — drawer ouvert via le hamburger |

---

## 4. Détail technique

`router.jsx` :

```jsx
<Route element={<RequireAuth />}>            {/* garde d'authentification + <Outlet/> */}
  <Route element={<AppLayout />}>            {/* Header + Sidebar + <main><Outlet/></main> + Footer */}
    <Route index element={<HomePage />} />
    <Route path="accountManage" element={<RequireAuth requirePermission="accounts.manage"><AccountManage /></RequireAuth>} />
    <Route path="rolesManage"   element={<RequireAuth requirePermission="roles.manage"><RolesManage /></RequireAuth>} />
  </Route>
</Route>
```

- `RequireAuth` sans `children` rend désormais `<Outlet />` (routes layout).
- `AppLayout` garde le même arbre entre deux navigations → Sidebar non remontée (critère 6).
- `Sidebar` : colonne fixe ≥ `md`, drawer `< md` (état `mobileOpen` remonté depuis `AppLayout`).
- `Header` : bouton hamburger `md:hidden` (prop `onToggleSidebar`).
- Les pages ne rendent plus que leur contenu (`HomePage` / `AccountManage` / `RolesManage`),
  le padding est fourni par `AppLayout`.

### Note périmètre
`Changer de mot de passe` reste une **modale** ouverte depuis le menu profil du Header
(équivalent du `/changePassword` mentionné) — conforme au B1 validé, sans ajout de route.
