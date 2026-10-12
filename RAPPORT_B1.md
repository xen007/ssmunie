# SSMUNIE — Rapport de vérification B1 v2

Bloc **B1** : authentification réelle, rôles & permissions dynamiques, setup wizard,
sessions en base de données, durcissement (A1→A5 + F1→F21).

- Migration : `sql/migrations/002_auth.sql` (MariaDB 10.4+, idempotente)
- Back : `api/_session_handler.php` (sessions BDD), `db.php`, `_middleware.php`,
  `_helpers.php` + 9 endpoints
- Front : AuthProvider, RequireAuth, SetupPage, AccountManage, RolesManage,
  ChangePassword, SessionWarningModal, Modal, Sidebar/Header filtrés
- Date : 2026-10-07

---

## 1. Critères d'acceptation — 30 / 30 ✅

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Base vierge → `/` redirige `/setup` | ✅ | `setup_completed=0` → `/login`→`checkSetup`→`/setup` (DOM vérifié) |
| 2 | Wizard 3 étapes crée établissement + année + admin | ✅ | `POST complete.php` → 200, upsert id=1 + auto-login |
| 3 | Auto-login + redirect `/` après setup | ✅ | `complete.php` retourne `{user}` + session BDD + cookie |
| 4 | `/login` valides → accès `/` | ✅ | curl + CDP : `auth.php` 200 → home rendu |
| 5 | Identifiants invalides → 401 + toast | ✅ | `auth.php` 401 `invalid_credentials` ; toast `useT` |
| 6 | Mauvais mot de passe → 401 (pas clair) | ✅ | `password_verify` uniquement ; 401 générique |
| 7 | Session persiste après refresh | ✅ | bootstrap `check_session` restaure l'utilisateur (cookie `ssm_sid`) |
| 8 | 25 min → modale d'avertissement | ✅ | `check_session` → `warning` + `remaining` (testé : age=1600 → warning 200 s) |
| 9 | 30 min → déconnexion + `/login` | ✅ | `idle > 1800` → `session_expired` + destroy (testé : age=1900 → 401) |
| 10 | « Rester connecté » réarme | ✅ | `refresh.php` → `last_activity = time()` (testé : remaining 1799 après) |
| 11 | `/accountManage` si `accounts.manage` | ✅ | RequireAuth + middleware 403 sinon (enseignant → 403) |
| 12 | `/rolesManage` si `roles.manage` | ✅ | idem |
| 13 | Créer enseignant → se connecte, voit ses items | ✅ | `prof1` → login 200, permissions `[students.view, grades.view, grades.edit]` |
| 14 | Retirer `grades.view` → item Notes disparaît après reconnexion | ✅ | `rolesM PUT` → re-login → permissions sans `grades.view` |
| 15 | admin non supprimable / modifiable | ✅ | `accountM DELETE id=1` → 403 ; `rolesM PUT/DELETE admin` → 403 |
| 16 | Supprimer user → sessions invalidées immédiatement | ✅ | DELETE user + `DELETE FROM sessions` + revalidation BDD (`session_version`) |
| 17 | Aucun mot de passe en clair | ✅ | `SELECT LEFT(password,20)` → `$2y$10$…` |
| 18 | `session_regenerate_id(true)` au login | ✅ | `error_log` + cookie changé (log Apache « session_regenerate_id OK ») |
| 19 | Middleware refuse sans session/permission | ✅ | `_middleware` → 401 `unauthenticated` / 403 `forbidden` |
| 20 | Chaînes UI via `useT()` | ✅ | ~100 clés FR/EN ; erreurs API traduites par code |
| 21 | Cookie `ssm_sid` / `__Host-ssm_sid` | ✅ | `session_name()` A1 ; jamais `SSMUNIE_SESSIONID` |
| 22 | Cookie modifié → 401 (fingerprint) | ✅ | `_fp` = hash(UA + IP) vérifié à chaque requête |
| 23 | 12 h → 401 absolu | ✅ | `_created` + `SSMUNIE_ABSOLUTE_TIMEOUT=43200` (testé) |
| 24 | Changement de mdp → session invalidée | ✅ | `session_version+1` → revalidation → 401 (testé : prof1 → 401) |
| 25 | Hash `$2y$` en BDD | ✅ | `password_hash(PASSWORD_DEFAULT)` |
| 26 | DELETE rôle admin → 403 | ✅ | `rolesM DELETE id=1` → 403 |
| 27 | Accès `sql/…/001_init.sql` → 403 | ✅ | `.htaccess` racine → Apache 403 |
| 28 | Accès direct `api/db.php` → 403 | ✅ | `api/.htaccess` → 403 |
| 29 | CORS autre origine → refusé | ✅ | CORS retiré de `db.php` ; pas d'`Access-Control-Allow-Origin` |
| 30 | ChangePassword self + admin autrui | ✅ | self (`old_password`) + admin (`accounts.manage` pour autrui) |

---

## 2. Captures d'écran

Dossier : `docs/screenshots/`

| Fichier | Contenu |
| --- | --- |
| `setup-step1.png` | Wizard étape 1 — Établissement |
| `setup-step2.png` | Wizard étape 2 — Année scolaire |
| `setup-step3.png` | Wizard étape 3 — Administrateur |
| `login.png` | Page de connexion |
| `login-error.png` | Toast « Identifiants invalides » |
| `home-connected.png` | Accueil connecté (admin, sidebar complète) |
| `account-manage.png` | Table des comptes + actions |
| `roles-manage.png` | Table des rôles + permissions |
| `session-warning.png` | Modale « Session sur le point d'expirer » (countdown) |

---

## 3. Corrections apportées pendant B1

1. **`bind_param` par référence** — les littéraux (`'admin'`) et éléments de tableau
   (`$currentUser['id']`) ne peuvent pas être passés par référence à `bind_param`.
   Corrigé (variables intermédiaires) + helper `ssmunie_bind_dynamic()` pour les listes
   `IN (?, ?, …)`.
2. **`useT()` instable** — renvoyait une nouvelle fonction à chaque rendu → boucle de
   re-fetch dans `AccountManage`/`RolesManage` (dépendance `[t]`). Corrigé avec
   `useCallback` (référence stable par langue).
3. **Warnings React Router v7** — ajout des `future` flags sur `<BrowserRouter>`.

> Note de vérification : la capture de la modale d'avertissement a été faite via CDP
> (Chrome DevTools Protocol) car `--virtual-time-budget` de Chrome headless est
> instable avec les redirections + fetchs chaînés. Le flux backend complet
> (login → vieillissement → `check_session` = `warning`) a été vérifié en curl **et**
> via fetch dans le navigateur.

---

## 4. Reproduction

```bash
cd C:\xampp\htdocs\ssmunie
# 1. Base vierge (wizard) :
mysql -u root -e "DROP DATABASE IF EXISTS ssmunie_meta;"
mysql -u root < sql/migrations/001_init.sql
mysql -u root < sql/migrations/002_auth.sql
npm run dev   # http://localhost:5173 → redirige /setup
# 2. Après setup : admin / (mot de passe choisi)
```

Variables d'environnement : `APP_ENV` (défaut `dev`), `DB_HOST`, `DB_USER`, `DB_PASS`,
`DB_META` (défauts : `127.0.0.1` / `root` / vide / `ssmunie_meta`).
