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

import { getPart, placeHtml } from "./Game0Basics";
import { Token, TokenMoveInfo, AnimArgs, TokenDisplayInfo } from "./Game1Tokens";
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
    placeHtml(`<div id="supply_monster" class="supply"></div>`, "limbo");
    placeHtml(`<div id="player_areas"></div>`, "thething");
    const mapWrapper = "map_wrapper";
    placeHtml(`<div id="${mapWrapper}" class="${mapWrapper}"></div>`, "thething");
    this.createMap($(mapWrapper));
    placeHtml(`<div id="timetrack_1"></div>`, mapWrapper);
    placeHtml(`<div id="timetrack_2"></div>`, mapWrapper);
    placeHtml(`<div id="display_monsterturn"></div>`, $("thething"));
    placeHtml(`<div id="deck_monster_yellow" class="deck deck_monster"></div>`, "thething");
    placeHtml(`<div id="deck_monster_red" class="deck deck_monster"></div>`, "thething");

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
      this.updateTooltip(node.id);
    });
  }

  getPlaceRedirect(tokenInfo: Token, args: AnimArgs = {}): TokenMoveInfo {
    const result = tokenInfo as TokenMoveInfo;
    return result;
  }

  updateTokenDisplayInfo(tokenInfo: TokenDisplayInfo) {
    // override to generate dynamic tooltips and such
    const mainType = tokenInfo.mainType;
    const token = $(tokenInfo.tokenId);
    const parentId = token?.parentElement?.id;
    const state = parseInt(token?.dataset.state);
    const tokenId = tokenInfo.tokenId;
    const subType = getPart(tokenId, 1);
    switch (mainType) {
      case "card": {
        if (subType === "monster") {
          const spawnLoc = this.getTokenName(tokenInfo.spawnloc);
          tokenInfo.tooltip = this.ttSection(_("Spawn Location"), spawnLoc);
          if (tokenInfo.ftext) tokenInfo.tooltip += this.ttSection(_("Flavor"), String(tokenInfo.ftext));
        }
        break;
      }
      case "monster": {
        tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction));
        tokenInfo.tooltip += this.ttSection(_("Rank"), String(tokenInfo.rank));
        tokenInfo.tooltip += this.ttSection(_("Strength"), String(tokenInfo.strength));
        tokenInfo.tooltip += this.ttSection(_("Health"), String(tokenInfo.health));
        if (tokenInfo.move) tokenInfo.tooltip += this.ttSection(_("Move"), String(tokenInfo.move));
        if (tokenInfo.armor) tokenInfo.tooltip += this.ttSection(_("Armor"), String(tokenInfo.armor));
        if (tokenInfo.xp) tokenInfo.tooltip += this.ttSection(_("XP"), String(tokenInfo.xp));
        break;
      }
      case "house": {
        tokenInfo.tooltip = this.ttSection(_("Type"), String(tokenInfo.name));
        break;
      }
      case "hex": {
        const q = getPart(tokenId, 1);
        const r = getPart(tokenId, 2);
        if (!r) return;
        //             "hex_9_1" => [
        //         "location" => "map_hexes",
        //         "x" => 9,
        //         "y" => 1,
        //         "terrain" => "forest",
        //         "loc" => "DarkForest",
        //         "c" => "red",
        // ],

        const locname = this.getTokenName(tokenInfo.loc);
        const areacolor = this.getTokenName(tokenInfo.c);
        const terrainname = this.getTokenName(tokenInfo.terrain);
        tokenInfo.name = _("Hex") + ` (${r},${q})`;
        tokenInfo.tooltip = this.ttSection(_("Terrain"), terrainname);
        tokenInfo.tooltip += this.ttSection(_("Coords"), `(${r},${q})`);
        if (tokenInfo.loc) tokenInfo.tooltip += this.ttSection(_("Location"), locname);
        if (tokenInfo.c) tokenInfo.tooltip += this.ttSection(_("Location Color"), areacolor);
      }
    }
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
