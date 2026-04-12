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

### Prochaines etapes
- Tester un tour complet (jouer carte, fin de tour, tour suivant)
- Implementer le pouvoir du Fou (cartes Joker)
- Ameliorer l'interface visuelle
