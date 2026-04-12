<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\VisiteRoyale\Game;

class PlayerTurn extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 10,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function getArgs(): array
    {
        $playerId = (int)$this->game->getActivePlayerId();
        $hand = $this->game->getPlayerHand($playerId);
        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($playerId);

        $playedType = (string)($this->game->bga->globals->get('played_type') ?? '');
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);

        $canUseWizardPower = ($playedCount === 0) && $this->canUseWizardPower($playerId, $pieces);
        $jesterPowerActive = $this->isJesterPowerActive($playerId, $pieces);

        $playableCards = [];
        foreach ($hand as $cardId => $card) {
            if ($playedCount > 0 && $card['card_type'] !== $playedType) {
                continue;
            }
            if ($this->canPlayCard($card, $playerId, $pieces, $direction, $jesterPowerActive)) {
                $playableCards[$cardId] = $card;
            }
        }

        return [
            'hand' => $hand,
            'playableCards' => $playableCards,
            'pieces' => $pieces,
            'direction' => $direction,
            'playedType' => $playedType,
            'playedCount' => $playedCount,
            'canUseWizardPower' => $canUseWizardPower,
            'wizardTargets' => $canUseWizardPower ? $this->getWizardTargets($pieces) : [],
            'jesterPowerActive' => $jesterPowerActive,
            'canEndTurn' => $playedCount > 0,
        ];
    }

    #[PossibleAction]
    public function actPlayCard(int $card_id, int $activePlayerId): string
    {
        $hand = $this->game->getPlayerHand($activePlayerId);
        if (!isset($hand[$card_id])) {
            throw new UserException('Card not in your hand');
        }

        $card = $hand[$card_id];
        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($activePlayerId);
        $playedType = (string)($this->game->bga->globals->get('played_type') ?? '');
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);

        if ($playedCount > 0 && $card['card_type'] !== $playedType) {
            throw new UserException('You must play a card of the same type');
        }

        $jesterPowerActive = $this->isJesterPowerActive($activePlayerId, $pieces);
        if (!$this->canPlayCard($card, $activePlayerId, $pieces, $direction, $jesterPowerActive)) {
            throw new UserException('This card cannot be played right now');
        }

        $this->applyCardMovement($card, $activePlayerId, $pieces, $direction);
        $this->game->discardCard($card_id);

        $this->game->bga->globals->set('played_type', $card['card_type']);
        $this->game->bga->globals->set('played_count', $playedCount + 1);

        $valueName = $this->getCardValueName($card);
        $this->game->bga->notify->all('cardPlayed', clienttranslate('${player_name} plays a ${card_type} card (${value_name})'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'card_type' => $card['card_type'],
            'card_id' => $card_id,
            'value_name' => $valueName,
            'pieces' => $this->game->getAllPiecePositions(),
        ]);

        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayCourtMove(int $card_id1, int $card_id2, int $activePlayerId): string
    {
        $hand = $this->game->getPlayerHand($activePlayerId);
        if (!isset($hand[$card_id1]) || !isset($hand[$card_id2])) {
            throw new UserException('Cards not in your hand');
        }
        if ($hand[$card_id1]['card_type'] !== 'king' || $hand[$card_id2]['card_type'] !== 'king') {
            throw new UserException('Both cards must be King cards');
        }

        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);
        $playedType = (string)($this->game->bga->globals->get('played_type') ?? '');
        if ($playedCount > 0 && $playedType !== 'king') {
            throw new UserException('You must play King cards');
        }

        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($activePlayerId);

        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];

        $newKing = $kingPos + $direction;
        $newG1 = $g1Pos + $direction;
        $newG2 = $g2Pos + $direction;

        if ($newKing < Game::POS_MIN || $newKing > Game::POS_MAX ||
            $newG1 < Game::POS_MIN || $newG1 > Game::POS_MAX ||
            $newG2 < Game::POS_MIN || $newG2 > Game::POS_MAX) {
            throw new UserException('The Court cannot move further');
        }

        $this->game->movePiece(Game::CHAR_KING, $newKing);
        $this->game->movePiece(Game::CHAR_GUARD1, $newG1);
        $this->game->movePiece(Game::CHAR_GUARD2, $newG2);
        $this->game->discardCard($card_id1);
        $this->game->discardCard($card_id2);

        $this->game->bga->globals->set('played_type', 'king');
        $this->game->bga->globals->set('played_count', $playedCount + 2);

        $this->game->bga->notify->all('courtMoved', clienttranslate('${player_name} moves the entire Court one space'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'pieces' => $this->game->getAllPiecePositions(),
        ]);

        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayGuardChoice(int $card_id, int $guardId, int $activePlayerId): string
    {
        $hand = $this->game->getPlayerHand($activePlayerId);
        if (!isset($hand[$card_id])) {
            throw new UserException('Card not in your hand');
        }
        $card = $hand[$card_id];
        if ($card['card_type'] !== 'guard') {
            throw new UserException('Not a Guard card');
        }
        if ($guardId !== Game::CHAR_GUARD1 && $guardId !== Game::CHAR_GUARD2) {
            throw new UserException('Invalid guard');
        }

        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($activePlayerId);
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);
        $subtype = $card['card_subtype'] ?? '';
        $steps = ($subtype === 'g11') ? 2 : 1;

        $guardPos = (int)$pieces[$guardId]['position'];
        $newPos = $guardPos + ($direction * $steps);
        if (!$this->isValidGuardMove($guardId, $newPos, $pieces)) {
            throw new UserException('This guard cannot move there');
        }
        $this->game->movePiece($guardId, $newPos);
        $this->game->discardCard($card_id);

        $this->game->bga->globals->set('played_type', 'guard');
        $this->game->bga->globals->set('played_count', $playedCount + 1);

        $this->game->bga->notify->all('guardMoved', clienttranslate('${player_name} moves a Guard'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'pieces' => $this->game->getAllPiecePositions(),
        ]);

        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayGuardBoth(int $card_id, int $activePlayerId): string
    {
        $hand = $this->game->getPlayerHand($activePlayerId);
        if (!isset($hand[$card_id])) {
            throw new UserException('Card not in your hand');
        }
        $card = $hand[$card_id];
        if ($card['card_type'] !== 'guard' || ($card['card_subtype'] ?? '') !== 'g11') {
            throw new UserException('Not a Guard 1+1 card');
        }

        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($activePlayerId);
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);

        $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $newG1 = $g1Pos + $direction;
        $newG2 = $g2Pos + $direction;

        if ($newG1 < Game::POS_MIN || $newG1 > Game::POS_MAX ||
            $newG2 < Game::POS_MIN || $newG2 > Game::POS_MAX) {
            throw new UserException('Guards cannot move further');
        }
        if (!$this->game->isKingBetweenGuards($kingPos, $newG1, $newG2)) {
            throw new UserException('King must remain between Guards');
        }

        $this->game->movePiece(Game::CHAR_GUARD1, $newG1);
        $this->game->movePiece(Game::CHAR_GUARD2, $newG2);
        $this->game->discardCard($card_id);

        $this->game->bga->globals->set('played_type', 'guard');
        $this->game->bga->globals->set('played_count', $playedCount + 1);

        $this->game->bga->notify->all('guardMoved', clienttranslate('${player_name} moves both Guards one space'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'pieces' => $this->game->getAllPiecePositions(),
        ]);

        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actEndTurn(int $activePlayerId): string
    {
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);
        if ($playedCount === 0) {
            throw new UserException('You must play at least one card or use a power');
        }

        $this->game->bga->globals->set('played_type', '');
        $this->game->bga->globals->set('played_count', 0);

        return MoveCrown::class;
    }

    #[PossibleAction]
    public function actUseWizardPower(int $targetPieceId, int $activePlayerId): string
    {
        $playedCount = (int)($this->game->bga->globals->get('played_count') ?? 0);
        if ($playedCount > 0) {
            throw new UserException('Cannot use Wizard power after playing cards');
        }

        $pieces = $this->game->getAllPiecePositions();
        if (!$this->canUseWizardPower($activePlayerId, $pieces)) {
            throw new UserException('Cannot use Wizard power right now');
        }

        $targets = $this->getWizardTargets($pieces);
        if (!in_array($targetPieceId, $targets)) {
            throw new UserException('Invalid target for Wizard power');
        }

        $wizardPos = (int)$pieces[Game::CHAR_WIZARD]['position'];
        $this->game->movePiece($targetPieceId, $wizardPos);

        $targetName = $pieces[$targetPieceId]['piece_type'];
        $this->game->bga->notify->all('wizardPower', clienttranslate('${player_name} uses Wizard power to summon ${target}'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'target' => $targetName,
            'targetPieceId' => $targetPieceId,
            'newPosition' => $wizardPos,
            'pieces' => $this->game->getAllPiecePositions(),
        ]);

        $this->game->bga->globals->set('played_type', '');
        $this->game->bga->globals->set('played_count', 0);

        return MoveCrown::class;
    }

    // ── Movement logic ────────────────────────────────────────

    private function applyCardMovement(array $card, int $playerId, array $pieces, int $direction): void
    {
        $type = $card['card_type'];
        $value = (int)$card['card_value'];
        $subtype = $card['card_subtype'] ?? '';

        switch ($type) {
            case 'king':
                $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
                $this->game->movePiece(Game::CHAR_KING, $kingPos + $direction);
                break;
            case 'wizard':
                $wizPos = (int)$pieces[Game::CHAR_WIZARD]['position'];
                $this->game->movePiece(Game::CHAR_WIZARD, max(Game::POS_MIN, min(Game::POS_MAX, $wizPos + ($direction * $value))));
                break;
            case 'jester':
                if ($subtype === 'jM') {
                    $this->game->movePiece(Game::CHAR_JESTER, Game::POS_FOUNTAIN);
                } else {
                    $jPos = (int)$pieces[Game::CHAR_JESTER]['position'];
                    $this->game->movePiece(Game::CHAR_JESTER, max(Game::POS_MIN, min(Game::POS_MAX, $jPos + ($direction * $value))));
                }
                break;
            case 'guard':
                if ($subtype === 'gflank') {
                    $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
                    $this->game->movePiece(Game::CHAR_GUARD1, $kingPos - 1);
                    $this->game->movePiece(Game::CHAR_GUARD2, $kingPos + 1);
                }
                break;
        }
    }

    private function canPlayCard(array $card, int $playerId, array $pieces, int $direction, bool $jesterPower): bool
    {
        $type = $card['card_type'];
        $value = (int)$card['card_value'];
        $subtype = $card['card_subtype'] ?? '';

        switch ($type) {
            case 'king':
                $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
                $newPos = $kingPos + $direction;
                if ($newPos < Game::POS_MIN || $newPos > Game::POS_MAX) return false;
                return $this->game->isKingBetweenGuards($newPos, (int)$pieces[Game::CHAR_GUARD1]['position'], (int)$pieces[Game::CHAR_GUARD2]['position']);
            case 'wizard':
                $newPos = (int)$pieces[Game::CHAR_WIZARD]['position'] + ($direction * $value);
                return $newPos >= Game::POS_MIN && $newPos <= Game::POS_MAX;
            case 'jester':
                if ($subtype === 'jM') return true;
                $newPos = (int)$pieces[Game::CHAR_JESTER]['position'] + ($direction * $value);
                return $newPos >= Game::POS_MIN && $newPos <= Game::POS_MAX;
            case 'guard':
                if ($subtype === 'gflank') {
                    $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
                    return ($kingPos - 1) >= Game::POS_MIN && ($kingPos + 1) <= Game::POS_MAX;
                }
                $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
                $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];
                $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
                $steps = ($subtype === 'g11') ? 2 : 1;
                // Can move guard1?
                $newG1 = $g1Pos + ($direction * $steps);
                if ($newG1 >= Game::POS_MIN && $newG1 <= Game::POS_MAX && $newG1 !== $kingPos &&
                    $this->game->isKingBetweenGuards($kingPos, $newG1, $g2Pos)) return true;
                // Can move guard2?
                $newG2 = $g2Pos + ($direction * $steps);
                if ($newG2 >= Game::POS_MIN && $newG2 <= Game::POS_MAX && $newG2 !== $kingPos &&
                    $this->game->isKingBetweenGuards($kingPos, $g1Pos, $newG2)) return true;
                // For g11: both guards 1 each?
                if ($subtype === 'g11') {
                    $nG1 = $g1Pos + $direction;
                    $nG2 = $g2Pos + $direction;
                    if ($nG1 >= Game::POS_MIN && $nG1 <= Game::POS_MAX &&
                        $nG2 >= Game::POS_MIN && $nG2 <= Game::POS_MAX &&
                        $nG1 !== $kingPos && $nG2 !== $kingPos &&
                        $this->game->isKingBetweenGuards($kingPos, $nG1, $nG2)) return true;
                }
                return false;
        }
        return false;
    }

    private function isValidGuardMove(int $guardId, int $newPos, array $pieces): bool
    {
        if ($newPos < Game::POS_MIN || $newPos > Game::POS_MAX) return false;
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        if ($newPos === $kingPos) return false;
        $otherId = ($guardId === Game::CHAR_GUARD1) ? Game::CHAR_GUARD2 : Game::CHAR_GUARD1;
        $otherPos = (int)$pieces[$otherId]['position'];
        return $this->game->isKingBetweenGuards($kingPos,
            ($guardId === Game::CHAR_GUARD1 ? $newPos : $otherPos),
            ($guardId === Game::CHAR_GUARD2 ? $newPos : $otherPos));
    }

    private function getCardValueName(array $card): string
    {
        $subtype = $card['card_subtype'] ?? '';
        if ($subtype === 'gflank') return 'flanking';
        if ($subtype === 'jM') return 'center';
        if ($subtype === 'g1') return '1';
        if ($subtype === 'g11') return '1+1';
        return (string)$card['card_value'];
    }

    // ── Powers ────────────────────────────────────────────────

    private function canUseWizardPower(int $playerId, array $pieces): bool
    {
        $w = (int)$pieces[Game::CHAR_WIZARD]['position'];
        $k = (int)$pieces[Game::CHAR_KING]['position'];
        $g1 = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2 = (int)$pieces[Game::CHAR_GUARD2]['position'];
        return $this->game->isKingBetweenGuards($w, $g1, $g2)
            || $this->game->isKingBetweenGuards($k, $w, $g2)
            || $this->game->isKingBetweenGuards($k, $g1, $w);
    }

    private function getWizardTargets(array $pieces): array
    {
        $w = (int)$pieces[Game::CHAR_WIZARD]['position'];
        $k = (int)$pieces[Game::CHAR_KING]['position'];
        $g1 = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2 = (int)$pieces[Game::CHAR_GUARD2]['position'];
        $targets = [];
        if ($this->game->isKingBetweenGuards($w, $g1, $g2)) $targets[] = Game::CHAR_KING;
        if ($this->game->isKingBetweenGuards($k, $w, $g2)) $targets[] = Game::CHAR_GUARD1;
        if ($this->game->isKingBetweenGuards($k, $g1, $w)) $targets[] = Game::CHAR_GUARD2;
        return $targets;
    }

    private function isJesterPowerActive(int $playerId, array $pieces): bool
    {
        $jesterPos = (int)$pieces[Game::CHAR_JESTER]['position'];
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $castle = $this->game->getPlayerCastle($playerId);
        $direction = $this->game->getPlayerDirection($playerId);
        if ($direction === Game::DIR_GREEN) {
            return $jesterPos < $kingPos && $jesterPos >= $castle[0];
        } else {
            return $jesterPos > $kingPos && $jesterPos <= $castle[1];
        }
    }

    function zombie(int $playerId)
    {
        $this->game->bga->globals->set('played_type', '');
        $this->game->bga->globals->set('played_count', 0);
        return MoveCrown::class;
    }
}
