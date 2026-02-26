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

import { placeHtml } from "./Game0Basics";
import { GameMachine } from "./GameMachine";

class PlayerTurn {
  private game: Game;
  private bga: Bga;

  constructor(game: Game, bga: Bga) {
    this.game = game;
    this.bga = bga;
  }

  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    if (args._private) this.game.onEnteringState_PlayerTurn(args._private);
    else this.game.onEnteringState_PlayerTurn(args);
  }

  onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    this.game.onLeavingState("PlayerTurn", args);
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
    placeHtml(`<div id="thething"></div>`, this.bga.gameArea.getElement());
    placeHtml(`<div id="limbo"></div>`, this.bga.gameArea.getElement());
    placeHtml(`<div id="player_areas"></div>`, "thething");
    const mapWrapper = "map_wrapper";
    placeHtml(`<div id="${mapWrapper}" class="${mapWrapper}"></div>`, "thething");
    this.createMap($(mapWrapper));
    placeHtml(`<div id="timetrack_1"></div>`, mapWrapper);
    placeHtml(`<div id="timetrack_2"></div>`, mapWrapper);

    Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
      // template leftovers TODO: remove
      //const playerId = Number(player.id);
      // this.bga.playerPanels.getElement(playerId).insertAdjacentHTML(
      //   "beforeend",
      //   `
      //           <span id="energy-player-counter-${playerId}"></span> Energy
      //       `
      // );
      // const counter = new ebg.counter();
      // counter.create(`energy-player-counter-${playerId}`, {
      //   value: (player as any).energy,
      //   playerCounter: "energy",
      //   playerId
      // });
      placeHtml(
        `<div id="tableau_${player.color}">
                    <strong>${player.name}</strong>
                    <div>Player zone content goes here</div>
                </div>`,
        "player_areas"
      );
    });

    this.setupGame(gamedatas);

    this.setupNotifications();

    console.log("Ending game setup");
  }

  createMap(parent: HTMLElement) {
    // create map area: Pointy-top hex grid, hexagonal shape with side length 9.
    // Shifted axial coordinates: center at (9,9), range 1..17. Hex boundary: |q-9| + |r-9| + |q+r-18| <= 16
    // Horizontal rows by r: row pattern 9, 10, 11, ..., 17, ..., 11, 10, 9
    const GRID_N = 8; // hex radius
    const GRID_C = GRID_N + 1; // center offset (9)
    const COLS = 2 * GRID_N + 1; // 17
    const ROWS = 3 * GRID_N + 2; // 26
    const hexes: string[] = [];

    for (let r = 1; r <= COLS; r++) {
      const r0 = r - GRID_C; // zero-based r
      const qMin = Math.max(1, 1 - r0);
      const qMax = Math.min(COLS, COLS - r0);
      for (let q = qMin; q <= qMax; q++) {
        const q0 = q - GRID_C; // zero-based q
        // Position as % of map_area: px/mapW*100 and py/mapH*100
        const leftPct = ((GRID_N + q0 + r0 / 2) / COLS) * 100;
        const topPct = ((1.5 * (GRID_N + r0)) / ROWS) * 100;
        const hexId = `hex_${q}_${r}`;
        const terrain = this.getRulesFor(hexId, "terrain", "");
        const loc = this.getRulesFor(hexId, "loc", "");
        hexes.push(
          `<div class="hex terrain_${terrain}" id="${hexId}" style="left:${leftPct}%;top:${topPct}%;" data-q="${q}" data-r="${r}" data-loc="${loc}"></div>`
        );
      }
    }

    const hexHtml = hexes.join("\n");

    placeHtml(`<div id="map_area">${hexHtml}</div>`, parent);

    parent.querySelectorAll(".hex").forEach((node: HTMLElement) => {
      this.addListenerWithGuard(node, (e) => this.onToken(e));
    });
  }

  setupNotifications() {
    console.log("notifications subscriptions setup");

    // automatically listen to the notifications, based on the `notif_xxx` function on this class.
    this.bga.notifications.setupPromiseNotifications({
      minDuration: 1,
      minDurationNoText: 1,

      logger: console.log, // show notif debug informations on console. Could be console.warn or any custom debug function (default null = no logs)
      //handlers: [this, this.tokens],
      onStart: (notifName, msg, args) => {
        if (msg) this.setSubPrompt(msg, args);
      }
      // onEnd: (notifName, msg, args) => this.setSubPrompt("", args)
    });
  }

  async notif_tokenMoved(args: any) {
    return super.notif_tokenMoved(args);
  }

  async notif_counter(args: any) {
    return super.notif_counter(args);
  }

  async notif_message(args: any) {
    //console.log("notif", args);
    return gameui.wait(1);
  }

  async notif_undoMove(args: any) {
    console.log("notif", args);
    return gameui.wait(1);
  }

  async notif_lastTurn(args: any) {
    //this.gamedatas.lastTurn = true;
    //this.updateBanner();
  }
}
