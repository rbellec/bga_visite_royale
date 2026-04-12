<?php
declare(strict_types=1);

/**
 * material.inc.php — Visite Royale constants and static data
 * Auto-included by the BGA framework. Never include() manually.
 *
 * NOTE: This file is included inside the game's namespace context.
 * All constant references must use \ prefix for global constants.
 */

// ── Board layout ──────────────────────────────────────────────
// Linear board: positions 0..18
// 0,1 = Green Castle | 2..8 = Green Duchy | 9 = Fountain | 10..16 = Red Duchy | 17,18 = Red Castle
defined('POS_GREEN_CASTLE_MIN') || define('POS_GREEN_CASTLE_MIN', 0);
defined('POS_GREEN_CASTLE_MAX') || define('POS_GREEN_CASTLE_MAX', 1);
defined('POS_GREEN_DUCHY_MIN')  || define('POS_GREEN_DUCHY_MIN', 2);
defined('POS_GREEN_DUCHY_MAX')  || define('POS_GREEN_DUCHY_MAX', 8);
defined('POS_FOUNTAIN')         || define('POS_FOUNTAIN', 9);
defined('POS_RED_DUCHY_MIN')    || define('POS_RED_DUCHY_MIN', 10);
defined('POS_RED_DUCHY_MAX')    || define('POS_RED_DUCHY_MAX', 16);
defined('POS_RED_CASTLE_MIN')   || define('POS_RED_CASTLE_MIN', 17);
defined('POS_RED_CASTLE_MAX')   || define('POS_RED_CASTLE_MAX', 18);

defined('POS_MIN') || define('POS_MIN', 0);
defined('POS_MAX') || define('POS_MAX', 18);

// ── Character IDs ─────────────────────────────────────────────
defined('CHAR_KING')    || define('CHAR_KING', 1);
defined('CHAR_GUARD1')  || define('CHAR_GUARD1', 2);
defined('CHAR_GUARD2')  || define('CHAR_GUARD2', 3);
defined('CHAR_WIZARD')  || define('CHAR_WIZARD', 4);
defined('CHAR_JESTER')  || define('CHAR_JESTER', 5);

// ── Initial positions (use literal values to avoid namespace issues) ──
defined('INIT_KING_POS')   || define('INIT_KING_POS', 9);
defined('INIT_GUARD1_POS') || define('INIT_GUARD1_POS', 7);
defined('INIT_GUARD2_POS') || define('INIT_GUARD2_POS', 11);

// ── Card types ────────────────────────────────────────────────
defined('CARD_TYPE_KING')    || define('CARD_TYPE_KING', 'king');
defined('CARD_TYPE_GUARD')   || define('CARD_TYPE_GUARD', 'guard');
defined('CARD_TYPE_WIZARD')  || define('CARD_TYPE_WIZARD', 'wizard');
defined('CARD_TYPE_JESTER')  || define('CARD_TYPE_JESTER', 'jester');

// ── Card subtypes (value on each card) ────────────────────────
defined('GUARD_MOVE_1')       || define('GUARD_MOVE_1', 'g1');
defined('GUARD_MOVE_1_1')     || define('GUARD_MOVE_1_1', 'g11');
defined('GUARD_FLANKING')     || define('GUARD_FLANKING', 'gflank');
defined('JESTER_MIDDLE')      || define('JESTER_MIDDLE', 'jM');

// ── Card deck definition (54 cards total) ─────────────────────
// King: 12, Guard: 16, Wizard: 12, Jester: 14
$this->card_definitions = [
    ['type' => 'king', 'value' => 1, 'count' => 12],

    ['type' => 'guard', 'value' => 1, 'subtype' => 'g1', 'count' => 4],
    ['type' => 'guard', 'value' => 2, 'subtype' => 'g11', 'count' => 10],
    ['type' => 'guard', 'value' => 0, 'subtype' => 'gflank', 'count' => 2],

    ['type' => 'wizard', 'value' => 1, 'count' => 2],
    ['type' => 'wizard', 'value' => 2, 'count' => 8],
    ['type' => 'wizard', 'value' => 3, 'count' => 2],

    ['type' => 'jester', 'value' => 1, 'count' => 1],
    ['type' => 'jester', 'value' => 2, 'count' => 3],
    ['type' => 'jester', 'value' => 3, 'count' => 4],
    ['type' => 'jester', 'value' => 4, 'count' => 3],
    ['type' => 'jester', 'value' => 5, 'count' => 1],
    ['type' => 'jester', 'value' => 0, 'subtype' => 'jM', 'count' => 2],
];

// ── Crown ─────────────────────────────────────────────────────
defined('CROWN_BIG')   || define('CROWN_BIG', 1);
defined('CROWN_SMALL') || define('CROWN_SMALL', 2);

// ── Player directions ─────────────────────────────────────────
defined('DIR_GREEN') || define('DIR_GREEN', -1);
defined('DIR_RED')   || define('DIR_RED', 1);

// ── Player colors ─────────────────────────────────────────────
defined('COLOR_GREEN') || define('COLOR_GREEN', '008000');
defined('COLOR_RED')   || define('COLOR_RED', 'ff0000');
