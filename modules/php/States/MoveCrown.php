<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\VisiteRoyale\Game;

class MoveCrown extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 20,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): string
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();
        $pieces = $this->game->getAllPiecePositions();
        $direction = $this->game->getPlayerDirection($activePlayerId);
        $castle = $this->game->getPlayerCastle($activePlayerId);

        $crownMove = 0;

        foreach ($pieces as $piece) {
            $pos = (int)$piece['position'];
            if ($pos >= $castle[0] && $pos <= $castle[1]) {
                $crownMove++;
            }
        }

        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $g1Pos = (int)$pieces[Game::CHAR_GUARD1]['position'];
        $g2Pos = (int)$pieces[Game::CHAR_GUARD2]['position'];

        $duchy = $this->game->getPlayerDuchy($activePlayerId);
        $courtInDuchy = true;
        foreach ([$kingPos, $g1Pos, $g2Pos] as $pos) {
            if ($pos < $duchy[0] || $pos > $castle[1]) {
                $courtInDuchy = false;
                break;
            }
        }
        if ($courtInDuchy) {
            $crownMove++;
        }

        if ($crownMove > 0) {
            $crownPos = (int)$this->game->bga->globals->get('crown_position');
            $newCrownPos = $crownPos + ($direction * $crownMove);
            $newCrownPos = max(Game::POS_MIN, min(Game::POS_MAX, $newCrownPos));

            $this->game->bga->globals->set('crown_position', $newCrownPos);

            $this->game->bga->notify->all('crownMoved', clienttranslate('The Crown moves ${steps} space(s)'), [
                'player_id' => $activePlayerId,
                'steps' => $crownMove,
                'newPosition' => $newCrownPos,
                'crown_side' => (int)$this->game->bga->globals->get('crown_side'),
            ]);
        }

        return DrawCards::class;
    }
}
