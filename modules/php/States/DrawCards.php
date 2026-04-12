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

        // Count cards in hand
        $handCount = count($this->game->getPlayerHand($activePlayerId));
        $toDraw = 8 - $handCount;

        if ($toDraw > 0) {
            $drawn = $this->game->drawCards($activePlayerId, $toDraw);
            if ($drawn > 0) {
                $this->game->bga->notify->player($activePlayerId, 'cardsDrawn', clienttranslate('You draw ${count} card(s)'), [
                    'count' => $drawn,
                    'hand' => $this->game->getPlayerHand($activePlayerId),
                ]);
                $this->game->bga->notify->all('deckUpdated', '', [
                    'deck_count' => (int)$this->game->getObjectFromDB("SELECT COUNT(*) AS c FROM vr_cards WHERE card_location='deck'")['c'],
                ]);
            }
        }

        return CheckEndGame::class;
    }
}
