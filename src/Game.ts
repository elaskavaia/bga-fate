/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */


class PlayerTurn {
    private game: Game;
    private bga: Bga;

    constructor(game: Game, bga: Bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args: any, isCurrentPlayerActive: boolean) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive ?
            _('${you} must play a card or pass') :
            _('${actplayer} must play a card or pass')
        );

        if (isCurrentPlayerActive) {
            const playableCardsIds = args.playableCardsIds;

            playableCardsIds.forEach(
                (cardId: number) => this.bga.statusBar.addActionButton(_('Play card with id ${card_id}').replace('${card_id}', cardId.toString()), () => this.onCardClick(cardId))
            );

            this.bga.statusBar.addActionButton(_('Pass'), () => this.bga.actions.performAction("actPass"), { color: 'secondary' });
        }
    }

    onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    }

    onPlayerActivationChange(args: any, isCurrentPlayerActive: boolean) {
    }

    onCardClick(card_id: number) {
        console.log( 'onCardClick', card_id );

        this.bga.actions.performAction("actPlayCard", {
            card_id,
        }).then(() =>  {
        });
    }
}

export class Game {
    public bga: Bga;
    private gamedatas!: FateGamedatas;
    private playerTurn: PlayerTurn;

    constructor(bga: Bga) {
        console.log('fate constructor');
        this.bga = bga;

        this.playerTurn = new PlayerTurn(this, bga);
        this.bga.states.register('PlayerTurn', this.playerTurn);
    }

    setup(gamedatas: FateGamedatas) {
        console.log( "Starting game setup" );
        this.gamedatas = gamedatas;

        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="player-tables"></div>
        `);

        Object.values(gamedatas.players).forEach((player: FatePlayer) => {
            const playerId = Number(player.id);
            this.bga.playerPanels.getElement(playerId).insertAdjacentHTML('beforeend', `
                <span id="energy-player-counter-${playerId}"></span> Energy
            `);
            const counter = new ebg.counter();
            counter.create(`energy-player-counter-${playerId}`, {
                value: (player as any).energy,
                playerCounter: 'energy',
                playerId
            });

            document.getElementById('player-tables')!.insertAdjacentHTML('beforeend', `
                <div id="player-table-${playerId}">
                    <strong>${player.name}</strong>
                    <div>Player zone content goes here</div>
                </div>
            `);
        });

        this.setupNotifications();

        console.log( "Ending game setup" );
    }

    setupNotifications() {
        console.log( 'notifications subscriptions setup' );

        this.bga.notifications.setupPromiseNotifications({
        });
    }
}
