/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

import { getIntPart, getParentParts, getPart, placeHtml } from "./Game0Basics";
import { Game1Tokens, Token, TokenMoveInfo, AnimArgs, TokenDisplayInfo } from "./Game1Tokens";
import { LaHand } from "./LaHand";
import { LaZoom } from "./LaZoom";
import { PlayerTurn } from "./PlayerTurn";
import { CustomGamedatas, CustomPlayer } from "./types";

class PlayerTurnConfirm {
  constructor(
    private game: Game,
    private bga: Bga
  ) {}
  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    this.bga.statusBar.addActionButton(_("Confirm"), (event: Event) => {
      this.bga.actions
        .performAction("action_resolve", {})
        .then((x) => {
          console.log("action complete", x);
        })
        .catch((e: any) => {
          console.error(e);
        });
    });
  }
}
export class Game extends Game1Tokens {
  private playerTurn: PlayerTurn;
  private zoomControls!: LaZoom;
  private handControls!: LaHand;

  constructor(bga: Bga) {
    super(bga);
    //console.log("fate constructor");

    this.playerTurn = new PlayerTurn(this, bga);
    this.bga.states.register("PlayerTurn", this.playerTurn);
    this.bga.states.register("PlayerTurnConfirm", new PlayerTurnConfirm(this, bga));
  }

  onEnteringState(stateName: string, args: { args: { [key: string]: any } | null }) {
    console.log("Entering unknown state", stateName, args);
  }

  onToken(e: Event) {
    // TODO: pick proper state object
    this.playerTurn.onToken(e);
  }

  setup(gamedatas: CustomGamedatas) {
    this.inSetup = true;
    try {
      console.log("Starting game setup");
      super.setup(gamedatas);

      const title = $("page-title")!;
      const topbar = $("game_top_bar");
      if (topbar) topbar.remove();
      placeHtml(
        `
      <div id='game_top_bar' class='game_top_bar'>
        <div id='selection_area' class='selection_area'>
        </div>
      </div>`,
        title
      );
      placeHtml(`<div id="thething_wrap">        <div id="thething"></div>      </div>`, this.bga.gameArea.getElement());
      placeHtml(`<div id="limbo"></div>`, this.bga.gameArea.getElement());

      // Board area: map + monster turn display + supply (right side in wide layout)
      const mapWrapper = "map_wrapper";
      placeHtml(
        `<div id="board_area">
      <div id="display_battle"> </div>
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

      this.createMap($(mapWrapper)!);
      // Players panels
      placeHtml(`<div id="players_panels"></div>`, "thething");
      // Create hand container for current player only (not spectators)
      if (!this.bga.players.isCurrentPlayerSpectator()) {
        const myColor = this.player_color;
        const name = _("Hand");
        placeHtml(
          `<div id="hand_park_home"><div class="hand_area" data-name="${name}"><div id="hand_${myColor}" class="hand"></div></div></div>`,
          "players_panels"
        );
      }

      const orderedPlayerIds = this.getOrderedPlayerIds(gamedatas);
      orderedPlayerIds.forEach((pid: number) => {
        const player: CustomPlayer = gamedatas.players[pid];
        const color = player.color;
        const hnoClass = player.heroNo ? `hno_${player.heroNo}` : "";
        const heroName = player.heroNo ? this.getTokenName(`hero_${player.heroNo}`) : "";
        placeHtml(`<div id="tableau_${color}" class="tableau ${hnoClass}"></div`, "players_panels");
        ["deck_ability", "deck_equip", "deck_event", "discard"].forEach((d) => {
          const name = this.getTr(_("${hero}'s ${deck}"), {
            hero: heroName,
            deck: this.getRulesFor(d, "name")
          });
          placeHtml(
            `<div class="deck_wrapper" data-name="${name}"><div id="${d}_${color}" class="deck ${d}"></div></div>`,
            `tableau_${color}`
          );
        });

        const panel = this.bga.playerPanels.getElement(Number(player.id));

        placeHtml(
          `<div id="miniboard_${color}" class="miniboard ${hnoClass}" style="--player-color: #${color}">
          <div class="miniboard_banner">${heroName}</div>
          <div id="bucket_crystal_yellow_tableau_${color}" class="pboard_slot bucket bucket_crystal_yellow"></div>
        </div>`,
          panel
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

      this.setupTokens(gamedatas);
      this.zoomControls = new LaZoom(this.bga, { targetId: "thething", storagePrefix: "fate" });
      this.zoomControls.setup();

      const handArea = document.querySelector<HTMLElement>("#hand_park_home > .hand_area");
      if (handArea) {
        this.handControls = new LaHand({
          handArea,
          parkedHome: $("hand_park_home")!,
          floatDockParent: this.bga.gameArea.getElement(),
          storagePrefix: "fate"
        });
        this.handControls.setup();
      }

      this.setupNotifications();

      this.setupCardCatalog();

      if (gamedatas.endBanner) {
        if (gamedatas.endBanner.isWellDestroyed) this.bga.gameArea.addLastTurnBanner(gamedatas.endBanner.message);
        else this.bga.gameArea.addWinConditionBanner(gamedatas.endBanner.message);
      }

      // last minute tweaks for miniboard
      Object.values(gamedatas.players).forEach((player: CustomPlayer) => {
        const color = player.color;
        const mini = $(`miniboard_${color}`)!;
        const heroNo = Number(player.heroNo);

        // hand count + limit stitched into one n/N composite (both stay live nodes)
        const handComposite = this.wrapComposite(
          $(`counter_hand_${color}`)!,
          $(`tracker_hand_${color}`)!,
          "minicomposite_hand wicon_hand wicon"
        );
        handComposite.id = `minicomposite_hand_${color}`;
        mini.appendChild(handComposite);
        this.updateTooltip("minicomposite_hand", handComposite);

        // hero damage mirror on miniboard

        // damage
        const srcBucket = $(`bucket_crystal_red_hero_${heroNo}`);
        const initialState = srcBucket?.dataset.state ?? "0";
        placeHtml(
          `<div id="tracker_damage_${color}" class="bucket bucket_crystal_red tracker_damage" data-hero="hero_${heroNo}" data-state="${initialState}"></div>`,
          `miniboard_${color}`
        );
        this.updateTooltip("tracker_damage", $(`tracker_damage_${color}`));

        // Boldur's hero cards grant "Armor. (Always prevents 1 damage)" - static signature pill
        if (heroNo == 4) {
          placeHtml(
            `<div id="tracker_armor_${color}" class="tracker_armor tracker wicon wicon_armor" data-state="1"></div>`,
            `miniboard_${color}`
          );
          this.updateTooltip(`tracker_armor_${color}`);
        }

        // gold bucket + upgrade cost marker stitched into one x/y composite (both stay live nodes)
        const marker = $(`marker_${color}_3`)!;
        marker.classList.add("bucket", "upgrade_cost");
        this.updateTooltip("upgrade_cost", marker);
        const goldBucket = $(`bucket_crystal_yellow_tableau_${color}`)!;
        const miniGoldComposite = this.wrapComposite(goldBucket, marker, "minicomposite_gold");
        miniGoldComposite.id = `minicomposite_gold_${color}`;
        mini.appendChild(miniGoldComposite);
        this.updateTooltip(miniGoldComposite.id);

        const tname = this.getRulesFor(`hero_${player.heroNo}`, "name");
        $(`tableau_${color}`)!.dataset.name = this.getTr(tname);
        const hand = document.querySelector(`.hand_area > #hand_${color}`);
        if (hand) hand.parentElement!.dataset.name = this.getTr("${hero}'s Hand", { hero: this.getTr(tname) });
      });
    } catch (e) {
      console.error(e);
      throw e;
    } finally {
      this.inSetup = false;
    }
    console.log("Ending game setup");
  }

  private setupCardCatalog() {
    const root = this.bga.gameArea.getElement();
    placeHtml(`<div id="catalog" class="catalog"></div>`, root);

    const folders: { id: string; titleKey: string; hno?: number }[] = [];
    for (let hno = 1; hno <= 4; hno++) {
      const heroName = this.getRulesFor(`hero_${hno}`, "name", `Hero ${hno}`);
      folders.push({ id: `catalog_hero_${hno}`, titleKey: _("Cards:") + " " + heroName, hno });
    }
    folders.push({ id: "catalog_monster", titleKey: _("Cards: Monsters") });

    folders.forEach((f) => {
      placeHtml(
        `<details class="catalog_folder" id="${f.id}">
          <summary><span class="catalog_title">${this.getTr(f.titleKey)}</span></summary>
          <div class="catalog_filter_wrap">
            <input type="text" class="catalog_filter" placeholder="${_("Search...")}">
            <button type="button" class="catalog_filter_clear" title="${_("Clear")}">✕</button>
          </div>
          <span>${_("Click or hover over card to get details")}</span>
          <div class="catalog_cards" id="${f.id}_cards"></div>
        </details>`,
        "catalog"
      );
    });

    const ctypeOrder: Record<string, number> = { hero: 0, ability: 1, equip: 2, event: 3, monster: 4 };
    type Entry = { cardId: string; folderId: string; ctype: string; num: number };
    const entries: Entry[] = [];
    for (const key in this.gamedatas.token_types) {
      if (!key.startsWith("card_")) continue;
      const ctype = getPart(key, 1);
      if (ctype === "monster") {
        const num = parseInt(getPart(key, 2)) || 0;
        entries.push({ cardId: key, folderId: "catalog_monster", ctype, num });
      } else {
        const hno = parseInt(getPart(key, 2));
        const num = parseInt(getPart(key, 3)) || 0;
        if (!(hno >= 1 && hno <= 4)) continue;
        entries.push({ cardId: key, folderId: `catalog_hero_${hno}`, ctype, num });
      }
    }
    entries.sort((a, b) => (ctypeOrder[a.ctype] ?? 99) - (ctypeOrder[b.ctype] ?? 99) || a.num - b.num);

    const counts: Record<string, number> = {};
    entries.forEach((e) => {
      const container = $(`${e.folderId}_cards`);
      if (!container) return;
      const info = this.getTokenDisplayInfo(e.cardId);
      const div = document.createElement("div");
      div.id = `catalog_${e.cardId}`;
      div.classList.add(...info.imageTypes.split(/  */).filter(Boolean));
      div.classList.add("catalog_card");
      div.dataset.targetId = e.cardId;
      container.appendChild(div);
      this.updateTooltip(e.cardId, div);
      counts[e.folderId] = (counts[e.folderId] ?? 0) + 1;
    });

    folders.forEach((f) => {
      const folderEl = $(f.id);
      if (!folderEl) return;
      const titleSpan = folderEl.querySelector(".catalog_title") as HTMLElement;
      const count = counts[f.id] ?? 0;
      titleSpan.textContent = `${titleSpan.textContent} (${count})`;

      const cardsEl = folderEl.querySelector(".catalog_cards") as HTMLElement;
      cardsEl.addEventListener("click", (e) => {
        const node = (e.target as HTMLElement).closest(".catalog_card") as HTMLElement | null;
        if (!node || !node.dataset.targetId) return;
        this.showCatalogCardDialog(node.dataset.targetId);
      });

      const filter = folderEl.querySelector(".catalog_filter") as HTMLInputElement;
      const applyFilter = () => {
        const q = filter.value.trim().toLowerCase();
        cardsEl.querySelectorAll<HTMLElement>(".catalog_card").forEach((card) => {
          if (!q) {
            card.style.removeProperty("display");
            return;
          }
          const id = card.dataset.targetId;
          if (!id) return;
          const text = (this.getTooltipHtmlForToken(id) || "").toLowerCase() + " " + (card.dataset.name || "").toLowerCase();
          card.style.display = text.includes(q) ? "" : "none";
        });
      };
      filter.addEventListener("input", applyFilter);
      const clearBtn = folderEl.querySelector(".catalog_filter_clear") as HTMLButtonElement;
      clearBtn.addEventListener("click", () => {
        filter.value = "";
        applyFilter();
        filter.focus();
      });
    });
  }

  private showCatalogCardDialog(cardId: string) {
    const dialog = new ebg.popindialog();
    dialog.create("catalog_card_dlg");
    dialog.setTitle(this.getTokenName(cardId));
    dialog.setContent(`<div class="catalog_dlg_content">${this.getTooltipHtmlForToken(cardId)}</div>`);
    dialog.show();
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
        const road = this.getRulesFor(hexId, "road", 0);
        const roadCls = road ? " road" : "";
        hexes.push(
          `<div class="hex terrain_${terrain}${roadCls}" id="${hexId}" style="left:${leftPct}%;top:${topPct}%;" data-q="${q}" data-r="${r}" data-loc="${loc}"></div>`
        );
      }
    }

    const hexHtml = hexes.join("\n");

    placeHtml(`<div id="map_area">${hexHtml}</div>`, parent);

    parent.querySelectorAll<HTMLElement>(".hex").forEach((node) => {
      this.addListenerWithGuard(node, (e) => this.onToken(e));
      this.updateTooltip(node.id);
    });
  }

  getPlaceRedirect(tokenInfo: Token, args: AnimArgs = {}): TokenMoveInfo {
    const result = { ...tokenInfo } as TokenMoveInfo;
    const loc = tokenInfo.location;
    const tokenKey = tokenInfo.key;
    const mainType = getPart(tokenKey, 0);

    if (args.place_from) result.place_from = args.place_from;

    switch (mainType) {
      case "tracker":
        // Redirect tracker tokens to miniboard in player panel
        if (loc.startsWith("tableau_")) {
          const color = getPart(loc, 1);
          result.location = `miniboard_${color}`;
          result.noa = true;
          if (tokenKey.startsWith("tracker_hand")) {
            // hand limit lives inside the hand composite; update state in place, don't relocate
            result.nop = true;
            break;
          }
          // Hero attack/health changed (upgrade, equip) — refresh its stats box on the map
          if (tokenKey.startsWith("tracker_strength") || tokenKey.startsWith("tracker_health")) {
            const player = Object.values(this.gamedatas.players ?? {}).find((p: CustomPlayer) => p.color === color);
            if (player) result.onEnd = () => this.refreshAttackStat(`hero_${player.heroNo}`);
          }
        }
        break;

      case "marker":
        if (loc.startsWith("tableau_")) {
          const color = getPart(loc, 1);
          result.location = `miniboard_${color}`;
          result.noa = true;
          // upgrade cost marker lives inside the gold composite; update state in place, don't relocate
          if (getPart(tokenKey, 2) === "3") result.nop = true;
        }
        break;

      case "monster":
        if (loc === "supply_monster") {
          // Stack monsters by type in supply: create sub-container per monster type
          const monsterType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1);
          if (monsterType === "monster_legend") {
            // Legends: place directly on map_wrapper, no piling
            result.location = "map_wrapper";
          } else {
            const subId = "supply_" + monsterType;
            if (!$(subId)) {
              const attackHtml = this.buildAttackStat(monsterType);
              const stats = attackHtml ? `<div class="stats_attack">${attackHtml}</div>` : "";
              placeHtml(`<div id="${subId}" class="pile_monster ${monsterType}">${stats}</div>`, "map_wrapper");
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
        }
        break;

      case "card":
        result.onClick = (e) => this.onToken(e);
        // Upgrade flip: L2 just landed in tableau; flip from L1's sprite to L2's at the slot.
        if (args.flip_from) {
          const fromId = args.flip_from;
          result.onEnd = () => {
            this.animationLa.cardFlip(tokenKey, String(tokenInfo.state), 1000, undefined, fromId);
          };
        }
        break;

      case "crystal": {
        // Bucket redirect: tokens placed on another token get a sub-container bucket
        // e.g. crystal_red on monster_goblin_1 → bucket_crystal_red_monster_goblin_1
        const bucketType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1);
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
                const oldCharId = oldBucketId.replace(/^bucket_crystal_[a-z]+_/, "");
                if ($(oldCharId)) this.updateTooltip(oldCharId, undefined, { force: true });
              }
              this.updateBucketCount(bucketId);
            };
          }
        } else if (oldBucketId) {
          // Crystal returning to supply — suppress slide, just pulse the old bucket
          const oldCharId = oldBucketId.replace(/^bucket_crystal_[a-z]+_/, "");
          result.noa = true;
          result.onEnd = () => {
            this.updateBucketCount(oldBucketId);
            this.animationLa.pulse(oldBucketId);
            if ($(oldCharId)) this.updateTooltip(oldCharId, undefined, { force: true });
          };
        }
        break;
      }

      case "timetrack":
        // Redirect timetrack container to timetrack_area and populate slots
        result.location = "timetrack_area";
        result.onEnd = () => this.createTimetrack(tokenKey);
        break;

      case "rune":
        // Redirect rune_stone to the specific timetrack slot based on its state (step number)
        if (tokenKey === "rune_stone" && loc.startsWith("timetrack_")) {
          result.location = `slot_${loc}_${tokenInfo.state}`;
        }
        break;

      case "die":
        // Dice landing on display_battle: show evaporate effect at the attack target
        if (loc === "display_battle" && args.anim_target) {
          const target = args.anim_target;
          result.onEnd = (node) => {
            this.animationLa.evaporate(node, target);
          };
        }
        break;

      case "display":
        if (tokenKey.startsWith("display_battle")) {
          result.nop = true;
        }
        break;

      case "tableau":
        result.nop = true;
        break;
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
      // sync miniboard damage mirror if this is a hero's red crystal bucket
      const heroMatch = bucketId.match(/^bucket_crystal_red_(hero_(\d+))$/);
      if (heroMatch) {
        const heroNo = parseInt(heroMatch[2]);
        const player = Object.values(this.gamedatas.players).find((p: CustomPlayer) => p.heroNo === heroNo);
        const max = player ? this.getTokenState(`tracker_health_${player.color}`) : 0;
        const mirror = document.querySelector(`[data-hero="${heroMatch[1]}"].bucket_crystal_red`) as HTMLElement;
        if (mirror) mirror.dataset.state = String(count);
        if (max > 0) {
          bucket.dataset.max = String(max);
          if (mirror) mirror.dataset.max = String(max);
        }
      }
      // Monster damage bucket: stash max HP so the badge can render "damage/max".
      const monsterMatch = bucketId.match(/^bucket_crystal_red_(monster_.+)$/);
      if (monsterMatch) {
        const max = parseInt(this.getRulesFor(monsterMatch[1], "health", "0"));
        if (max > 0) bucket.dataset.max = String(max);
        // refresh the attack box for monsters whose attack depends on damage (e.g. Nidhuggr)
        this.refreshAttackStat(monsterMatch[1]);
      }
    }
  }

  /** Inner html for a character/pile .stats_attack box: attack / max-health.
   *  Returns null for non-combatants (no stats, e.g. gold veins) so no box is shown. */
  buildAttackStat(charId: string): string | null {
    let attack = 0;
    let hp = 0;
    const damage = parseInt($(`bucket_crystal_red_${charId}`)?.dataset.state ?? "0");
    if (getPart(charId, 0) === "hero") {
      const heroNo = getIntPart(charId, 1);
      const player = Object.values(this.gamedatas.players).find((p: CustomPlayer) => p.heroNo === heroNo);
      if (player) {
        attack = this.getTokenState(`tracker_strength_${player.color}`);
        hp = this.getTokenState(`tracker_health_${player.color}`);
      }
    } else {
      hp = parseInt(this.getRulesFor(charId, "health", "0"));
      if (getPart(charId, 1) === "legend" && getPart(charId, 2) === "6") {
        // Nidhuggr: attack equals its current remaining health (max - damage)
        attack = hp - damage;
      } else {
        attack = parseInt(this.getRulesFor(charId, "strength", "0"));
      }
    }

    if (!attack && !hp) return null;
    const remhealth = hp - damage;
    const important = damage > 0;
    return this.buildCompositeCounter(
      {
        [`counter_attack_${charId}`]: attack,
        [`counter_remhealth_${charId}`]: remhealth
      },
      important ? "composite_second_important" : ""
    );
  }

  buildCompositeCounter(pair: Record<string, string | number>, classes: string = "") {
    const [[firstId, firstValue], [secondId, secondValue]] = Object.entries(pair);
    return `<div class="composite ${classes}"><div id="${firstId}" class="composite_first" data-state="${firstValue}"></div><span class="composite_separator"></span><div id="${secondId}" class="composite_second" data-state="${secondValue}"></div></div>`;
  }

  // Composite around two live nodes (e.g. gold bucket + cost marker) that must keep their identity:
  // moves them into the shared .composite shell so they render via the same CSS as buildCompositeCounter.
  wrapComposite(first: HTMLElement, second: HTMLElement, classes: string = ""): HTMLElement {
    const composite = document.createElement("div");
    composite.className = `composite ${classes}`.trim();
    // value renders from data-state; default empty nodes (e.g. gold bucket with no crystals) to 0
    first.dataset.state ??= "0";
    second.dataset.state ??= "0";
    first.classList.add("composite_first");
    second.classList.add("composite_second");
    const separator = document.createElement("span");
    separator.className = "composite_separator";
    composite.append(first, separator, second);
    return composite;
  }

  /** Ensure a map character carries its .stats_attack box and refresh its content. */
  refreshAttackStat(charId: string) {
    const node = $(charId);
    if (!node) return;
    let box = node.querySelector(":scope > .stats_attack") as HTMLElement | null;
    const html = this.buildAttackStat(charId);
    if (html === null) {
      box?.remove();
      return;
    }
    if (!box) {
      box = document.createElement("div");
      box.className = "stats_attack";
      node.appendChild(box);
    }
    box.innerHTML = html;
  }

  updateToken(tokenNode: HTMLElement, placeInfo: TokenMoveInfo) {
    super.updateToken(tokenNode, placeInfo);
    const charId = placeInfo.key;
    const mainType = getPart(charId, 0);
    if (mainType === "hero") {
      this.refreshAttackStat(charId);
    } else if (mainType === "monster") {
      const loc = placeInfo.location ?? "";
      // only map characters get a box; supply monsters live in piles (the pile has its own)
      if (loc.startsWith("hex_") || loc === "map_wrapper") this.refreshAttackStat(charId);
    }
  }

  getTokenPresentaton(type: string, tokenKey: string, args?: any, strict?: false): string;
  getTokenPresentaton(type: string, tokenKey: string, args: any, strict: true): string | null;
  getTokenPresentaton(type: string, tokenKey: string, args: any = {}, strict: boolean = false): string | null {
    const res = strict ? super.getTokenPresentaton(type, tokenKey, args, true) : super.getTokenPresentaton(type, tokenKey, args);
    if (res === null) return null;
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
        if (subType === "goldvein") {
          tokenInfo.tooltip = this.ttSection(_("Effect"), _("Mountain gold deposit. Damage dealt converts to [XP]."));
          break;
        }

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
        let rows = "";
        rows += this.ttRow(_("Strength"), this.getTokenState(`tracker_strength_${color}`), "strength");
        rows += this.ttRow(_("Health"), this.getTokenState(`tracker_health_${color}`), "health");
        rows += this.ttRow(_("Range"), this.getTokenState(`tracker_range_${color}`), "range");
        rows += this.ttRow(_("Move"), this.getTokenState(`tracker_move_${color}`), "move");
        rows += this.ttRow(_("Hand Limit"), this.getTokenState(`tracker_hand_${color}`), "hand");
        tokenInfo.tooltip += this.ttStats(rows);
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
        if (tokenInfo.road) tokenInfo.tooltip += this.ttSection(_("Road"), _("Yes"));
      }
    }
  }

  private factionAttackRange(faction: string): number {
    return faction === "firehorde" ? 2 : 1;
  }

  private factionEffectText(faction: string): string | undefined {
    const map: Record<string, string> = {
      trollkin: _("All Trollkin get +1 attack strength for each other Trollkin adjacent to them."),
      firehorde: _("All Fire Horde monsters have attack range 2."),
      dead: _("Runes count as hits when the Dead attack.")
    };
    return map[faction];
  }

  private factionFlavorText(faction: string): string | undefined {
    const map: Record<string, string> = {
      trollkin: _("The Trollkin are a savage clan of goblins, brutes, and trolls that roam the forests and valleys."),
      firehorde: _("The Fire Horde emerges from volcanic regions, bringing sprites, elementals, and mighty Jotunns."),
      dead: _("The Dead rise from marshes and plains – imps, skeletons, and the fearsome Draugr.")
    };
    return map[faction];
  }

  buildMonsterTooltip(tokenInfo: TokenDisplayInfo) {
    let rows = "";
    tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction) + " - " + tokenInfo.rank);
    rows += this.ttRow(_("Strength"), tokenInfo.strength, "strength");
    rows += this.ttRow(_("Health"), tokenInfo.health, "health");
    rows += this.ttRow(_("Gold"), tokenInfo.xp, "gold");
    if (tokenInfo.move) rows += this.ttRow(_("Move"), tokenInfo.move, "move");
    // Range is not shipped in material; derive from faction. Only show when > 1.
    const range = this.factionAttackRange(tokenInfo.faction);
    if (range > 1) rows += this.ttRow(_("Range"), String(range), "range");
    if (tokenInfo.armor) rows += this.ttRow(_("Armor"), tokenInfo.armor);

    tokenInfo.tooltip += this.ttStats(rows);
    const eff = this.factionEffectText(tokenInfo.faction);
    if (eff) tokenInfo.tooltip += this.ttSection(_("Faction Effect"), eff);
    const flv = this.factionFlavorText(tokenInfo.faction);
    if (flv) tokenInfo.tooltip += this.iiSection(flv);
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
        "The fire giant with his flaming sword is supposed to bring about Ragnarok, the apocalypse of the cosmos - if he makes it that long."
      ),
      "5": _(
        "This brute leader is fearless and collects battle scars as trophies of his invincibility. Naturally, his presence infuses the entire trollkin clan with confidence."
      ),
      "6": _(
        "While the actual Midgaard Serpent encircles the entire world tree, Yggdrasil, nobody really has time to compare the sizes when this beast approaches."
      )
    };
    tokenInfo.tooltip = this.ttSection(
      _("Faction"),
      this.getTokenName(tokenInfo.faction) + " " + _("Legend") + " " + (level === "1" ? "I" : "II")
    );

    let rows = "";
    // Stats as Level I / Level II
    if (side1 && side2) {
      const fmt = (v: any) => (v == 0 ? "*" : `${v ?? "-"}`);
      const dual = (label: string, field: string, icon: string = "") => {
        const v1 = level === "1" ? side1[field] : side2[field];
        const v2 = side2[field];
        if (v1 || v2) {
          rows += this.ttRow(label, v1 == v2 ? fmt(v1) : `${fmt(v1)} (${fmt(v2)} - level II)`, icon);
        }
      };
      dual(_("Strength"), "strength", "strength");
      dual(_("Health"), "health", "health");
      dual(_("Gold"), "xp", "gold");
      // Range follows the legend's faction (firehorde legends get +1).
      const range = this.factionAttackRange(tokenInfo.faction);
      if (range > 1) rows += this.ttRow(_("Range"), String(range), "range");
      dual(_("Armor"), "armor");
    }
    tokenInfo.tooltip += this.ttStats(rows);

    // Special ability notes for legends with * strength
    const specialAbility: Record<string, string> = {
      "2": _("As her attack, deals 1 unpreventable damage to all heroes everywhere."),
      "6": _("Wyrm: Nidhuggr's strength is the same as its remaining health.")
    };
    if (specialAbility[legendNum]) tokenInfo.tooltip += this.ttSection(_("Ability"), specialAbility[legendNum]);

    // Faction effect — same map as regular monsters; lets the player see Seer/Surt are firehorde, Queen is dead, etc.
    const eff = this.factionEffectText(tokenInfo.faction);
    if (eff) tokenInfo.tooltip += this.ttSection(_("Faction Effect"), eff);

    if (legendFlavor[legendNum]) tokenInfo.tooltip += this.iiSection(legendFlavor[legendNum]);
  }

  /** Get crystal damage/gold/mana + status (stun) info for a character from its child tokens. */
  getCrystalInfo(tokenId: string): string {
    const iconForCrystal: Record<string, string> = { red: "damage", green: "mana", yellow: "gold" };
    let rows = "";
    for (const type of ["red", "green", "yellow"]) {
      const bucket = $(`bucket_crystal_${type}_${tokenId}`);
      const count = parseInt(bucket?.dataset.state ?? "0");
      if (count > 0) rows += this.ttRow(this.getTokenName(`crystal_${type}`), count, iconForCrystal[type]);
    }
    let info = this.ttStats(rows);
    if ($(tokenId)?.querySelector(':scope > .stunmarker[data-state="0"]')) {
      info += this.ttSection(_("Stunned"), _("Cannot move during this monster turn"));
    }
    return info;
  }

  getDynamicTooltip(tokenInfo: TokenDisplayInfo, attachNode?: HTMLElement) {
    if (attachNode) {
      const crystalInfo = this.getCrystalInfo(attachNode?.id);
      return crystalInfo;
    } else {
      return undefined;
    }
  }

  handleStackedTooltips(attachNode: HTMLElement) {
    // Case 1: A hex that has children — remove hex tooltip, children own it
    if (attachNode.classList.contains("hex")) {
      for (const child of attachNode.children) {
        this.handleStackedTooltipsParentChild(attachNode, child as HTMLElement);
      }
      return;
    }

    // Case 2: A token (hero/monster/house) on a hex — combine hex + token tooltips on the token
    if (this.handleStackedTooltipsParentChild(attachNode.parentElement as HTMLElement, attachNode)) {
      return;
    }

    // Case 3: Level I hero/ability card on tableau — append Level II preview as a second container
    const tokenId = attachNode.dataset.tt ?? attachNode.id;
    const siblingId = this.getLevel2Sibling(tokenId);
    if (siblingId) {
      const baseHtml = this.getTooltipHtmlForToken(tokenId);
      const reverseHtml = this.getTooltipHtmlForToken(siblingId);
      this.game.addTooltipHtml(attachNode.id, baseHtml + reverseHtml, this.game.defaultTooltipDelay);
    }
  }

  handleStackedTooltipsParentChild(parentElement: HTMLElement, attachNode: HTMLElement) {
    const parentId = parentElement?.id;
    const tokenId = attachNode.dataset.tt ?? attachNode.id;
    if (!tokenId) return false;
    const mainChildType = getPart(tokenId, 0);
    // buckets on hexes carry crystal-count tooltips already; don't stack hex info on top
    if (parentId?.startsWith("hex") && mainChildType != "bucket" && mainChildType != "marker") {
      const childHtml = this.getTooltipHtmlForToken(tokenId);
      const hexHtml = this.getTooltipHtmlForToken(parentId);

      this.game.addTooltipHtml(attachNode.id, childHtml + hexHtml, this.game.defaultTooltipDelay);
      this.game.addTooltipHtml(parentId, childHtml + hexHtml, this.game.defaultTooltipDelay);
      return true;
    }
    return false;
  }

  /** Return the Level II sibling tokenId iff `tokenId` is a Level I hero/ability card */
  private getLevel2Sibling(tokenId: string): string | null {
    if (getPart(tokenId, 0) !== "card") return null;
    const sub = getPart(tokenId, 1);
    if (sub !== "hero" && sub !== "ability") return null;
    const last = getIntPart(tokenId, -1);
    if (last % 2 !== 1) return null;
    return getParentParts(tokenId) + "_" + (last + 1);
  }

  setupNotifications() {
    console.log("notifications subscriptions setup");

    // automatically listen to the notifications, based on the `notif_xxx` function on this class.
    this.bga.notifications.setupPromiseNotifications({
      minDuration: 1,
      minDurationNoText: 1,

      //logger: console.log, // show notif debug informations on console. Could be console.warn or any custom debug function (default null = no logs)
      //handlers: [this, this.tokens],
      onStart: (notifName, msg, args) => {
        if (msg) this.setActionStatus(msg, args);
      }
    });
  }

  async notif_tokenMoved(args: any) {
    return super.notif_tokenMoved(args);
  }

  async notif_tokenMovedAsync(args: any) {
    return super.notif_tokenMovedAsync(args);
  }

  async notif_counter(args: any) {
    return super.notif_counter(args);
  }

  async notif_counterAsync(args: any) {
    return super.notif_counterAsync(args);
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

  async notif_endBanner(args: any) {
    if (args.isWellDestroyed) this.bga.gameArea.addLastTurnBanner(args.message);
    else this.bga.gameArea.addWinConditionBanner(args.message);
    return gameui.wait(1);
  }
}
