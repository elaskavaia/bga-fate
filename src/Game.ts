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
import { Game1Tokens, Token, TokenMoveInfo, AnimArgs, TokenDisplayInfo } from "./Game1Tokens";
import { PlayerTurn } from "./PlayerTurn";
import { CustomGamedatas, CustomPlayer } from "./types";

export class Game extends Game1Tokens {
  private playerTurn: PlayerTurn;
  private boardLayout: string = "scale";

  constructor(bga: Bga) {
    super(bga);
    //console.log("fate constructor");

    this.playerTurn = new PlayerTurn(this, bga);
    this.bga.states.register("PlayerTurn", this.playerTurn);
  }

  onToken(e: Event) {
    // TODO: pick proper state object
    this.playerTurn.onToken(e);
  }

  setup(gamedatas: CustomGamedatas) {
    console.log("Starting game setup");
    super.setup(gamedatas);
    placeHtml(
      `
      <div id='selection_area' class='selection_area'></div>
      <div id="mainarea">
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
    // Players panels (left side in wide layout, top in narrow)
    placeHtml(`<div id="players_panels"></div>`, "thething");
    // Board area: map + monster turn display + supply (right side in wide layout)
    const mapWrapper = "map_wrapper";
    placeHtml(
      `<div id="board_area">
      <div id="display_battle"></div>
      <div id="${mapWrapper}" class="map_wrapper"></div>
      <div id="display_monsterturn">
        <div id="deck_monster_yellow" class="deck deck_monster"></div>
        <div id="deck_monster_red" class="deck deck_monster"></div>
      </div>
      <div id="gsupply" class="gsupply">
        <div id="supply_monster" class="supply"></div>
        <div id="supply_crystal_green" class="supply bucket_crystal_green"></div>
        <div id="supply_crystal_red" class="supply bucket_crystal_red"></div>
        <div id="supply_crystal_yellow" class="supply bucket_crystal_yellow"></div>
        <div id="supply_die_attack" class="supply"></div>
        <div id="supply_die_monster" class="supply"></div>
      </div>
      <div id="timetrack_area"></div>
      </div>`,
      "thething"
    );

    this.createMap($(mapWrapper));

    Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
      const color = player.color;
      const hnoClass = player.heroNo ? `hno_${player.heroNo}` : "";
      placeHtml(`<div id="tableau_${color}" class="tableau ${hnoClass}"></div`, "players_panels");
      ["deck_ability", "deck_equip", "deck_event", "discard"].forEach((d) => {
        const name = this.getRulesFor(d, "name");
        placeHtml(
          `<div class="deck_wrapper" data-name="${name}"><div id="${d}_${color}" class="deck ${d}"></div></div>`,
          `tableau_${color}`
        );
      });
      placeHtml(
        `<div id="miniboard_${color}" class="miniboard">
                  <div id="bucket_crystal_yellow_tableau_${color}" class="pboard_slot bucket bucket_crystal_yellow"></div>
        </div>`,
        this.bga.playerPanels.getElement(Number(player.id))
      );
      placeHtml(
        `
        <div id="pboard_${color}" class="pboard">
          <div id="aslot_${color}_actionMove" class="pboard_slot aslot aslot_actionMove"></div>
          <div id="aslot_${color}_actionAttack" class="pboard_slot aslot aslot_actionAttack"></div>
          <div id="aslot_${color}_actionPrepare" class="pboard_slot aslot aslot_actionPrepare"></div>
          <div id="aslot_${color}_actionFocus" class="pboard_slot aslot aslot_actionFocus"></div>
          <div id="aslot_${color}_actionMend" class="pboard_slot aslot aslot_actionMend"></div>
          <div id="aslot_${color}_actionPractice" class="pboard_slot aslot aslot_actionPractice"></div>
          <div id="aslot_${color}_empty_1" class="pboard_slot aslot aslot_empty"></div>
          <div id="aslot_${color}_empty_2" class="pboard_slot aslot aslot_empty"></div>
        </div>`,
        `limbo`
      );
    });

    // Create hand container for current player only (not spectators)
    if (!this.bga.players.isCurrentPlayerSpectator()) {
      const myColor = this.player_color;
      const name = _("Hand (Events)");
      placeHtml(
        `<div class="hand_wrapper" data-name="${name}"><div id="hand_${myColor}" class="hand"></div></div>`,
        `tableau_${myColor}`,
        "afterbegin"
      );
    }

    this.setupTokens(gamedatas);
    this.setupLayoutControls();

    this.setupNotifications();

    Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
      const color = player.color;
      // attach hand counter to miniboard
      $(`miniboard_${color}`).appendChild($(`counter_hand_${color}`));
      $(`counter_hand_${color}`).classList.add("counter_hand");
      //"\f256"
    });

    console.log("Ending game setup");
  }

  /** Populate timetrack slots inside the timetrack container (created by token system). */
  createTimetrack(trackId: string) {
    // Build slots programmatically from material data
    for (let step = 1; step <= 20; step++) {
      const slotId = `slot_${trackId}_${step}`;
      if ($(slotId)) continue;
      const spotType = this.getRulesFor(slotId, "r", null);
      if (spotType === null) break;

      const phase = spotType.startsWith("tm_yellow") ? "tm_phase_yellow" : "tm_phase_red";
      placeHtml(`<div id="${slotId}" class="tt_slot ${spotType} ${phase}" data-step="${step}"></div>`, trackId);
      this.updateTooltip(slotId);
    }
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
    const result = { ...tokenInfo } as TokenMoveInfo;
    const loc = tokenInfo.location;
    const tokenKey = tokenInfo.key;

    // Redirect tracker tokens to miniboard in player panel
    if (tokenKey.startsWith("tracker_") && loc.startsWith("tableau_")) {
      const color = loc.replace("tableau_", "");
      result.location = `miniboard_${color}`;
      result.noa = true;
    }

    // Stack monsters by type in supply: create sub-container per monster type
    if (loc === "supply_monster") {
      const monsterType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1); // e.g. "monster_goblin"
      if (monsterType === "monster_legend") {
        // Legends: place directly on map_wrapper, no piling
        result.location = "map_wrapper";
      } else {
        const subId = "supply_" + monsterType;
        if (!$(subId)) {
          placeHtml(`<div id="${subId}" class="pile_monster ${monsterType}"></div>`, "map_wrapper");
        }
        result.location = subId;
      }
      // Shrink & fade at current position, then snap to supply
      if ($(tokenKey)?.parentElement?.id?.startsWith("hex_")) {
        result.noa = true;
        result.onStart = async (node) => {
          await this.animationLa.shrinkAndFade(node);
        };
      }
    } else if (loc.startsWith("hand_") && tokenKey.startsWith("card_")) {
      // Cards in hand need click handlers for discard selection
      result.onClick = (e) => this.onToken(e);
    } else if (loc.startsWith("tableau_") && tokenKey.startsWith("card_")) {
      result.onClick = (e) => this.onToken(e);
    } else if (tokenKey.startsWith("crystal_")) {
      // Bucket redirect: tokens placed on another token get a sub-container bucket
      // e.g. crystal_red on monster_goblin_1 → bucket_crystal_red_monster_goblin_1
      const bucketType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1); // e.g. "crystal_red"
      const tokenNode = $(tokenKey);
      const oldBucket = tokenNode?.parentElement;
      const oldBucketId = oldBucket?.classList.contains("bucket") ? oldBucket.id : null;

      if (!loc.startsWith("supply")) {
        const bucketId = `bucket_${bucketType}_${loc}`;
        if (!$(bucketId)) {
          placeHtml(`<div id="${bucketId}" class="bucket bucket_${bucketType}"></div>`, loc);
        }
        result.location = bucketId;

        // Crystal landing on a monster, hero, or card: suppress slide, pulse the crystal bucket instead
        if (loc.startsWith("monster") || loc.startsWith("hero") || loc.startsWith("card")) {
          result.noa = true;
          result.onEnd = () => {
            if (oldBucketId) this.updateBucketCount(oldBucketId);
            this.updateBucketCount(bucketId);
            this.animationLa.pulse(bucketId);
            this.updateTooltip(loc, undefined, { force: true });
          };
        } else {
          result.onEnd = () => {
            if (oldBucketId) {
              this.updateBucketCount(oldBucketId);
              const oldCharId = oldBucketId.replace(/^bucket_crystal_\w+_/, "");
              if ($(oldCharId)) this.updateTooltip(oldCharId, undefined, { force: true });
            }
            this.updateBucketCount(bucketId);
          };
        }
      } else if (oldBucketId) {
        // Crystal returning to supply — suppress slide, just pulse the old bucket
        const oldCharId = oldBucketId.replace(/^bucket_crystal_\w+_/, "");
        result.noa = true;
        result.onEnd = () => {
          this.updateBucketCount(oldBucketId);
          this.animationLa.pulse(oldBucketId);
          if ($(oldCharId)) this.updateTooltip(oldCharId, undefined, { force: true });
        };
      }
    } else if (tokenKey.startsWith("timetrack_")) {
      // Redirect timetrack container to timetrack_area and populate slots
      result.location = "timetrack_area";
      result.onEnd = () => this.createTimetrack(tokenKey);
    } else if (tokenKey === "rune_stone" && loc.startsWith("timetrack_")) {
      // Redirect rune_stone to the specific timetrack slot based on its state (step number)
      result.location = `slot_${loc}_${tokenInfo.state}`;
    } else if (loc === "display_battle" && tokenKey.startsWith("die_") && args.anim_target) {
      // Dice landing on display_battle: show evaporate effect at the attack target
      const target = args.anim_target;
      result.onEnd = (node) => {
        this.animationLa.evaporate(node, target);
      };
    } else if (tokenKey.startsWith("display_battle")) {
      result.nop = true;
    }

    return result;
  }

  /** Update data-state on a bucket by counting its direct children (excluding other buckets). */
  updateBucketCount(bucketId: string) {
    const bucket = $(bucketId);
    if (bucket) {
      let count = 0;
      for (let i = 0; i < bucket.children.length; i++) {
        if (!bucket.children[i].classList.contains("bucket")) count++;
      }
      bucket.dataset.state = String(count);
    }
  }

  getTokenPresentaton(type: string, tokenKey: string, args: any = {}): string {
    const res = super.getTokenPresentaton(type, tokenKey, args);
    const tc = this.getRulesFor(tokenKey, "tc");
    if (tc) return `<span style="color:${tc};font-weight:bold">${res}</span>`;
    return res;
  }

  updateTokenDisplayInfo(tokenInfo: TokenDisplayInfo) {
    // override to generate dynamic tooltips and such
    const mainType = tokenInfo.mainType;
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
        if (subType === "legend") {
          this.buildLegendTooltip(tokenInfo);
        } else {
          this.buildMonsterTooltip(tokenInfo);
        }
        break;
      }
      case "hero": {
        const heroNo = parseInt(getPart(tokenId, 1));
        const player = Object.values(this.gamedatas.players).find((p: CustomPlayer) => p.heroNo === heroNo);
        tokenInfo.tooltip = "";
        if (!player) break;
        const color = player.color;
        const strength = this.getTokenState(`tracker_strength_${color}`) || 0;
        const health = this.getTokenState(`tracker_health_${color}`) || 0;
        const range = this.getTokenState(`tracker_range_${color}`) || 0;
        const move = this.getTokenState(`tracker_move_${color}`) || 0;
        const hand = this.getTokenState(`tracker_hand_${color}`) || 0;
        tokenInfo.tooltip += this.ttSection(_("Strength"), String(strength));
        tokenInfo.tooltip += this.ttSection(_("Health"), String(health));
        tokenInfo.tooltip += this.ttSection(_("Range"), String(range));
        tokenInfo.tooltip += this.ttSection(_("Move"), String(move));
        tokenInfo.tooltip += this.ttSection(_("Hand Limit"), String(hand));

        break;
      }
      case "house": {
        tokenInfo.tooltip = this.ttSection(_("Type"), this.getTr(tokenInfo.name));
        break;
      }
      case "die": {
        const dtype = getPart(tokenId, 1); // "attack" or "monster"
        const dieState = this.getTokenState(tokenId);
        if (dieState >= 1 && dieState <= 6) {
          tokenInfo.imageData = { state: String(dieState) };
          const sideKey = `side_die_${dtype}_${dieState}`;
          const sideInfo = this.getRulesFor(sideKey, "name", "");
          if (sideInfo) tokenInfo.tooltip = this.ttSection(_("Result"), this.getTr(sideInfo));
        }
        break;
      }
      case "slot": {
        if (tokenId.startsWith("slot_timetrack")) {
          // Timetrack slots: show step number and effect name
          const spotType = this.getRulesFor(tokenId, "r", null);
          if (spotType) {
            const stepNum = getPart(tokenId, -1);
            const effectName = this.getTokenName(spotType);
            tokenInfo.name = `${_("Step")} ${stepNum}: ${effectName}`;
            const spotDescriptions: Record<string, string> = {
              tm_yellow_axes: _("Reinforcements: each player draws 1 yellow monster card and places monsters accordingly"),
              tm_red_axes: _("Reinforcements: each player draws 1 red monster card and places monsters accordingly"),
              tm_yellow_shield: _("No reinforcements and no charge this turn"),
              tm_red_shield: _("No reinforcements and no charge this turn"),
              tm_red_skull: _("Charge: all monsters move 1 additional area toward Grimheim")
            };
            tokenInfo.tooltip = this.ttSection(_("Effect"), spotDescriptions[spotType] ?? effectName);
          }
        }
        break;
      }
      case "hex": {
        const q = getPart(tokenId, 1);
        const r = getPart(tokenId, 2);
        if (!r) return;
        tokenInfo.imageTypes = "_nottimage";
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
        const coords = `(${r},${q})`;
        tokenInfo.name = `${terrainname} ${coords}`;
        tokenInfo.tooltip = this.ttSection(_("Terrain"), terrainname);
        tokenInfo.tooltip += this.ttSection(_("Coords"), `(${r},${q})`);
        if (tokenInfo.loc) {
          tokenInfo.name = `${locname} - ${terrainname} ${coords}`;
          tokenInfo.tooltip += this.ttSection(_("Location"), locname);
        }
        if (tokenInfo.c) tokenInfo.tooltip += this.ttSection(_("Location Color"), areacolor);
      }
    }
  }

  buildMonsterTooltip(tokenInfo: TokenDisplayInfo) {
    const factionFlavor: Record<string, string> = {
      trollkin: _("The Trollkin are a savage clan of goblins, brutes, and trolls that roam the forests and valleys."),
      firehorde: _("The Fire Horde emerges from volcanic regions, bringing sprites, elementals, and mighty Jotunns."),
      dead: _("The Dead rise from marshes and plains – imps, skeletons, and the fearsome Draugr.")
    };
    if (factionFlavor[tokenInfo.faction]) {
      tokenInfo.tooltip += this.iiSection(factionFlavor[tokenInfo.faction]);
    }
    if (tokenInfo.rank) tokenInfo.tooltip += this.ttSection(_("Rank"), tokenInfo.rank);
    if (tokenInfo.strength) tokenInfo.tooltip += this.ttSection(_("Strength"), tokenInfo.strength);
    if (tokenInfo.health) tokenInfo.tooltip += this.ttSection(_("Health"), tokenInfo.health);
    if (tokenInfo.move) tokenInfo.tooltip += this.ttSection(_("Move"), tokenInfo.move);
    if (tokenInfo.armor) tokenInfo.tooltip += this.ttSection(_("Armor"), tokenInfo.armor);
    if (tokenInfo.xp) tokenInfo.tooltip += this.ttSection(_("XP"), tokenInfo.xp);
  }

  buildLegendTooltip(tokenInfo: TokenDisplayInfo) {
    const tokenId = tokenInfo.tokenId;
    const legendNum = getPart(tokenId, 2);
    const level = getPart(tokenId, 3); // "1" or "2"

    // Add parent prefix classes for CSS sprite targeting (create=1 tokens don't get these automatically)
    tokenInfo.imageTypes += ` monster_legend monster_legend_${legendNum}`;

    // Look up both sides' stats
    const side1 = this.getAllRules(`monster_legend_${legendNum}_1`);
    const side2 = this.getAllRules(`monster_legend_${legendNum}_2`);

    const legendFlavor: Record<string, string> = {
      "1": _(
        "A chilling sight to behold, Hel brings the dead to the underworld at death. At least those who died of old age and sickness. Let's hope that's not you..."
      ),
      "2": _(
        "This unsettling figure may be blind, but still sees things of the past and future, acting as an advisor to the Asgaard gods. In this case Loki and his hordes."
      ),
      "3": _(
        "The strength of this colossal beast is matched only by his lack of intellect. He has heard the singing from the mead hall and can't bear it any longer. He is hungry..."
      ),
      "4": _(
        "The fire giant with his flaming sword is supposed to bring about Ragnarok, the apocalypse of the cosmos – if he makes it that long."
      ),
      "5": _(
        "This brute leader is fearless and collects battle scars as trophies of his invincibility. Naturally, his presence infuses the entire trollkin clan with confidence."
      ),
      "6": _(
        "While the actual Midgaard Serpent encircles the entire world tree, Yggdrasil, nobody really has time to compare the sizes when this beast approaches."
      )
    };
    if (legendFlavor[legendNum]) tokenInfo.tooltip += this.iiSection(legendFlavor[legendNum]);

    // Show current level indicator
    tokenInfo.tooltip += this.ttSection(_("Current Level"), level === "1" ? "I" : "II");

    // Stats as Level I / Level II
    if (side1 && side2) {
      const fmt = (v: any) => (v == 0 ? "*" : `${v ?? "–"}`);
      const dual = (label: string, field: string) => {
        const v1 = side1[field];
        const v2 = side2[field];
        if (v1 != null || v2 != null) {
          tokenInfo.tooltip += this.ttSection(label, v1 == v2 ? fmt(v1) : `${fmt(v1)} / ${fmt(v2)}`);
        }
      };
      dual(_("Strength"), "strength");
      dual(_("Health"), "health");
      dual(_("XP"), "xp");
      dual(_("Armor"), "armor");

      // Special ability notes for legends with * strength
      const specialAbility: Record<string, string> = {
        "2": _("As her attack, deals 1 unpreventable damage to all heroes everywhere."),
        "6": _("Wyrm: Nidhuggr's strength is the same as its remaining health.")
      };
      if (specialAbility[legendNum]) tokenInfo.tooltip += this.iiSection(specialAbility[legendNum]);
    }
  }

  /** Get crystal damage/gold/mana info for a character from its bucket children. */
  getCrystalInfo(tokenId: string): string {
    let info = "";
    for (const type of ["red", "green", "yellow"]) {
      const bucket = $(`bucket_crystal_${type}_${tokenId}`);
      const count = parseInt(bucket?.dataset.state ?? "0");
      if (count > 0) info += this.ttSection(this.getTokenName(`crystal_${type}`), String(count));
    }
    return info;
  }

  handleStackedTooltips(attachNode: HTMLElement) {
    // Case 1: A hex that has children — remove hex tooltip, children own it
    if (attachNode.classList.contains("hex")) {
      if (attachNode.childElementCount > 0) {
        this.removeTooltip(attachNode.id);
      }
      return;
    }

    // Case 2: A token (hero/monster/house) on a hex — combine hex + token tooltips on the token
    const parentId = attachNode.parentElement?.id;
    if (!parentId?.startsWith("hex")) return;

    // Remove hex tooltip — the token on top will carry everything
    this.removeTooltip(parentId);

    // Rebuild token tooltip with crystal info injected into its tooltip text
    const tokenToken = attachNode.dataset.tt ?? attachNode.id;
    const tokenInfo = this.getTokenDisplayInfo(tokenToken, true);
    const crystalInfo = this.getCrystalInfo(attachNode.id);
    if (crystalInfo) tokenInfo.tooltip = (tokenInfo.tooltip ?? "") + crystalInfo;
    let combined = this.getTooltipHtmlForTokenInfo(tokenInfo);
    if (!combined) return;

    const hexHtml = this.getTooltipHtmlForToken(parentId);
    if (hexHtml) combined += hexHtml;

    this.game.addTooltipHtml(attachNode.id, combined, this.game.defaultTooltipDelay);
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

  async notif_log(args: any, notif: any) {
    super.notif_log(args, notif);
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
    scalecontrol.style.minWidth = "";
    scalecontrol.style.height = "";
    scalecontrol.style.marginBottom = "";
    scalecontrol.style.transformOrigin = "";
    scalecontrol.scrollLeft = 0;
    scalecontrol.dataset.scale = "1";
    parent.scrollLeft = 0;

    if (!set) {
      scalecontrol.style.minWidth = "unset";
      scalecontrol.style.width = "100%";
      return;
    }

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
