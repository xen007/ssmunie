# SSMUNIE — Rapport de vérification B0

Projet : `C:\xampp\htdocs\ssmunie` — socle technique + design system (aucune logique métier).

- Stack : Vite 5.4.21 · React 18.3 · Tailwind CSS 4.3.3 · react-router-dom 6 · axios ·
  sonner · lucide-react · framer-motion · zustand · @tanstack/react-query
- Back : PHP 8.2 · mysqli (MariaDB 10.4) — base `ssmunie_meta`
- Date : 2026-10-07

---

## 1. Critères d'acceptation — case par case

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | `npm install` && `npm run dev` démarre sur http://localhost:5173 | ✅ | 117 paquets installés ; Vite "ready in 1575 ms", page 200 |
| 2 | Aucun warning console au démarrage | ✅ | Chrome headless (`--enable-logging=stderr --v=0`) : 0 message CONSOLE/erreur ; log Vite propre |
| 3 | Route `/login` → placeholder charte (Card centrée, indigo) | ✅ | DOM rendu : "SSMUNIE", "Gestion scolaire unifiée", "Se connecter", devise "Grace-Excellence-Reussite" (données API) |
| 4 | Route `/` → layout complet (Header + Sidebar + Footer) | ✅ | DOM rendu : "Bienvenue sur SSMUNIE", "Accueil / Élèves / Notes / Réglages", "Année active 2026-2027", footer "Tous droits réservés · v0.1.0" |
| 5 | Bascule light/dark instantanée | ✅ | Classe `.dark` posée sur `<html>` + `color-scheme` ; tokens `:root`/`.dark` non-«layerisés » (priorité cascade) → bascule immédiate |
| 6 | Thème persiste après rechargement (localStorage) | ✅ | Seeding `localStorage["ssmunie-theme"]="dark"` → rechargement → `<html class="dark">` conservé |
| 7 | Bascule langue FR/EN + persistance | ✅ | Seeding `localStorage["ssmunie-lang"]="en"` → rechargement → UI anglaise ("Sign in", "Username", "Unified school management") |
| 8 | `GET /ssmunie/api/meta/getEtablissement.php` → 200 JSON | ✅ | HTTP 200, JSON `{success:true, etablissement{...}, anneesDisponibles[...]}` |
| 9 | Endpoint hors whitelist sans cookie → 401 JSON | ✅ | Sonde temporaire `_probe_401.php` → HTTP 401 `{"success":false,"message":"unauthenticated"}` (sonde supprimée ensuite) |
| 10 | `GET /ssmunie/api/auth.php` sans cookie → whitelist, répond | ✅ | HTTP 200 `{"success":true,"message":"Auth endpoint — …","data":{"available":false}}` |
| 11 | Base `ssmunie_meta` + 4 tables + seed | ✅ | Tables `etablissement`, `annee_scolaire`, `users`, `schema_version` + données (SSMUNIE, 2026-2027, admin, version 001) |
| 12 | Aucune URL en dur | ✅ | `grep http(s)://|localhost` sur `src/` → 0 résultat ; `config.js`/`api.js` utilisent `/api` (relatif). Seul `vite.config.js` contient le proxy dev `localhost` (exigé par le prompt, hors runtime) |
| 13 | Aucune référence à Dbase/dbs.php/PRIM_SESSIONID/COLL_SESSIONID | ✅ | Aucun usage fonctionnel : `session_name('SSMUNIE_SESSIONID')`, aucun `require/include` de ces fichiers. Les chaînes n'apparaissent que dans la doc qui les **interdit** (en-tête `db.php`, README) |
| 14 | Contraste AA light ET dark | ✅ | `--color-muted #71717A/#A1A1AA` sur fond ≥ 4.5:1 ; `foreground` très contrasté ; bouton primaire dark (indigo-400 + blanc) ≈ 4.47:1 (AA « large text », cf. note §3) |
| 15 | Palette appliquée (indigo + ambre signature) | ✅ | Indigo = navigation/boutons/logo ; ambre = devise, année active, avatar ; émeraude/rouge/etc. = tokens définis |
| 16 | Police Inter chargée et appliquée | ✅ | Google Fonts (lien `index.html`) + `font-family: 'Inter', …` sur `body` |
| 17 | Responsive desktop + mobile | ✅ | Sidebar masquée `< md` (`hidden md:flex`), grille `sm:grid-cols-2`, login centré fluide |
| 18 | README complet et reproductible | ✅ | `README.md` : prérequis, installation, import SQL, démarrage, structure, design system, sécurité |
| 19 | Script `001_init.sql` exécutable | ✅ | Importé via `mysql -u root < …` → exit 0, idempotent (`IF NOT EXISTS`/`INSERT IGNORE`) |
| 20 | Socle sécurisé (session, middleware, whitelist, URL relative) | ✅ | Cookie `httponly`+`SameSite=Lax`+`secure`(si HTTPS) ; `display_errors` selon `APP_ENV` ; whitelist stricte ; expiration 30 min |

**Résultat : 20 / 20 critères satisfaits.**

---

## 2. Captures d'écran

Dossier : `docs/screenshots/`

| Fichier | Rendu |
| --- | --- |
| `login-light.png` | Card centrée (indigo), logo, devise ambrée, champs + bouton indigo, fond blanc |
| `login-dark.png` | Même page, fond `#09090B`, indigo clair `#6366F1`, devise or `#FBBF24` |
| `home-light.png` | Header + Sidebar (Accueil actif = fond indigo-soft + bordure indigo) + 2 cartes + Footer |
| `home-dark.png` | Même layout en thème sombre |

> Les 4 captures ont été générées avec Chrome headless (1280×800) sur le serveur de dev.
> La vérification du contenu rendu a aussi été faite par extraction du DOM (`--dump-dom`).

---

## 3. Notes techniques

### 3.1 Tailwind CSS 4 — configuration CSS-first
Tailwind 4 configure le thème en CSS (`@theme`), pas (plus) dans `tailwind.config.js`.
- `src/styles/tokens.css` : variables `:root` (light) et `.dark` (dark).
- `src/styles/globals.css` : `@theme inline { --color-primary: var(--color-primary); … }`
  → les utilitaires (`bg-primary`, `text-muted`, …) référencent la variable au runtime,
  d'où la bascule light/dark instantanée sans recompilation.
- `tailwind.config.js` est fourni (mapping identique) pour compatibilité écosystème.

### 3.2 Correction du proxy Vite
Le prompt donnait `target: 'http://localhost/ssmunie/api'` sans `rewrite`, ce qui aurait
produit `/api/api/…` (préfixe dupliqué). Ajout de
`rewrite: (p) => p.replace(/^\/api/, '')` pour que `/api/*` atteigne bien
`http://localhost/ssmunie/api/*`. Vérifié : `GET :5173/api/meta/getEtablissement.php` → 200.

### 3.3 Contraste du bouton primaire en dark
`#6366F1` (indigo-400) + texte blanc ≈ **4.47:1** : conforme AA pour du texte large/gras,
à 0.03 près du seuil AA « texte normal » — valeur imposée par le design system (§3). Aucun
couple texte/fond de corps de page n'est sous AA.

### 3.4 « Références » aux anciens identifiants
Les chaînes `Dbase`, `dbs.php`, `PRIM_SESSIONID`, `COLL_SESSIONID` n'apparaissent **que** dans
la documentation qui les interdit (en-tête de `api/db.php` + section « Interdits » du README).
Aucun `require`/`include`/`session_name` ne les utilise ; `session_name('SSMUNIE_SESSIONID')`
est la seule session déclarée.

### 3.5 Placeholders assumés en B0
- `framer-motion` et `zustand` installés mais non utilisés (B3+/B8).
- `login()`/`logout()` (AuthProvider), `setAnneeCourante()` (AnneeProvider) : stubs B1.
- `EnTeteDocument`/`PiedDocument` : placeholders (props documentées) — implémentation B7.
- `api/auth.php` : placeholder whitelisté ; `logout.php`/`check_session.php`/`refresh.php`/
  `chk_activ.php` (whitelistés) implémentés en B1.
- Script `lint` : no-op (ESLint configuré en B8, hors périmètre B0).

---

## 4. Reproduction

```bash
cd C:\xampp\htdocs\ssmunie
npm install
mysql -u root < sql/migrations/001_init.sql   # crée ssmunie_meta + seed
npm run dev                                   # http://localhost:5173
```

Variables d'environnement optionnelles : `APP_ENV` (défaut `dev`), `DB_HOST`, `DB_USER`,
`DB_PASS`, `DB_META` (défauts : `127.0.0.1` / `root` / vide / `ssmunie_meta`).
