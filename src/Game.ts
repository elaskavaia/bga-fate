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
  private boardLayout: string = "scale";

  constructor(bga: Bga) {
    super(bga);
    console.log("fate constructor");

    this.playerTurn = new PlayerTurn(this, bga);
    this.bga.states.register("PlayerTurn", this.playerTurn);
  }

  setup(gamedatas: CustomGamedatas) {
    console.log("Starting game setup");
    super.setup(gamedatas);
    placeHtml(
      `<div id="mainarea">
        <div id="thething"></div>
      </div>`,
      this.bga.gameArea.getElement()
    );
    placeHtml(`<div id="limbo"></div>`, this.bga.gameArea.getElement());
    placeHtml(
      `<div id="board_layout_controls" class="board_layout_controls">
        <button id="layout_scale" class="layout_button active">\u2922</button>
        <button id="layout_scroll" class="layout_button">\u2194</button>
      </div>`,
      "limbo"
    );
    placeHtml(`<div id="supply" class="supply"></div>`, "thething");
    placeHtml(`<div id="supply_monster" class="supply"></div>`, "supply");
    placeHtml(`<div id="supply_crystal_green" class="supply"></div>`, "supply");
    placeHtml(`<div id="supply_crystal_red" class="supply"></div>`, "supply");
    placeHtml(`<div id="supply_crystal_yellow" class="supply"></div>`, "supply");
    placeHtml(`<div id="supply_dice" class="supply"></div>`, "supply");
    placeHtml(`<div id="players_panels"></div>`, "thething");
    const mapWrapper = "map_wrapper";
    placeHtml(`<div id="${mapWrapper}" class="map_wrapper"></div>`, "thething");
    this.createMap($(mapWrapper));
    placeHtml(`<div id="timetrack_1" class="timetrack token timetrack_1"></div>`, mapWrapper);
    placeHtml(`<div id="timetrack_2" class="timetrack token timetrack_2"></div>`, mapWrapper);
    placeHtml(`<div id="display_monsterturn"></div>`, "thething");
    placeHtml(`<div id="deck_monster_yellow" class="deck deck_monster"></div>`, "display_monsterturn");
    placeHtml(`<div id="deck_monster_red" class="deck deck_monster"></div>`, "display_monsterturn");

    Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
      const color = player.color;
      const hnoClass = player.heroNo ? `hno_${player.heroNo}` : "";
      placeHtml(
        `<div id="tableau_${color}" class="tableau ${hnoClass}">

        <div id="pboard_${color}" class="pboard">
          <div id="slot_gold_${color}" class="pboard_slot slot_gold"></div>
          <div id="deck_ability_${color}" class="pboard_slot deck deck_ability"></div>
          <div id="deck_equip_${color}" class="pboard_slot deck deck_equip"></div>
          <div id="deck_event_${color}" class="pboard_slot deck deck_event"></div>
          <div id="discard_${color}" class="pboard_slot deck discard"></div>

          <div id="aslot_${color}_actionMove" class="pboard_slot aslot aslot_actionMove"></div>
          <div id="aslot_${color}_actionAttack" class="pboard_slot aslot aslot_actionAttack"></div>
          <div id="aslot_${color}_actionPrepare" class="pboard_slot aslot aslot_actionPrepare"></div>
          <div id="aslot_${color}_actionFocus" class="pboard_slot aslot aslot_actionFocus"></div>
          <div id="aslot_${color}_actionMend" class="pboard_slot aslot aslot_actionMend"></div>
          <div id="aslot_${color}_actionPractice" class="pboard_slot aslot aslot_actionPractice"></div>
                    <div id="aslot_${color}_empty_1" class="pboard_slot aslot aslot_empty"></div>
          <div id="aslot_${color}_empty_2" class="pboard_slot aslot aslot_empty"></div>
           </div>
        <div id="cardsarea_${color}" class="cardsarea"></div>
        </div>`,
        "players_panels"
      );
    });

    this.setupGame(gamedatas);
    this.setupLayoutControls();

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
    const loc = tokenInfo.location;
    const tokenKey = tokenInfo.key;
    // Stack monsters by type in supply: create sub-container per monster type
    if (loc === "supply_monster") {
      const monsterType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1); // e.g. "monster_goblin"
      const subId = "supply_" + monsterType;
      if (!$(subId)) {
        placeHtml(`<div id="${subId}" class="pile_monster ${monsterType}"></div>`, "supply_monster");
      }
      result.location = subId;
    }
    // Redirect gold crystals on tableau to the gold slot on the player board
    if (loc.startsWith("tableau_") && tokenKey.startsWith("crystal_yellow")) {
      const color = loc.substring("tableau_".length);
      result.location = `slot_gold_${color}`;
    }
    // Redirect cards on tableau to the card area
    if (loc.startsWith("tableau_") && tokenKey.startsWith("card_")) {
      const color = loc.substring("tableau_".length);
      result.location = `cardsarea_${color}`;
    }
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
          if (tokenInfo.ftext) tokenInfo.tooltip += this.ttSection(_("Flavor"), tokenInfo.ftext);
        } else if (["hero", "ability", "equip", "event"].includes(subType)) {
          const heroName = this.getTokenName(`hero_${tokenInfo.hno}`);
          tokenInfo.tooltip = this.ttSection(_("Hero"), heroName);
          tokenInfo.tooltip += this.ttSection(_("Type"), this.getTokenName(`ctype_${subType}`));
          if (tokenInfo.quest) tokenInfo.tooltip += this.ttSection(_("Quest"), this.getTr(tokenInfo.quest));
          if (tokenInfo.effect) tokenInfo.tooltip += this.ttSection(_("Effect"), this.getTr(tokenInfo.effect));
          if (tokenInfo.flavour) tokenInfo.tooltip += this.iiSection(this.getTr(tokenInfo.flavour));
        }
        break;
      }
      case "monster": {
        tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction));
        tokenInfo.tooltip += this.ttSection(_("Rank"), tokenInfo.rank);
        tokenInfo.tooltip += this.ttSection(_("Strength"), tokenInfo.strength);
        tokenInfo.tooltip += this.ttSection(_("Health"), tokenInfo.health);
        if (tokenInfo.move) tokenInfo.tooltip += this.ttSection(_("Move"), tokenInfo.move);
        if (tokenInfo.armor) tokenInfo.tooltip += this.ttSection(_("Armor"), tokenInfo.armor);
        if (tokenInfo.xp) tokenInfo.tooltip += this.ttSection(_("XP"), tokenInfo.xp);
        break;
      }
      case "house": {
        tokenInfo.tooltip = this.ttSection(_("Type"), this.getTr(tokenInfo.name));
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

  // --- Layout controls: scale-to-fit vs horizontal scroll ---

  setupLayoutControls() {
    super.setupLocalControls("board_layout_controls");
    const savedLayout = localStorage.getItem("fate_board_layout") || "scale";
    this.boardLayout = savedLayout;
    this.applyBoardLayout();

    $("layout_scale").addEventListener("click", () => this.setBoardLayout("scale"));
    $("layout_scroll").addEventListener("click", () => this.setBoardLayout("scroll"));

    $("layout_scale").title = _("Board Layout: Scale to fit");
    $("layout_scroll").title = _("Board Layout: Horizontal scroll");
  }

  setBoardLayout(layout: string) {
    this.boardLayout = layout;
    localStorage.setItem("fate_board_layout", layout);
    this.applyBoardLayout();
  }

  applyBoardLayout() {
    $("ebd-body").dataset.boardLayout = this.boardLayout;
    this.boundUpdateBoardScale();

    document.querySelectorAll(".layout_button").forEach((btn) => btn.classList.remove("active"));
    $(`layout_${this.boardLayout}`)?.classList.add("active");

    if (this.boardLayout === "scale") {
      window.addEventListener("resize", this.boundUpdateBoardScale);
    } else {
      window.removeEventListener("resize", this.boundUpdateBoardScale);
    }
  }

  private boundUpdateBoardScale = () => {
    this.updateBoardScale($("thething"));
  };

  updateBoardScale(scalecontrol: HTMLElement) {
    const set = this.boardLayout === "scale";
    const parent = scalecontrol.parentElement;

    scalecontrol.style.transform = "none";
    scalecontrol.style.width = "";
    scalecontrol.style.height = "";
    scalecontrol.style.marginBottom = "";
    scalecontrol.style.transformOrigin = "";
    scalecontrol.scrollLeft = 0;
    scalecontrol.dataset.scale = "1";
    parent.scrollLeft = 0;

    if (!set) return;

    const naturalWidth = scalecontrol.scrollWidth;
    const availableWidth = parent.clientWidth;

    let scale = 1;
    if (naturalWidth > availableWidth) {
      scale = availableWidth / naturalWidth;
    }

    this.applyScale(scalecontrol, scale);
  }

  applyScale(scalecontrol: HTMLElement, scale: number) {
    if (Math.abs(scale - 1) < 0.01) return;
    const naturalHeight = scalecontrol.offsetHeight;
    scalecontrol.dataset.scale = String(scale);
    scalecontrol.style.transform = `scale(${scale})`;
    scalecontrol.style.transformOrigin = "top center";
    const reducedHeight = naturalHeight * (1 - scale);
    scalecontrol.style.marginBottom = `-${reducedHeight}px`;
  }
}
