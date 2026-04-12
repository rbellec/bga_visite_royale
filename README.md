# Visite Royale — BGA Studio

Implementation of **Royal Visit** (Reiner Knizia, 2 players) on [Board Game Arena](https://boardgamearena.com) Studio.

## The Game

A tug-of-war on a linear board (19 spaces). Five characters — King, 2 Guards, Wizard, Jester — are moved by playing cards. Each player tries to pull the King (or the Crown) into their Castle.

**Win conditions:**
- King enters a Castle
- Crown reaches a Castle
- Deck exhausted twice — King's current side wins

**Turn flow:** play card(s) of one type → move Crown → draw back to 8.

**54 cards:** King (12), Guard (16: 4x g1, 10x g1+1, 2x flank), Wizard (12), Jester (14: values 1-5 + 2x center).

**Powers:**
- Wizard: if between the Guards, summon King or a Guard to his space (instead of playing cards)
- Jester: if between your Castle and the King, Jester cards become wildcards for any character type
- King's Privilege: play 2 King cards to move the entire Court (King + both Guards) one space

## Development

```bash
make check   # PHP lint
make deploy  # Upload to BGA Studio via SCP
```

### Requirements

- BGA Studio account with game registered
- SSH key configured for `GoOn@1.studio.boardgamearena.com:2022`
- Chrome with "Claude in Chrome" extension (for automated testing)

### Project Structure

```
modules/php/Game.php           — main class, constants, DB helpers
modules/php/States/            — state machine (PlayerTurn, MoveCrown, DrawCards, CheckEndGame, NextPlayer, EndScore)
modules/js/Game.js             — ES6 client
dbmodel.sql                    — vr_pieces, vr_cards tables
visiteroyale.css               — board and card styles
DEV_JOURNAL.md                 — development log (for blog article)
bga_initial_code_template/     — original BGA scaffold (read-only)
```

### Testing

Create a hotseat game via BGA Studio lobby, then navigate to:
- `/1/visiteroyale?table=N` — player view
- `/1/visiteroyale?table=N&testuser=PLAYER_ID` — play as other player

## Status

All core mechanics implemented and tested:
- All card types (King, Guard g1/g11/gflank, Wizard, Jester)
- Wizard power (summon King/Guard)
- Court move (2 King cards)
- Win condition (King in castle)
- Full turn cycle (play → crown → draw → next player)

Remaining: UI polish.

## Credits

- Game design: Reiner Knizia
- Publisher: IELLO
- BGA implementation: Raphael Bellec + Claude Code
