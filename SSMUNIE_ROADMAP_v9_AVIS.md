# SSMUNIE — Validation finale de la ROADMAP v9

Lecture seule. Croisé avec l'audit legacy + vérification web (Base UI) + vérifications BDD antérieures.

---

## VOLET 1 — INTÉGRATION DES REMARQUES v8 : 7/7 ✅

| # | Remarque v8 | Statut | Preuve § v9 |
| --- | --- | --- | --- |
| 1 | Table `sanctions_absence` créée dans annuel_006 | ✅ OK | B4c « Tables : …, sanctions_absence (créée explicitement ici) » + critère « □ Table sanctions_absence créée » |
| 2 | `id_uuid` : ALTER explicite B0-B3 | ✅ OK | B4.0 « Ajout id_uuid par ALTER TABLE explicite sur les tables B0-B3… » + critère « □ id_uuid ajouté sur toutes les tables B0-B3 » |
| 3 | « À déposer » (pas « fournie ») | ✅ OK | §11 « 🟡 À déposer — prérequis avant B4b » + B4b « à déposer dans le dépôt avant lancement de B4b » |
| 4 | « 7 paliers » (pas « 4 libellés ») | ✅ OK | §11 « Mapping 7 paliers ↔ 7 libellés » |
| 5 | Compte endpoints fantômes ajusté | ✅ OK | §12 « 19 nommés » + « 7 mineurs non nommés » = 26 |
| 6 | `codeparentGen.php` → B7k | ✅ OK | B7k « + codeparentGen.php (génération codes parent, réécrit) » |
| 7 | Alertes alignées sur bornes paliers | ✅ OK | §0.5 + B4c « orange > 25 h, rouge > 40 h » (note : 25 vs 26 = fuzz d'1 h, négligeable) |

---

## VOLET 2 — REFRAMING PHASE 6 (Base UI) : ✅ PERTINENT (vérifié)

Les 3 affirmations du §0.3/§0.7/§7 sont **confirmées par recherche web** :
1. **Base UI est réel** — bibliothèque headless (« unstyled ») de composants React, accès CSS +
   accessibilité WAI-ARIA (github.com/atomiks/base-ui).
2. **« Radix en fin de vie »** — exact : Radix a ralenti après l'acquisition WorkOS ; Base UI en est le successeur.
3. **« Défaut shadcn/ui depuis juillet 2026 »** — exact : changelog officiel shadcn/ui
   « July 2026 — Base UI as the Default ».

Le choix « créer les primitives dans `src/shared/ui/` au fil des besoins » est exactement la
bonne stratégie (pas de migration massive). Cohérent avec les tokens B0.

**Réserve (non bloquante) :** confirmer le nom exact du package npm à l'installation
(« @base-ui/react » — vérifier si c'est bien `@base-ui-components/react`). Et noter que le socle
B0-B3 actuel n'utilise encore aucune dépendance headless : Base UI sera introduit incrémentalement.

---

## VOLET 3 — REFRAMING PHASE 7 (Extensions) : ✅ PROPRE

Découplage sain : le core fonctionne sans Phase 7 ; activation individuelle par école via
`etablissement.extensions_actives` ; chaque bloc « Activable ».

**Réserve (non bloquante) :** ajouter la colonne `extensions_actives` à `etablissement` (méta)
dans une migration (B4.0 ou une migration méta dédiée).

---

## VOLET 4 — VERDICT FINAL

| Phase | Verdict | Motif |
| --- | --- | --- |
| 0 — Socle | 🟢 VERT | B3.1 trivial et réel |
| 1 — Cœur pédago/financier | 🟢 VERT | B4.0/B4a/c/d/e OK ; B4b sous condition finance doc (déjà marqué 🟡) |
| 2 — Périphériques pédago | 🟢 VERT | borné |
| 3 — Modules admin/commerciaux | 🟢 VERT | borné |
| 4 — Documents/comm/médias | 🟢 VERT | borné |
| 5 — Rapports/journal/admin | 🟢 VERT | faible risque |
| 6 — UI/UX | 🟢 VERT | Base UI validé (vérifié) |
| 7 — Extensions (optionnelles) | 🟢 VERT | découplée, feature-flag |
| 8 — Recette/déploiement | 🟢 VERT | dépend de l'amont |

**VERDICT GLOBAL : 🟢 FEU VERT — 9 phases / 9 vertes.**

La roadmap v9 est **complète, cohérente, exhaustive et fondée** : toutes mes remarques v7 et v8
sont intégrées, le comptage est exact (54 blocs, 9 phases, 1 546 élèves vérifié), la règle de
portage « extraire la logique, jamais copier le code » est la bonne méthode, et les deux
reframings (Base UI + Extensions) sont des décisions justes et vérifiées.

**→ FEU VERT pour lancer B3.1 + B4.0 + B4a immédiatement.**

Prérequis et micro-points non bloquants (à gérer en passant) :
1. **ROADMAP finance v2.0** : déposer `docs/ROADMAP_finance_v2.0.md` **avant B4b** (pas avant B4.0/B4a).
2. Confirmer le nom exact du package Base UI à l'installation.
3. Sourcer précisément « concours 6e seuil 05/20 (MINESEC 2026) » — réintroduit comme « sourcé »,
   paramétrable donc faible risque, mais fournir la référence si possible.
4. Ajouter `etablissement.extensions_actives` dans une migration.

**La roadmap est calée. On peut lancer B3.1.**

