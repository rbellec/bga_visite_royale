/**
 * Visite Royale — BGA Client (ES6, new framework)
 */

class PlayerTurnState {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive ?
            _('${you} must play cards or use a power') :
            _('${actplayer} must play cards or use a power')
        );

        if (isCurrentPlayerActive) {
            this.game.selectedCards = [];
            this.game.enableCardSelection(args);

            if (args.canUseWizardPower) {
                this.bga.statusBar.addActionButton(
                    _('Use Wizard Power'),
                    () => this.game.showWizardTargets(args.wizardTargets),
                    { color: 'secondary' }
                );
            }

            this.bga.statusBar.addActionButton(
                _('Play selected cards'),
                () => this.game.playSelectedCards(),
                { id: 'btn_play', color: 'primary' }
            );
        }
    }

    onLeavingState() {
        this.game.clearSelection();
    }
}

export class Game {
    constructor(bga) {
        console.log('visiteroyale constructor');
        this.bga = bga;
        this.selectedCards = [];

        this.playerTurn = new PlayerTurnState(this, bga);
        this.bga.states.register('PlayerTurn', this.playerTurn);
    }

    setup(gamedatas) {
        console.log('Starting game setup', gamedatas);
        this.gamedatas = gamedatas;

        // Build board
        this.buildBoard();

        // Place pieces
        this.placePieces(gamedatas.pieces);

        // Place crown
        this.placeCrown(gamedatas.crown_position, gamedatas.crown_side);

        // Render hand
        this.renderHand(gamedatas.hand);

        // Setup notifications
        this.setupNotifications();

        console.log('Ending game setup');
    }

    buildBoard() {
        const boardHtml = `
            <div id="vr-board-container">
                <div id="vr-crown-track">
                    ${Array.from({length: 19}, (_, i) =>
                        `<div class="vr-crown-space" data-pos="${i}" id="crown-space-${i}"></div>`
                    ).join('')}
                </div>
                <div id="vr-board">
                    ${Array.from({length: 19}, (_, i) => {
                        let cls = 'vr-space';
                        if (i <= 1) cls += ' vr-castle vr-castle-green';
                        else if (i <= 8) cls += ' vr-duchy-green';
                        else if (i === 9) cls += ' vr-fountain';
                        else if (i <= 16) cls += ' vr-duchy-red';
                        else cls += ' vr-castle vr-castle-red';
                        return `<div class="${cls}" data-pos="${i}" id="board-space-${i}"></div>`;
                    }).join('')}
                </div>
                <div id="vr-hand-container">
                    <div id="vr-hand"></div>
                </div>
            </div>
        `;
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', boardHtml);
    }

    placePieces(pieces) {
        // Remove existing piece elements
        document.querySelectorAll('.vr-piece').forEach(el => el.remove());

        Object.values(pieces).forEach(piece => {
            const pieceEl = document.createElement('div');
            pieceEl.id = `piece-${piece.piece_id}`;
            pieceEl.className = `vr-piece vr-piece-${piece.piece_type}`;
            pieceEl.textContent = this.getPieceLabel(piece.piece_type);
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
        if (!crownEl) {
            crownEl = document.createElement('div');
            crownEl.id = 'vr-crown';
            crownEl.className = 'vr-crown';
        }
        crownEl.textContent = side === 1 ? '\u2655' : '\u2654';
        crownEl.className = `vr-crown vr-crown-${side === 1 ? 'big' : 'small'}`;
        const space = document.getElementById(`crown-space-${position}`);
        if (space) space.appendChild(crownEl);
    }

    renderHand(hand) {
        const container = document.getElementById('vr-hand');
        if (!container) return;
        container.innerHTML = '';

        Object.values(hand).forEach(card => {
            const cardEl = document.createElement('div');
            cardEl.className = `vr-card vr-card-${card.card_type}`;
            cardEl.dataset.cardId = card.card_id;
            cardEl.dataset.cardType = card.card_type;

            let label = card.card_type.charAt(0).toUpperCase() + card.card_type.slice(1);
            let valueLabel = card.card_subtype === 'gflank' ? 'F' :
                             card.card_subtype === 'jM' ? 'M' :
                             card.card_value;
            cardEl.textContent = `${label} ${valueLabel}`;

            cardEl.addEventListener('click', () => this.toggleCardSelection(card.card_id, card.card_type));
            container.appendChild(cardEl);
        });
    }

    toggleCardSelection(cardId, cardType) {
        const idx = this.selectedCards.indexOf(cardId);
        if (idx >= 0) {
            this.selectedCards.splice(idx, 1);
        } else {
            // Only allow same type
            if (this.selectedCards.length > 0) {
                const firstEl = document.querySelector(`[data-card-id="${this.selectedCards[0]}"]`);
                if (firstEl && firstEl.dataset.cardType !== cardType) {
                    return; // different type, ignore
                }
            }
            this.selectedCards.push(cardId);
        }
        // Update visual selection
        document.querySelectorAll('.vr-card').forEach(el => {
            el.classList.toggle('vr-selected', this.selectedCards.includes(parseInt(el.dataset.cardId)));
        });
    }

    enableCardSelection(args) {
        // Cards are already clickable from renderHand
    }

    clearSelection() {
        this.selectedCards = [];
        document.querySelectorAll('.vr-card.vr-selected').forEach(el => el.classList.remove('vr-selected'));
    }

    playSelectedCards() {
        if (this.selectedCards.length === 0) return;

        this.bga.actions.performAction('actPlayCards', {
            cardIdsJson: JSON.stringify(this.selectedCards),
        });
    }

    showWizardTargets(targets) {
        if (targets.length === 1) {
            this.bga.actions.performAction('actUseWizardPower', {
                targetPieceId: targets[0],
            });
        } else {
            // Show choice buttons
            targets.forEach(t => {
                const labels = { 1: 'King', 2: 'Guard 1', 3: 'Guard 2' };
                this.bga.statusBar.addActionButton(
                    _('Summon ${target}').replace('${target}', labels[t] || t),
                    () => this.bga.actions.performAction('actUseWizardPower', { targetPieceId: t })
                );
            });
        }
    }

    setupNotifications() {
        this.bga.notifications.setupPromiseNotifications({});
    }

    async notif_cardsPlayed(args) {
        // Refresh pieces display
        // Pieces update will come from state change
    }

    async notif_wizardPower(args) {
        this.placePieces(args.pieces);
    }

    async notif_crownMoved(args) {
        this.placeCrown(args.newPosition, args.crown_side);
    }

    async notif_cardsDrawn(args) {
        this.renderHand(args.hand);
    }

    async notif_deckReshuffled(args) {
        // Visual indicator could be added
    }

    async notif_deckUpdated(args) {
        // Update deck count display if we add one
    }
}
