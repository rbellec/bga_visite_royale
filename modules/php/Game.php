<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale;

use Bga\Games\VisiteRoyale\States\PlayerTurn;
use Bga\GameFramework\Table;

class Game extends Table
{
    // Board positions (0..18)
    public const POS_GREEN_CASTLE_MIN = 0;
    public const POS_GREEN_CASTLE_MAX = 1;
    public const POS_GREEN_DUCHY_MIN = 2;
    public const POS_GREEN_DUCHY_MAX = 8;
    public const POS_FOUNTAIN = 9;
    public const POS_RED_DUCHY_MIN = 10;
    public const POS_RED_DUCHY_MAX = 16;
    public const POS_RED_CASTLE_MIN = 17;
    public const POS_RED_CASTLE_MAX = 18;
    public const POS_MIN = 0;
    public const POS_MAX = 18;

    // Character piece IDs
    public const CHAR_KING = 1;
    public const CHAR_GUARD1 = 2;
    public const CHAR_GUARD2 = 3;
    public const CHAR_WIZARD = 4;
    public const CHAR_JESTER = 5;

    // Crown sides
    public const CROWN_BIG = 1;
    public const CROWN_SMALL = 2;

    // Player directions and colors
    public const DIR_GREEN = -1;
    public const DIR_RED = 1;
    public const COLOR_GREEN = '008000';
    public const COLOR_RED = 'ff0000';

    // Card deck definition (54 cards total)
    public const CARD_DEFINITIONS = [
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

    public function __construct()
    {
        parent::__construct();
    }

    // Played type constants (for turn tracking)
    public const PLAYED_NONE = 0;
    public const PLAYED_KING = 1;
    public const PLAYED_GUARD = 2;
    public const PLAYED_WIZARD = 3;
    public const PLAYED_JESTER = 4;

    public const TYPE_TO_PLAYED = [
        'king' => self::PLAYED_KING,
        'guard' => self::PLAYED_GUARD,
        'wizard' => self::PLAYED_WIZARD,
        'jester' => self::PLAYED_JESTER,
    ];

    public const PLAYED_TO_TYPE = [
        self::PLAYED_KING => 'king',
        self::PLAYED_GUARD => 'guard',
        self::PLAYED_WIZARD => 'wizard',
        self::PLAYED_JESTER => 'jester',
    ];

    protected function initTable(): void
    {
        // Using $this->bga->globals for all state (supports any type, JSON-serialized)
        // NOT using initGameStateLabels (legacy, int-only)
    }

    protected function setupNewGame($players, $options = []): mixed
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $query_values = [];
        foreach ($players as $player_id => $player) {
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player["player_name"]),
            ]);
        }

        $this->DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        $this->setupPieces();
        $this->setupCards($players);

        $this->bga->globals->set('crown_position', self::POS_FOUNTAIN);
        $this->bga->globals->set('crown_side', self::CROWN_BIG);
        $this->bga->globals->set('deck_reshuffles', 0);
        $this->bga->globals->set('played_type', '');
        $this->bga->globals->set('played_count', 0);

        // First player: the one with the Wizard in their duchy
        $wizardPos = $this->getPiecePosition(self::CHAR_WIZARD);
        $playerColors = $this->getCollectionFromDb("SELECT player_id, player_color FROM player");

        foreach ($playerColors as $pid => $pdata) {
            if ($pdata['player_color'] === self::COLOR_GREEN && $wizardPos < self::POS_FOUNTAIN) {
                $this->gamestate->changeActivePlayer((int)$pid);
                break;
            }
            if ($pdata['player_color'] === self::COLOR_RED && $wizardPos > self::POS_FOUNTAIN) {
                $this->gamestate->changeActivePlayer((int)$pid);
                break;
            }
        }

        return PlayerTurn::class;
    }

    private function setupPieces(): void
    {
        $this->DbQuery("INSERT INTO `vr_pieces` VALUES (1, 'king', " . self::POS_FOUNTAIN . ")");
        $this->DbQuery("INSERT INTO `vr_pieces` VALUES (2, 'guard1', " . (self::POS_FOUNTAIN - 2) . ")");
        $this->DbQuery("INSERT INTO `vr_pieces` VALUES (3, 'guard2', " . (self::POS_FOUNTAIN + 2) . ")");

        $positions = [self::POS_FOUNTAIN - 1, self::POS_FOUNTAIN + 1];
        if (bga_rand(0, 1) === 1) {
            $positions = array_reverse($positions);
        }
        $this->DbQuery("INSERT INTO `vr_pieces` VALUES (4, 'wizard', {$positions[0]})");
        $this->DbQuery("INSERT INTO `vr_pieces` VALUES (5, 'jester', {$positions[1]})");
    }

    private function setupCards(array $players): void
    {
        $order = 0;
        $cards = [];
        foreach (self::CARD_DEFINITIONS as $def) {
            for ($i = 0; $i < $def['count']; $i++) {
                $subtype = $def['subtype'] ?? null;
                $subtypeVal = $subtype !== null ? "'{$subtype}'" : "NULL";
                $cards[] = "('{$def['type']}', {$def['value']}, {$subtypeVal}, 'deck', 0, {$order})";
                $order++;
            }
        }
        $this->DbQuery("INSERT INTO `vr_cards` (`card_type`, `card_value`, `card_subtype`, `card_location`, `card_location_arg`, `card_order`) VALUES " . implode(",", $cards));

        $this->shuffleDeck();

        $playerIds = array_keys($players);
        foreach ($playerIds as $pid) {
            $this->drawCards((int)$pid, 8);
        }
    }

    public function shuffleDeck(): void
    {
        $deckCards = $this->getCollectionFromDb("SELECT card_id FROM vr_cards WHERE card_location='deck'");
        $ids = array_keys($deckCards);
        shuffle($ids);
        foreach ($ids as $i => $id) {
            $this->DbQuery("UPDATE vr_cards SET card_order={$i} WHERE card_id={$id}");
        }
    }

    public function drawCards(int $playerId, int $count): int
    {
        $drawn = 0;
        for ($i = 0; $i < $count; $i++) {
            $card = $this->getObjectFromDB("SELECT card_id FROM vr_cards WHERE card_location='deck' ORDER BY card_order ASC LIMIT 1");
            if ($card === null) {
                $reshuffles = (int)$this->bga->globals->get('deck_reshuffles');
                if ($reshuffles >= 2) {
                    break;
                }
                $this->DbQuery("UPDATE vr_cards SET card_location='deck' WHERE card_location='discard'");
                $this->shuffleDeck();
                $this->bga->globals->set('deck_reshuffles', $reshuffles + 1);
                $this->bga->globals->set('crown_side', self::CROWN_SMALL);

                $this->bga->notify->all('deckReshuffled', clienttranslate('The discard pile is reshuffled into a new draw pile. The Crown is flipped to its small side.'), []);

                $card = $this->getObjectFromDB("SELECT card_id FROM vr_cards WHERE card_location='deck' ORDER BY card_order ASC LIMIT 1");
                if ($card === null) {
                    break;
                }
            }
            $this->DbQuery("UPDATE vr_cards SET card_location='hand', card_location_arg={$playerId} WHERE card_id={$card['card_id']}");
            $drawn++;
        }
        return $drawn;
    }

    public function getPlayerHand(int $playerId): array
    {
        return $this->getCollectionFromDb("SELECT card_id, card_type, card_value, card_subtype FROM vr_cards WHERE card_location='hand' AND card_location_arg={$playerId}");
    }

    public function getPiecePosition(int $pieceId): int
    {
        $row = $this->getObjectFromDB("SELECT position FROM vr_pieces WHERE piece_id={$pieceId}");
        return (int)$row['position'];
    }

    public function getAllPiecePositions(): array
    {
        return $this->getCollectionFromDb("SELECT piece_id, piece_type, position FROM vr_pieces");
    }

    public function movePiece(int $pieceId, int $newPosition): void
    {
        $this->DbQuery("UPDATE vr_pieces SET position={$newPosition} WHERE piece_id={$pieceId}");
    }

    public function discardCard(int $cardId): void
    {
        $this->DbQuery("UPDATE vr_cards SET card_location='discard', card_location_arg=0 WHERE card_id={$cardId}");
    }

    public function getPlayerDirection(int $playerId): int
    {
        $color = $this->getObjectFromDB("SELECT player_color FROM player WHERE player_id={$playerId}");
        return $color['player_color'] === self::COLOR_GREEN ? self::DIR_GREEN : self::DIR_RED;
    }

    public function getPlayerCastle(int $playerId): array
    {
        $dir = $this->getPlayerDirection($playerId);
        if ($dir === self::DIR_GREEN) {
            return [self::POS_GREEN_CASTLE_MIN, self::POS_GREEN_CASTLE_MAX];
        }
        return [self::POS_RED_CASTLE_MIN, self::POS_RED_CASTLE_MAX];
    }

    public function getPlayerDuchy(int $playerId): array
    {
        $dir = $this->getPlayerDirection($playerId);
        if ($dir === self::DIR_GREEN) {
            return [self::POS_GREEN_DUCHY_MIN, self::POS_GREEN_DUCHY_MAX];
        }
        return [self::POS_RED_DUCHY_MIN, self::POS_RED_DUCHY_MAX];
    }

    public function isInCastle(int $position, int $playerId): bool
    {
        $castle = $this->getPlayerCastle($playerId);
        return $position >= $castle[0] && $position <= $castle[1];
    }

    public function isInDuchy(int $position, int $playerId): bool
    {
        $duchy = $this->getPlayerDuchy($playerId);
        return $position >= $duchy[0] && $position <= $duchy[1];
    }

    public function isKingBetweenGuards(int $kingPos, int $guard1Pos, int $guard2Pos): bool
    {
        $minGuard = min($guard1Pos, $guard2Pos);
        $maxGuard = max($guard1Pos, $guard2Pos);
        return $kingPos > $minGuard && $kingPos < $maxGuard && $kingPos !== $guard1Pos && $kingPos !== $guard2Pos;
    }

    public function getAllDatas(int $currentPlayerId): array
    {
        $result = [];

        $result['players'] = $this->getCollectionFromDb(
            "SELECT player_id AS id, player_score AS score, player_color AS color FROM player"
        );

        $result['pieces'] = $this->getAllPiecePositions();
        $result['hand'] = $this->getPlayerHand($currentPlayerId);
        $result['crown_position'] = (int)$this->bga->globals->get('crown_position');
        $result['crown_side'] = (int)$this->bga->globals->get('crown_side');
        $result['deck_count'] = (int)$this->getObjectFromDB("SELECT COUNT(*) AS c FROM vr_cards WHERE card_location='deck'")['c'];
        $result['discard_count'] = (int)$this->getObjectFromDB("SELECT COUNT(*) AS c FROM vr_cards WHERE card_location='discard'")['c'];

        return $result;
    }

    public function getGameProgression(): int
    {
        $crownPos = (int)$this->bga->globals->get('crown_position');
        $distFromCenter = abs($crownPos - self::POS_FOUNTAIN);
        $maxDist = self::POS_FOUNTAIN;
        return min(100, (int)($distFromCenter / $maxDist * 100));
    }

    public function upgradeTableDb($from_version)
    {
    }
}
