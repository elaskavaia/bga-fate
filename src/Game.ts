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

    // create map area: Pointy-top hex grid, hexagonal shape with side length 9.
    // Shifted axial coordinates: center at (9,9), range 1..17. Hex boundary: |q-9| + |r-9| + |q+r-18| <= 16
    // Horizontal rows by r: row pattern 9, 10, 11, ..., 17, ..., 11, 10, 9
    const HEX_SIZE = 50; // must match $hex-size in Game.scss
    const HEX_W = HEX_SIZE * Math.sqrt(3);
    const GRID_N = 8; // hex radius
    const GRID_C = GRID_N + 1; // center offset (9)

    // Pointy-top axial to pixel using zero-based coords for positioning
    // Grid bounds: width = sqrt(3) * size * (2*N + 1), height = size * (3*N + 2)
    const mapW = HEX_W * (2 * GRID_N + 1);
    const mapH = HEX_SIZE * (3 * GRID_N + 2);
    const hexes: string[] = [];

    for (let r = 1; r <= 2 * GRID_N + 1; r++) {
      const r0 = r - GRID_C; // zero-based r for pixel math
      const qMin = Math.max(1, 1 - r0);
      const qMax = Math.min(2 * GRID_N + 1, 2 * GRID_N + 1 - r0);
      for (let q = qMin; q <= qMax; q++) {
        const q0 = q - GRID_C; // zero-based q for pixel math
        const px = HEX_W * GRID_N + HEX_SIZE * (Math.sqrt(3) * q0 + (Math.sqrt(3) / 2) * r0);
        const py = HEX_SIZE * 1.5 * GRID_N + HEX_SIZE * 1.5 * r0;
        const hexId = `hex_${q}_${r}`;
        const terrain = this.getRulesFor(hexId, "terrain", "");
        const loc = this.getRulesFor(hexId, "loc", "");
        hexes.push(
          `<div class="hex terrain_${terrain}" id="${hexId}" style="left:${px}px;top:${py}px;" data-q="${q}" data-r="${r}" data-loc="${loc}"></div>`
        );
      }
    }

    const hexHtml = hexes.join("\n");

    this.bga.gameArea
      .getElement()
      .insertAdjacentHTML("beforeend", `<div id="map_area" style="width:${mapW}px;height:${mapH}px;">${hexHtml}</div>`);

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
