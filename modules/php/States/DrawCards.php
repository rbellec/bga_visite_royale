<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\VisiteRoyale\Game;

class DrawCards extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 30,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): string
    {
        $activePlayerId = (int)$this->game->getActivePlayerId();

        $handCount = $this->game->cards->countCardInLocation('hand', $activePlayerId);
        $toDraw = 8 - $handCount;

        if ($toDraw > 0) {
            $drawn = $this->game->drawCards($activePlayerId, $toDraw);
            if ($drawn > 0) {
                $this->game->bga->notify->player($activePlayerId, 'cardsDrawn', clienttranslate('You draw ${count} card(s)'), [
                    'count' => $drawn,
                    'hand' => $this->game->getPlayerHandParsed($activePlayerId),
                ]);
                $this->game->bga->notify->all('deckUpdated', '', [
                    'deck_count' => $this->game->cards->countCardInLocation('deck'),
                ]);
            }
        }

        return CheckEndGame::class;
    }
}
