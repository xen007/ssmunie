# SSMUNIE — Avis final sur la ROADMAP v8

Lecture seule. Croisé avec l'audit legacy + vérifications BDD réalisées en v7.

---

## VOLET 1 — INTÉGRATION DE MES REMARQUES v7 (11 points)

| # | Remarque v7 | Statut | Preuve § v8 |
| --- | --- | --- | --- |
| 1 | ROADMAP finance référencée explicitement | ✅ OK | B4b « docs/ROADMAP_finance_v2.0.md… à déposer avant lancement » + §11 |
| 2 | Doublon sanctions_absence.php résolu | ✅ OK | B4a note « appartient à B4c » ; B4c « (propriétaire) » |
| 3 | Mapping 7 paliers clarifié | ✅ OK | §0.5 détaille les 7 paliers |
| 4 | Référentiel APC configurable + seed | ✅ OK | §0.6 + B4a table `competences_apc` |
| 5 | Seuil 6e technique 05/20 retiré | ✅ OK | §0.7 « Retiré (non sourcé) » |
| 6 | B3.2 → B4.0 renommé | ✅ OK | §2 « B4.0 — renommé depuis B3.2 » |
| 7 | Critères d'acceptation étendus B4b-e | ✅ OK | B4b/B4c/B4d/B4e ont chacun leurs critères |
| 8 | Endpoints fantômes nommés | ✅ OK | §12 liste 8 EDT + 19 ssm nommément |
| 9 | Correspondance B5a/B5b | ✅ OK | §3 note « ordre v7/v8 : B5a avant B5b » |
| 10 | B9a shadcn reporté | ✅ OK | §0.7 + §7 « réévaluation fin Phase 1 » |
| 11 | Phase 7 offline différée | ✅ OK | §0.7 + §8 « différée après pilote » |

**Bilan : 11/11 intégrées.**

---

## VOLET 2 — EXHAUSTIVITÉ FINALE

| Élément | Statut | Remarque |
| --- | --- | --- |
| 20 fonctionnalités audit §1 | ✅ OK | toutes mappées |
| Tables fantômes / schéma seul | ✅ OK | materiel/emargements/notifications/depenses/bus → B6c/B5c/B7h/B6e/B6b |
| Portail parent | ✅ OK | B7k |
| Migration legacy | ✅ OK | B4.0 |
| Passage de classe | ✅ OK | B4e |
| `codeparentGen.php` (génération code_parent) | 🟡 DOUTE | petit utilitaire legacy non cité — à rattacher à B7k (génération des codes d'accès parent) |
| `seedcol/trig/typeEleve/Dbase/dbs/view` | ✅ OK | §12 abandonnés |

**Bilan : exhaustif.** Seul `codeparentGen.php` reste en zone grise (mineur).

---

## VOLET 3 — COHÉRENCE

| Bloc | Point | Statut |
| --- | --- | --- |
| B4a / B4c | **Table `sanctions_absence` référencée mais non créée** : B4a « lit la table sanctions_absence », B4c « alimente la table sanctions_absence », mais aucune des deux migrations (annuel_004, annuel_006) ne la liste. | 🟡 à corriger : l'ajouter explicitement dans annuel_006 (B4c) |
| B4.0 | `id_uuid` sur les tables B0-B3 déjà créées (classe, eleve, matiere, enseignant…) : couvert par « toutes les tables métier », mais la migration `annuel_000` devra faire des ALTER explicites. | 🟡 à expliciter |
| §11 | « ROADMAP finance v2.0 : ✅ Fournie (à déposer dans docs/) » — « fournie » et « à déposer » sont contradictoires (le fichier n'est pas dans le repo, vérifié). | 🟡 reformuler « 🟡 À déposer — prérequis avant B4b » |
| §11 | « Mapping 7 paliers ↔ 4 libellés » — §0.5 donne en réalité 7 libellés distincts (palier par palier). | 🟡 wording vestigial |
| §12 | « 26 endpoints fantômes ssm » mais seulement 19 nommés. | 🟡 ajuster le compte ou compléter la liste |
| B4c | Alertes « >35h rouge » vs paliers §0.5 (30-40h, 41h+) — seuil 35h chevauche le palier 6. | 🟡 aligner le seuil d'alerte sur les bornes des paliers |

**Bilan : aucune incohérence bloquante — 6 micro-points cosmétiques/à préciser.**

---

## VOLET 4 — RISQUES RÉSIDUELS

| Risque | Gravité | Garde-fou (déjà prévu ou à prévoir) |
| --- | --- | --- |
| Hallucination des règles officielles (7 paliers horaires, décisions) | MOYENNE | §0.5 « paramétrable par école » = défauts modifiables ; sourcer les valeurs par défaut avant B4c |
| Moteurs bulletins (4) : complexité de calcul | MOYENNE | classes PHP pures + jeu d'essai par cycle (critères B4a) |
| B4b argent (split auto, I1-I5) | ÉLEVÉE | **prérequis : déposer la ROADMAP finance avant B4b** + T1-T16 automatisés |
| B5b génération EDT | MOYENNE | porter emploi.php + édition manuelle secours (garde-fou inscrit) |
| B9a shadcn (différé) | FAIBLE maintenant | réévaluation fin Phase 1 |
| Phase 7 offline (différée) | FAIBLE maintenant | découplée du pilote |

**Aucun risque bloquant pour B3.1/B4.0/B4a.**

---

## VOLET 5 — VERDICT FINAL

| Phase | Verdict | Motif |
| --- | --- | --- |
| 0 — Socle | 🟢 VERT | B3.1 trivial, critère clair (F5 ne déconnecte plus) |
| 1 — Cœur pédago/financier | 🟢 VERT* | B4.0/B4a/c/d/e OK ; *B4b sous condition finance doc |
| 2 — Périphériques pédago | 🟢 VERT | borné, portage encadré |
| 3 — Modules admin/commerciaux | 🟢 VERT | borné |
| 4 — Documents/comm/médias | 🟢 VERT | borné |
| 5 — Rapports/journal/admin | 🟢 VERT | faible risque |
| 6 — UI/UX | 🟠 ORANGE | B9a shadcn — différé, réévalué fin Phase 1 |
| 7 — Offline/WAN | 🟠 ORANGE | différé après pilote (décision saine) |
| 8 — Recette/déploiement | 🟢 VERT | dépend de l'amont |

**VERDICT GLOBAL : 🟢 FEU VERT.**

La v8 est **solide, cohérente et exhaustive**. Toutes mes remarques v7 sont intégrées,
la règle de portage « extraire la logique, jamais copier le code » (§0.2) est exactement la bonne
méthode, et les décisions de périmètre (shadcn différé, offline différé, APC configurable, seuil
non sourcé retiré) sont toutes pertinentes.

**→ FEU VERT pour lancer B3.1 + B4.0 + B4a immédiatement.**

Une seule condition, qui ne bloque pas B4.0/B4a :
**déposer `docs/ROADMAP_finance_v2.0.md` dans le dépôt avant de lancer B4b.**

Micro-corrections non bloquantes à appliquer en passant (cosmétiques) :
1. Créer explicitement la table `sanctions_absence` dans annuel_006 (B4c).
2. Reformuler §11 « Fournie (à déposer) » → « À déposer — prérequis B4b ». Et « 4 libellés » → « 7 paliers ». Et « 26 fantômes » → aligner sur les 19 nommés.
3. Expliciter dans B4.0 que `id_uuid` est ajouté par ALTER sur les tables B0-B3 existantes.
4. Rattacher `codeparentGen.php` à B7k.

