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

import { GameMachine } from "./GameMachine";

class PlayerTurn {
  private game: Game;
  private bga: Bga;

  constructor(game: Game, bga: Bga) {
    this.game = game;
    this.bga = bga;
  }

  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    this.game.onEnteringState_PlayerTurn(args);
  }

  onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    this.game.onLeavingState(args);
  }

  onPlayerActivationChange(args: any, isCurrentPlayerActive: boolean) {}
}

export class Game extends GameMachine {
  private playerTurn: PlayerTurn;

  constructor(bga: Bga) {
    super(bga);
    console.log("fate constructor");

    this.playerTurn = new PlayerTurn(this, bga);
    this.bga.states.register("PlayerTurn", this.playerTurn);
  }

  setup(gamedatas: CustomGamedatas) {
    console.log("Starting game setup");
    super.setup(gamedatas);

    this.bga.gameArea.getElement().insertAdjacentHTML(
      "beforeend",
      `
            <div id="player-tables"></div>
        `
    );

    Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
      const playerId = Number(player.id);
      this.bga.playerPanels.getElement(playerId).insertAdjacentHTML(
        "beforeend",
        `
                <span id="energy-player-counter-${playerId}"></span> Energy
            `
      );
      const counter = new ebg.counter();
      counter.create(`energy-player-counter-${playerId}`, {
        value: (player as any).energy,
        playerCounter: "energy",
        playerId
      });

      document.getElementById("player-tables")!.insertAdjacentHTML(
        "beforeend",
        `
                <div id="player-table-${playerId}">
                    <strong>${player.name}</strong>
                    <div>Player zone content goes here</div>
                </div>
            `
      );
    });

    this.setupNotifications();

    console.log("Ending game setup");
  }

  setupNotifications() {
    console.log("notifications subscriptions setup");

    this.bga.notifications.setupPromiseNotifications({});
  }
}
