<?php
declare(strict_types=1);

namespace Bga\Games\VisiteRoyale\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\VisiteRoyale\Game;

class CheckEndGame extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 40,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): string
    {
        $pieces = $this->game->getAllPiecePositions();
        $kingPos = (int)$pieces[Game::CHAR_KING]['position'];
        $crownPos = (int)$this->game->bga->globals->get('crown_position');

        $players = $this->game->getCollectionFromDb("SELECT player_id, player_color FROM player");

        if ($kingPos <= Game::POS_GREEN_CASTLE_MAX) {
            $this->setWinnerByColor($players, Game::COLOR_GREEN);
            return EndScore::class;
        }
        if ($kingPos >= Game::POS_RED_CASTLE_MIN) {
            $this->setWinnerByColor($players, Game::COLOR_RED);
            return EndScore::class;
        }

        if ($crownPos <= Game::POS_GREEN_CASTLE_MAX) {
            $this->setWinnerByColor($players, Game::COLOR_GREEN);
            return EndScore::class;
        }
        if ($crownPos >= Game::POS_RED_CASTLE_MIN) {
            $this->setWinnerByColor($players, Game::COLOR_RED);
            return EndScore::class;
        }

        $reshuffles = (int)$this->game->bga->globals->get('deck_reshuffles');
        $deckEmpty = $this->game->cards->countCardInLocation('deck') === 0;

        if ($reshuffles >= 2 && $deckEmpty) {
            if ($kingPos < Game::POS_FOUNTAIN) {
                $this->setWinnerByColor($players, Game::COLOR_GREEN);
            } else {
                $this->setWinnerByColor($players, Game::COLOR_RED);
            }
            return EndScore::class;
        }

        return NextPlayer::class;
    }

    private function setWinnerByColor(array $players, string $winColor): void
    {
        foreach ($players as $pid => $pdata) {
            $score = ($pdata['player_color'] === $winColor) ? 1 : 0;
            $this->game->bga->playerScore->set((int)$pid, $score);
        }
    }
}
