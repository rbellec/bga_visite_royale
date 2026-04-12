<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\VisiteRoyale\Game;

class NextPlayer extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 90,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    function onEnteringState(int $activePlayerId): string
    {
        $this->game->giveExtraTime($activePlayerId);
        $this->game->activeNextPlayer();
        return PlayerTurn::class;
    }
}
