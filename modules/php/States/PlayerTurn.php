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
        $playerId = $this->game->getActivePlayerId();
        $hand = $this->game->getPlayerHand((int)$playerId);
        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection((int)$playerId);

        $canUseWizardPower = $this->canUseWizardPower((int)$playerId, $pieces);
        $jesterPowerActive = $this->isJesterPowerActive((int)$playerId, $pieces);

        return [
            'hand' => $hand,
            'pieces' => $pieces,
            'direction' => $direction,
            'canUseWizardPower' => $canUseWizardPower,
            'wizardTargets' => $canUseWizardPower ? $this->getWizardTargets($pieces) : [],
            'jesterPowerActive' => $jesterPowerActive,
        ];
    }

    #[PossibleAction]
    public function actPlayCards(string $cardIdsJson, int $activePlayerId): string
    {
        $cardIds = json_decode($cardIdsJson, true);
        if (!is_array($cardIds) || empty($cardIds)) {
            throw new UserException('You must select at least one card');
        }

        $hand = $this->game->getPlayerHand($activePlayerId);

        foreach ($cardIds as $cardId) {
            if (!isset($hand[$cardId])) {
                throw new UserException('Card not in your hand');
            }
        }

        $firstType = $hand[$cardIds[0]]['card_type'];
        foreach ($cardIds as $cardId) {
            if ($hand[$cardId]['card_type'] !== $firstType) {
                throw new UserException('All cards must be of the same type');
            }
        }

        foreach ($cardIds as $cardId) {
            $this->game->discardCard($cardId);
        }

        $this->game->bga->notify->all('cardsPlayed', clienttranslate('${player_name} plays ${count} card(s)'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'count' => count($cardIds),
        ]);

        return MoveCrown::class;
    }

    #[PossibleAction]
    public function actUseWizardPower(int $targetPieceId, int $activePlayerId): string
    {
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

        return MoveCrown::class;
    }

    private function canUseWizardPower(int $playerId, array $pieces): bool
    {
        $wizardPos = (int)$pieces[Game::CHAR_WIZARD]['position'];
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];

        if ($this->game->isKingBetweenGuards($wizardPos, $g1Pos, $g2Pos)) return true;
        if ($this->game->isKingBetweenGuards($kingPos, $wizardPos, $g2Pos)) return true;
        if ($this->game->isKingBetweenGuards($kingPos, $g1Pos, $wizardPos)) return true;

        return false;
    }

    private function getWizardTargets(array $pieces): array
    {
        $wizardPos = (int)$pieces[Game::CHAR_WIZARD]['position'];
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];

        $targets = [];
        if ($this->game->isKingBetweenGuards($wizardPos, $g1Pos, $g2Pos)) $targets[] = Game::CHAR_KING;
        if ($this->game->isKingBetweenGuards($kingPos, $wizardPos, $g2Pos)) $targets[] = Game::CHAR_GUARD1;
        if ($this->game->isKingBetweenGuards($kingPos, $g1Pos, $wizardPos)) $targets[] = Game::CHAR_GUARD2;

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
        return MoveCrown::class;
    }
}
