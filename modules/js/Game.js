/**
 * Visite Royale — BGA Client (ES6, new framework)
 */

class PlayerTurnState {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        // Update board with latest pieces
        this.game.placePieces(args.pieces);

        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} must play cards or use a power'));
            return;
        }

        const { playedCount, playedType, canEndTurn, playableCards, canUseWizardPower, wizardTargets } = args;

        if (playedCount > 0) {
            this.bga.statusBar.setTitle(_('${you} may play another ${type} card or end your turn').replace('${type}', playedType));
        } else {
            this.bga.statusBar.setTitle(_('${you} must play a card or use a power'));
        }

        // Render hand with playable highlighting
        this.game.renderHand(args.hand, playableCards);

        // Add action buttons
        if (canEndTurn) {
            this.bga.statusBar.addActionButton(_('End turn'), () => {
                this.bga.actions.performAction('actEndTurn');
            }, { color: 'primary' });
        }

        if (canUseWizardPower) {
            this.bga.statusBar.addActionButton(_('Use Wizard Power'), () => {
                this.game.showWizardTargets(wizardTargets);
            }, { color: 'secondary' });
        }
    }

    onLeavingState() {
    }
}

export class Game {
    constructor(bga) {
        console.log('visiteroyale constructor');
        this.bga = bga;
        this.currentGuardCardId = null;

        this.playerTurn = new PlayerTurnState(this, bga);
        this.bga.states.register('PlayerTurn', this.playerTurn);
    }

    setup(gamedatas) {
        console.log('Starting game setup', gamedatas);
        this.gamedatas = gamedatas;

        this.buildBoard();
        this.placePieces(gamedatas.pieces);
        this.placeCrown(gamedatas.crown_position, gamedatas.crown_side);

        // Render hand with playable info from initial state args if available
        const stateArgs = gamedatas.gamestate?.args;
        this.renderHand(gamedatas.hand, stateArgs?.playableCards || {});

        this.setupNotifications();

        console.log('Ending game setup');
    }

    buildBoard() {
        const labels = [];
        for (let i = 0; i <= 18; i++) {
            let label = '';
            if (i <= 1) label = 'C';
            else if (i === 9) label = 'F';
            else if (i >= 17) label = 'C';
            else label = i;
            labels.push(label);
        }

        const boardHtml = `
            <div id="vr-board-container">
                <div id="vr-info-bar">
                    <span id="vr-deck-info">Deck: ${this.gamedatas.deck_count} | Discard: ${this.gamedatas.discard_count}</span>
                </div>
                <div id="vr-crown-track">
                    ${Array.from({length: 19}, (_, i) => {
                        let cls = 'vr-crown-space';
                        if (i <= 1) cls += ' vr-castle-green';
                        else if (i >= 17) cls += ' vr-castle-red';
                        return `<div class="${cls}" data-pos="${i}" id="crown-space-${i}"></div>`;
                    }).join('')}
                </div>
                <div id="vr-board">
                    ${Array.from({length: 19}, (_, i) => {
                        let cls = 'vr-space';
                        if (i <= 1) cls += ' vr-castle vr-castle-green';
                        else if (i <= 8) cls += ' vr-duchy-green';
                        else if (i === 9) cls += ' vr-fountain';
                        else if (i <= 16) cls += ' vr-duchy-red';
                        else cls += ' vr-castle vr-castle-red';
                        return `<div class="${cls}" data-pos="${i}" id="board-space-${i}"><span class="vr-pos-label">${labels[i]}</span></div>`;
                    }).join('')}
                </div>
                <div id="vr-hand-container">
                    <div id="vr-hand"></div>
                </div>
                <div id="vr-guard-choice" style="display:none;">
                    <button id="vr-guard1-btn" class="bgabutton bgabutton_blue">Guard 1 (left)</button>
                    <button id="vr-guard2-btn" class="bgabutton bgabutton_blue">Guard 2 (right)</button>
                    <button id="vr-guard-both-btn" class="bgabutton bgabutton_gray" style="display:none;">Both guards 1 each</button>
                    <button id="vr-guard-cancel-btn" class="bgabutton bgabutton_red">Cancel</button>
                </div>
                <div id="vr-court-move" style="display:none;">
                    <button id="vr-court-btn" class="bgabutton bgabutton_blue">Move entire Court (2 King cards)</button>
                </div>
            </div>
        `;
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', boardHtml);

        // Guard choice handlers
        document.getElementById('vr-guard1-btn').addEventListener('click', () => this.playGuardChoice(2));
        document.getElementById('vr-guard2-btn').addEventListener('click', () => this.playGuardChoice(3));
        document.getElementById('vr-guard-both-btn').addEventListener('click', () => this.playGuardBoth());
        document.getElementById('vr-guard-cancel-btn').addEventListener('click', () => this.hideGuardChoice());
    }

    placePieces(pieces) {
        document.querySelectorAll('.vr-piece').forEach(el => el.remove());

        Object.values(pieces).forEach(piece => {
            const pieceEl = document.createElement('div');
            pieceEl.id = `piece-${piece.piece_id}`;
            pieceEl.className = `vr-piece vr-piece-${piece.piece_type}`;
            pieceEl.textContent = this.getPieceLabel(piece.piece_type);
            pieceEl.title = piece.piece_type;
            const space = document.getElementById(`board-space-${piece.position}`);
            if (space) space.appendChild(pieceEl);
        });
    }

    getPieceLabel(type) {
        const labels = { king: '\u265A', guard1: '\u265C', guard2: '\u265C', wizard: '\u2605', jester: '\u263A' };
        return labels[type] || '?';
    }

    placeCrown(position, side) {
        let crownEl = document.getElementById('vr-crown');
        if (crownEl) crownEl.remove();

        crownEl = document.createElement('div');
        crownEl.id = 'vr-crown';
        crownEl.className = `vr-crown vr-crown-${side === 1 ? 'big' : 'small'}`;
        crownEl.textContent = side === 1 ? '\u2655' : '\u2654';
        const space = document.getElementById(`crown-space-${position}`);
        if (space) space.appendChild(crownEl);
    }

    renderHand(hand, playableCards) {
        const container = document.getElementById('vr-hand');
        if (!container) return;
        container.innerHTML = '';
        const playableIds = playableCards ? Object.keys(playableCards).map(Number) : [];

        Object.values(hand).forEach(card => {
            const cardEl = document.createElement('div');
            const isPlayable = playableIds.includes(parseInt(card.card_id));
            cardEl.className = `vr-card vr-card-${card.card_type}${isPlayable ? '' : ' vr-card-disabled'}`;
            cardEl.dataset.cardId = card.card_id;
            cardEl.dataset.cardType = card.card_type;
            cardEl.dataset.cardSubtype = card.card_subtype || '';

            let label = card.card_type.charAt(0).toUpperCase() + card.card_type.slice(1);
            let valueLabel = card.card_subtype === 'gflank' ? 'Flank' :
                             card.card_subtype === 'jM' ? 'Mid' :
                             card.card_subtype === 'g1' ? '1' :
                             card.card_subtype === 'g11' ? '1+1' :
                             card.card_value;
            cardEl.innerHTML = `<div class="vr-card-type">${label}</div><div class="vr-card-value">${valueLabel}</div>`;

            if (isPlayable) {
                cardEl.addEventListener('click', () => this.onCardClick(card));
            }
            container.appendChild(cardEl);
        });
    }

    onCardClick(card) {
        const type = card.card_type;
        const subtype = card.card_subtype || '';

        if (type === 'guard' && subtype === 'g1') {
            // Show guard choice UI for single guard move
            this.showGuardChoice(card.card_id, subtype);
            return;
        }

        if (type === 'guard' && subtype === 'g11') {
            // Guard 1+1: must move both guards, play directly
            this.bga.actions.performAction('actPlayGuardBoth', { card_id: parseInt(card.card_id) });
            return;
        }

        if (type === 'king') {
            // Check if player wants to do Court move (2 king cards)
            // For now, just play single king card
            this.bga.actions.performAction('actPlayCard', { card_id: parseInt(card.card_id) });
            return;
        }

        // Wizard, Jester, Guard flanking: play directly
        this.bga.actions.performAction('actPlayCard', { card_id: parseInt(card.card_id) });
    }

    showGuardChoice(cardId, subtype) {
        this.currentGuardCardId = cardId;
        const panel = document.getElementById('vr-guard-choice');
        panel.style.display = 'flex';

        const bothBtn = document.getElementById('vr-guard-both-btn');
        bothBtn.style.display = (subtype === 'g11') ? 'inline-block' : 'none';
    }

    hideGuardChoice() {
        document.getElementById('vr-guard-choice').style.display = 'none';
        this.currentGuardCardId = null;
    }

    playGuardChoice(guardId) {
        if (this.currentGuardCardId === null) return;
        this.bga.actions.performAction('actPlayGuardChoice', {
            card_id: parseInt(this.currentGuardCardId),
            guardId: guardId,
        });
        this.hideGuardChoice();
    }

    playGuardBoth() {
        if (this.currentGuardCardId === null) return;
        this.bga.actions.performAction('actPlayGuardBoth', {
            card_id: parseInt(this.currentGuardCardId),
        });
        this.hideGuardChoice();
    }

    showWizardTargets(targets) {
        const labels = { 1: 'King', 2: 'Guard 1', 3: 'Guard 2' };
        targets.forEach(t => {
            this.bga.statusBar.addActionButton(
                _('Summon ${target}').replace('${target}', labels[t] || t),
                () => this.bga.actions.performAction('actUseWizardPower', { targetPieceId: t })
            );
        });
    }

    setupNotifications() {
        this.bga.notifications.setupPromiseNotifications({});
    }

    async notif_cardPlayed(args) {
        this.placePieces(args.pieces);
    }

    async notif_courtMoved(args) {
        this.placePieces(args.pieces);
    }

    async notif_guardMoved(args) {
        this.placePieces(args.pieces);
    }

    async notif_wizardPower(args) {
        this.placePieces(args.pieces);
    }

    async notif_crownMoved(args) {
        this.placeCrown(args.newPosition, args.crown_side);
    }

    async notif_cardsDrawn(args) {
        this.renderHand(args.hand, {});
    }

    async notif_deckReshuffled(args) {
    }

    async notif_deckUpdated(args) {
        const info = document.getElementById('vr-deck-info');
        if (info) info.textContent = `Deck: ${args.deck_count}`;
    }
}
