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
    // type encodes card_type + subtype, type_arg encodes the movement value
    public const CARD_DEFINITIONS = [
        ['type' => 'king',         'type_arg' => 1, 'nbr' => 12],
        ['type' => 'guard_g1',     'type_arg' => 1, 'nbr' => 4],
        ['type' => 'guard_g11',    'type_arg' => 2, 'nbr' => 10],
        ['type' => 'guard_gflank', 'type_arg' => 0, 'nbr' => 2],
        ['type' => 'wizard',       'type_arg' => 1, 'nbr' => 2],
        ['type' => 'wizard',       'type_arg' => 2, 'nbr' => 8],
        ['type' => 'wizard',       'type_arg' => 3, 'nbr' => 2],
        ['type' => 'jester',       'type_arg' => 1, 'nbr' => 1],
        ['type' => 'jester',       'type_arg' => 2, 'nbr' => 3],
        ['type' => 'jester',       'type_arg' => 3, 'nbr' => 4],
        ['type' => 'jester',       'type_arg' => 4, 'nbr' => 3],
        ['type' => 'jester',       'type_arg' => 5, 'nbr' => 1],
        ['type' => 'jester_jM',    'type_arg' => 0, 'nbr' => 2],
    ];

    // Map card_type to base character type (for turn tracking)
    public const CARD_TYPE_TO_BASE = [
        'king' => 'king',
        'guard_g1' => 'guard',
        'guard_g11' => 'guard',
        'guard_gflank' => 'guard',
        'wizard' => 'wizard',
        'jester' => 'jester',
        'jester_jM' => 'jester',
    ];

    public $cards; // Deck component

    public function __construct()
    {
        parent::__construct();
        $this->cards = $this->deckFactory->createDeck('vr_card');
        $this->cards->autoreshuffle = true;
        $this->cards->autoreshuffle_trigger = ['obj' => $this, 'method' => 'deckAutoReshuffle'];
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
        $this->cards->createCards(self::CARD_DEFINITIONS, 'deck');
        $this->cards->shuffle('deck');

        foreach (array_keys($players) as $pid) {
            $this->cards->pickCards(8, 'deck', (int)$pid);
        }
    }

    public function deckAutoReshuffle(): void
    {
        $reshuffles = (int)$this->bga->globals->get('deck_reshuffles');
        $this->bga->globals->set('deck_reshuffles', $reshuffles + 1);
        $this->bga->globals->set('crown_side', self::CROWN_SMALL);
        $this->bga->notify->all('deckReshuffled', clienttranslate('The discard pile is reshuffled into a new draw pile. The Crown is flipped to its small side.'), []);
    }

    public function drawCards(int $playerId, int $count): int
    {
        $reshuffles = (int)$this->bga->globals->get('deck_reshuffles');
        if ($reshuffles >= 2 && $this->cards->countCardInLocation('deck') === 0) {
            return 0;
        }
        $drawn = $this->cards->pickCards($count, 'deck', $playerId);
        return $drawn ? count($drawn) : 0;
    }

    public function getPlayerHand(int $playerId): array
    {
        return $this->cards->getPlayerHand($playerId);
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
        $this->cards->playCard($cardId);
    }

    /**
     * Convert Deck card format to our game format.
     * Deck returns: id, type, type_arg, location, location_arg
     * We need: card_id, card_type (base), card_value, card_subtype
     */
    public static function parseCard(array $card): array
    {
        $deckType = $card['type'];
        $baseType = self::CARD_TYPE_TO_BASE[$deckType] ?? $deckType;
        $subtype = null;
        if (str_contains($deckType, '_')) {
            $subtype = substr($deckType, strpos($deckType, '_') + 1);
        }
        return [
            'card_id' => $card['id'],
            'card_type' => $baseType,
            'card_value' => (int)$card['type_arg'],
            'card_subtype' => $subtype,
            'deck_type' => $deckType, // keep original for reference
        ];
    }

    /**
     * Get player hand in game format (parsed cards indexed by card_id).
     */
    public function getPlayerHandParsed(int $playerId): array
    {
        $raw = $this->cards->getPlayerHand($playerId);
        $result = [];
        foreach ($raw as $card) {
            $parsed = self::parseCard($card);
            $result[$parsed['card_id']] = $parsed;
        }
        return $result;
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
        $result['hand'] = $this->getPlayerHandParsed($currentPlayerId);
        $result['crown_position'] = (int)$this->bga->globals->get('crown_position');
        $result['crown_side'] = (int)$this->bga->globals->get('crown_side');
        $result['deck_count'] = $this->cards->countCardInLocation('deck');
        $result['discard_count'] = $this->cards->countCardInLocation('discard');

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
