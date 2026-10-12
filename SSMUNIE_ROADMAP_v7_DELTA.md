# SSMUNIE — DELTA v6 → v7 (liste définitive des corrections)

## A. Erreurs factuelles (à corriger absolument)

1. En-tête : « 48 blocs, 11 phases » → **52 blocs, 9 phases**.
   (Recompté bloc par bloc : 5+5+3+9+10+6+6+5+3 = 52 blocs ; 9 phases listées, pas 11.)
2. Récapitulatif : « Total : 48 blocs (dont 4 terminés) » → **52 blocs (dont 4 terminés)**.
3. B4a — titre « Notes + Bulletins (3 moteurs) » → **« (4 moteurs) »**
   (maternel + primaire + collège général + collège technique, cohérent avec §6.7 et SSMUNIE_v5 §2.2).

## B. Réordonnancement (dépendance)

4. **B5b (cours + structures) AVANT B5a (emploi du temps)** — l'emploi du temps consomme
   les `cours` (matière × classe × prof × volume) créés par B5b.

## C. Blocs manquants (à insérer)

5. **B3.2 « Migration de données legacy »** (Phase 1, avant B4a) : import des 7 bases legacy
   (`njikep/njikec/njikect/gsa/charis/innodb/coltest`) → base annuelle `lyc-YYYY`,
   remapping `cycle`→`section`, ajout `annee_scolaire_id`. Sans lui, B4-B7 tournent à vide.
6. **B7k « Portail parent »** : login par `code_parent` + vue des enfants (absences, notes,
   bulletins). Source existante : `src/parent/` (5 fichiers identiques dans les 2 forks),
   aujourd'hui orphelins.
7. **Passage de classe** (`classeSup.php` / `classeSup.js`) : à rattacher explicitement à B4e
   (promotion de masse + nomenclature configurable) — aujourd'hui `promotion.js` est listé
   comme certificat, pas comme passage de classe.

## D. Portes / pré-requis (bloquants avant validation)

8. **ROADMAP finance v2.0** : à fournir avant B4b (T1-T16 et I1-I5 y sont définis).
   Vérifié : le fichier n'existe ni dans `ssm/`, ni `ssmcol/`, ni `ssmunie/`.
9. **Trancher D5** (règles officielles du bulletin primaire APC) et **D8** (rôle de
   `id_eval` vs `m1..m9`) **avant B4a**.
10. **Réintégrer la décision UUID** (SSMUNIE_v5 §2.2 pt13 « UUID partout, y compris FK ») :
    les migrations B4+ doivent porter `id_uuid` dès maintenant, sinon B10 imposera de
    re-migrer toutes les tables B4-B6.

## E. Améliorations structurelles (recommandé)

11. Ajouter un **gabarit « critères d'acceptation » par bloc** (B0=20, B1=30, B2=17, B3=34),
    pour que chaque bloc soit vérifiable.
12. Ajouter une **table « décision → bloc bloqué »** (D5/D8 → B4a, ROADMAP finance → B4b, …).
13. Généraliser la **convention de migration annuelle** `annuel_004/005/…` à tous les blocs
    (aujourd'hui seuls B4a/b/c la mentionnent).
14. Expliciter « **abandonner** » pour `seedcol.php`, `trig.php`, `typeEleve.php`
    (sinon ils restent en zone grise).

## F. Déjà OK (aucune correction)

- Mapping des 20 fonctionnalités de l'audit : complet (20/20).
- Discipline mobile-first (§0.2) : bonne.
- B3.1 (fix fingerprint `::1` vs `127.0.0.1`) : correct et réel (reproduit en B3).
- B7a (EnTeteDocument) en tête de Phase 4 : correct.

