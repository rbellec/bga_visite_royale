# Visite Royale — BGA Studio Project

## Game
- **Royal Visit** by Reiner Knizia, 2 players
- BGA Studio: https://studio.boardgamearena.com/studiogame?game=visiteroyale
- BGG: https://boardgamegeek.com/boardgame/22245/royal-visit
- Lobby game ID: 14500

## BGA Framework
- **New framework (2025+)** — namespace `Bga\Games\VisiteRoyale`
- Follow all rules from the `bga-alpha` skill (installed at `~/.claude/skills/bga-alpha/SKILL.md`)
- SFTP user: `GoOn`, host: `1.studio.boardgamearena.com`, port 2022

## Key Conventions
- `initGameStateLabels` in `initTable()`, NOT constructor
- `$this->` not `self::` or `static::` for DB methods (PHP 8.4)
- No `--` comments in `dbmodel.sql`
- Guard `define()` with `defined('X') ||`
- State act methods return next state class
- `modules/php/material.inc.php` is auto-included, never `include()` it

## Deploy & Test
```bash
make check   # PHP lint
make deploy  # Upload to BGA Studio
```
Then use Chrome automation to create hotseat table and test.

## Commits
Regular commits at each milestone. Include co-author line.
Dev journal in `DEV_JOURNAL.md` for blog article.
