# Visite Royale — Journal de Developpement

Ce fichier documente les etapes, difficultes et decisions prises pendant le developpement du jeu Visite Royale sur BGA Studio, en utilisant le workflow bga-alpha de Claude Code.

---

## Etape 1 : Preparation (2026-04-12)

### Ce qui a ete fait
- Verification des acces : BGA Studio (connecte), Chrome automation (fonctionnel), SFTP
- Telechargement du scaffold BGA dans `bga_initial_code_template/`
- Lecture des regles depuis le PDF officiel FR
- Creation du plan d'implementation

### Observations
- Le scaffold BGA genere du code avec `static::DbQuery()` qui est incompatible PHP 8.4 — a corriger
- Le scaffold utilise un namespace PascalCase `Bga\Games\VisiteRoyale` (le skill indique lowercase, mais on suit le scaffold)
- Distribution exacte des 54 cartes non trouvee en ligne — estimee a partir des regles et reviews
- Le PDF des regles n'est pas lisible par les outils de fetch web (format image)

### Difficultes
- Trouver la distribution exacte des cartes : les regles montrent des pictogrammes mais pas de tableau explicite. Distribution fournie par l'utilisateur :
  - Roi : 12 cartes (valeur 1, ou 2 cartes = deplacer la Cour)
  - Gardes : 16 cartes (4x "1", 10x "1+1", 2x resserrer autour du roi)
  - Sorcier : 12 cartes (2x1, 8x2, 2x3)
  - Fou : 14 cartes (1x1, 3x2, 4x3, 3x4, 1x5, 2xM)
  - Total : 54 cartes
  - Note : la distribution entre cartes Garde "1" et "1+1" reste a verifier

### Bug #1 : Constantes globales vs namespace PHP
- `material.inc.php` utilise `define()` qui cree des constantes globales
- Le code dans `namespace Bga\Games\VisiteRoyale` ne les trouve pas sans prefixe `\`
- Solution : prefixer toutes les constantes avec `\` (ex: `\POS_FOUNTAIN`, `\CHAR_KING`)
- Solution finale : utiliser des constantes de classe (`public const` dans Game.php + `Game::CONSTANTE` dans les States)
- Les constantes `define()` dans material.inc.php ne sont pas resolues car le fichier est inclus dans le namespace du jeu
- **A noter pour le skill bga-alpha** : documenter ce piege et recommander les constantes de classe

---

## Etape 2 : Premier lancement reussi (2026-04-12)

### Ce qui a ete fait
- Premiere version complete de Game.php, 6 States (PlayerTurn, MoveCrown, DrawCards, CheckEndGame, NextPlayer, EndScore)
- Game.js avec rendu du plateau, pions, couronne, main de cartes
- dbmodel.sql avec tables vr_pieces et vr_cards
- Makefile fonctionnel (check + deploy)
- Premier lancement hotseat reussi : constructeur JS OK, setup OK, pas d'erreurs

### Difficultes
- 3 tentatives avant le premier lancement reussi
- Bug namespace : les constantes `define()` de material.inc.php ne sont pas visibles dans le namespace du jeu

### Bug #2 : Globals BGA — types int uniquement
- `$this->bga->globals->set('played_type', '')` ne fonctionne pas : les globals via `initGameStateLabels` sont des **int** uniquement
- Consequence : `getArgs()` retourne des valeurs inattendues, toutes les cartes apparaissent disabled
- Solution : utiliser des constantes int (`PLAYED_NONE=0, PLAYED_KING=1, ...`) et des maps de conversion
- **A verifier pour le skill** : documenter que les globals BGA sont strictement int, et recommander des constantes int pour tout tracking d'etat
- **Doc trouvee** : https://en.doc.boardgamearena.com/Main_game_logic:_yourgamename.game.php
  - `$this->bga->globals->set/get` = **any type** (JSON serialized) — systeme moderne
  - `initGameStateLabels` = **int uniquement** — systeme legacy
  - Ce sont **deux systemes distincts** ! Ne pas les melanger.
  - **Pour le skill** : recommander `bga->globals` pour tout, documenter que `initGameStateLabels` est legacy/int-only

### Decouverte : Quit programmatique fonctionne via mainsite.ajaxcall !
- Le skill dit que le quit programmatique est impossible (CSRF)
- En realite, `mainsite.ajaxcall('/table/table/quitgame.html', {table: N, neutralized: true}, mainsite, ok, err)` fonctionne !
- `fetch()` direct echoue (CSRF), mais `mainsite.ajaxcall` ajoute le token automatiquement
- **Pour le skill** : mettre a jour la methode de quit dans le test loop

### Bug #3 : URL de jeu — /tableview vs /1/gamename
- `/tableview?table=N` est la vue spectateur, pas la vue joueur
- Pour jouer, il faut naviguer vers `/1/visiteroyale?table=N`
- Le `game_play_area` n'existe pas en vue spectateur
- **A noter pour le skill** : le test loop doit utiliser le bon URL

---

## Etape 3 : Logique de mouvement (2026-04-12)

### Ce qui a ete fait
- Cartes jouees une par une (flow conforme aux regles)
- Logique de mouvement pour Roi, Sorcier, Fou, Gardes
- Validation des mouvements (Roi entre Gardes, limites du plateau)
- UI de choix pour les cartes Garde (quel garde, ou les deux)
- Pouvoir du Sorcier (attirer Roi/Garde)
- Privilege du Roi (2 cartes = deplacer la Cour)
- Tracking du type joue ce tour (pour forcer meme type)

### Bug #4 : Cartes toutes disabled au chargement
- `setup()` appelait `renderHand(hand, {})` avec un objet vide pour playableCards
- Le framework appelle `setup()` avant `onEnteringState()`, donc la main se rend sans info de jouabilite
- Solution : lire `gamedatas.gamestate.args.playableCards` dans `setup()` pour le rendu initial
- Note : `onEnteringState` n'est pas rappele apres `setup()` — il faut que `setup()` fasse le rendu complet

### Bug #5 : C'est pas mon tour !
- Le premier joueur est celui avec le Sorcier dans son Duche (regle du jeu)
- En hotseat, le Player2 (vert) avait le sorcier → c'etait son tour, pas le notre
- Solution : naviguer avec `?testuser=ID` pour jouer en tant que l'autre joueur

### Premier tour joue avec succes !
- Carte Sorcier (valeur 2) jouee par Player2 (vert, direction -1)
- Sorcier deplace de position 8 → 6 (2 cases vers le vert) ✓
- Main passe de 8 a 7 cartes ✓
- Seules les cartes Sorcier restent jouables (meme type requis) ✓
- Bouton "End turn" apparait ✓
- Fin de tour → couronne → pioche → joueur suivant ✓
- **Le cycle complet de jeu fonctionne !**

---

## Resume des ameliorations pour le skill bga-alpha

1. **Constantes** : recommander `public const` dans Game.php au lieu de `define()` dans material.inc.php (probleme namespace)
2. **Globals** : `bga->globals->set/get` (any type, JSON) au lieu de `initGameStateLabels` (legacy, int-only). Ce sont deux systemes distincts.
3. **Quit programmatique** : `mainsite.ajaxcall('/table/table/quitgame.html', {table: N, neutralized: true}, mainsite, ok, err)` fonctionne ! Mettre a jour le test loop.
4. **URL de jeu** : `/1/GAMENAME?table=N` pour la vue joueur, `/tableview?table=N` pour spectateur
5. **setup() et onEnteringState()** : le framework n'appelle pas `onEnteringState` apres `setup()` — il faut rendre l'etat complet dans `setup()` en lisant `gamedatas.gamestate.args`
6. **Namespace PascalCase** : le scaffold genere `Bga\Games\VisiteRoyale` (PascalCase), pas lowercase comme le skill l'indique

---

## Etape 4 : Tests des types de cartes (2026-04-12)

### Ce qui a ete teste
- **Carte King** : Roi pos 9→10 (+1 direction rouge) ✓
- **Carte Guard g1** : Guard1 pos 7→6 (-1 direction verte), choix de garde fonctionne ✓
- **Carte Guard gflank** : Gardes repositionnes autour du Roi (King±1) ✓
- **Carte Guard g11** : correctement desactivee quand mouvement invalide ✓
- **Carte Jester** : pos 10→14→16 (cumul de 2 cartes meme type) ✓, puis 16→13 (direction verte) ✓
- **Carte Wizard** : pos 6→4 (direction verte) ✓
- **Cumul de cartes** : jouer plusieurs cartes du meme type en un tour fonctionne ✓
- **End turn** : transition vers MoveCrown → DrawCards → NextPlayer → PlayerTurn ✓

### Bug #6 : Pouvoir du Sorcier — condition incorrecte
- `canUseWizardPower()` verifiait 3 conditions (`isKingBetweenGuards(w,g1,g2)`, etc.)
- La regle dit : le Sorcier peut utiliser son pouvoir quand il est **strictement entre les 2 Gardes** (dans la Cour)
- Ensuite il attire Roi ou un Garde sur sa case, si la contrainte Roi-entre-Gardes est respectee apres le mouvement
- L'ancienne logique permettait d'utiliser le pouvoir quand le Sorcier etait hors de la Cour
- Solution : verifier d'abord que `w > min(g1,g2) && w < max(g1,g2)`, puis filtrer les cibles valides
- **Pour le skill** : bien comprendre les regles avant d'implementer les conditions de pouvoir

### Bug #7 : Carte Guard 1+1 — mauvaise interpretation
- `actPlayGuardChoice` traitait g11 comme "un garde bouge de 2 cases" (`$steps = ($subtype === 'g11') ? 2 : 1`)
- La regle dit : une carte 1+1 oblige a deplacer **les deux gardes** de 1 case chacun
- Il n'y a pas d'option "un seul garde" pour une carte 1+1
- Solution : g11 appelle directement `actPlayGuardBoth`, pas `actPlayGuardChoice`
- Le JS a ete mis a jour pour ne plus afficher le choix individuel pour les cartes g11
- `canPlayCard` corrige : g11 ne teste plus l'option "un garde de 2", seulement "les deux de 1"

### Observations
- La navigation entre joueurs (testuser=) fonctionne bien pour le hotseat
- Le quit programmatique (`gameui.ajaxcall` depuis la page de jeu) fonctionne aussi
- Le zombie handler gere automatiquement les tours du joueur qui a quitte

### Decouverte : createTable API a change
- L'endpoint `/lobby/lobby/createTable.html` retourne 404 sur BGA Studio
- L'endpoint `/table/table/joingamealivetable.html` aussi 404
- `window.confirm` est utilise par "Express start" — il faut l'overrider pour l'automatiser
- **Pour le skill** : utiliser le bouton "Play with friends" depuis `/lobby?game=GAMEID` + "Express start" avec `window.confirm = () => true`

---

## Etape 5 : Test du pouvoir du Sorcier (2026-04-12)

### Ce qui a ete teste
- Nouvelle table #877402 avec positions initiales : Guard1=7, Jester=8, King=9, Wizard=10, Guard2=11
- **Pouvoir du Sorcier** : Wizard entre les Gardes (pos 10, entre 7 et 11) ✓
  - Cibles proposees : "Summon King" et "Summon Guard 2" ✓
  - Guard1 pas propose (deplacement rendrait Roi hors des gardes) ✓
  - Invocation du Roi (pos 9→10) reussie ✓
  - Le pouvoir termine automatiquement le tour (→ MoveCrown → DrawCards → NextPlayer) ✓
- **Fix Wizard Power confirme** : sur l'ancienne table, Wizard hors Cour → bouton absent ✓

### Bilan de tous les tests
- Cartes : King ✓, Guard g1 ✓, Guard g11 ✓, Guard gflank ✓, Wizard ✓, Jester ✓
- Cumul de cartes meme type ✓
- Pouvoir du Sorcier ✓
- Flow complet du tour (jouer → fin → couronne → pioche → joueur suivant) ✓

---

## Prochaines etapes

- Tester le mouvement de la Cour (2 cartes Roi)
- Verifier la victoire (Roi/Couronne dans chateau)
- Tester le pouvoir du Fou (cartes joker)
- Ameliorer l'UI
