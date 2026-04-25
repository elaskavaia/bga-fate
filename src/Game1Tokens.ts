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

import { Game0Basics, placeHtml, getPart, getParentParts, StringProperties } from "./Game0Basics";
import { LaAnimations } from "./LaAnimations";
import { BgaAnimations } from "./libs";
import { NotificationMessage } from "./types";

/**
 * Interface that mimics token datatabase object
 */
export interface Token {
  key: string;
  location: string;
  state: number;
}

export interface TokenDisplayInfo {
  key: string; // token id
  tokenId: string; // original id of html node
  typeKey: string; // this is key in token_types structure
  mainType: string; // first type
  imageTypes: string; // all classes
  imageData?: Record<string, string>; // data-* attributes for tooltip image
  name?: string | NotificationMessage;
  tooltip?: string | NotificationMessage;
  showtooltip?: boolean;
  [key: string]: any;
}

export interface TokenMoveInfo extends Token, AnimArgs {
  onStart?: (node: Element) => Promise<void> | void;
  onEnd?: (node: Element) => void;
  onClick?: (event?: any) => void;
}

export interface AnimArgs {
  duration?: number;
  noa?: boolean;
  nop?: boolean;
  nod?: boolean;
  delay?: number;
  place_from?: string;
  inc?: number;
  anim_target?: string;
}

export class Game1Tokens extends Game0Basics {
  CON: { [key: string]: string }; // constants from php
  original_click_id: any;
  globlog: number = 1;
  tokenInfoCache: { [key: string]: TokenDisplayInfo } = {};

  defaultAnimationDuration: number = 500;

  classActiveSlot: string = "active_slot";
  classActiveSlotHidden: string = "hidden_active_slot";
  classButtonDisabled: string = "disabled";
  classSelected: string = "gg_selected"; // for the purpose of multi-select operations
  classSelectedAlt: string = "gg_selected_alt"; // for the purpose of multi-select operations with alternative node
  game: Game1Tokens = this;
  animationManager: AnimationManager;
  animationLa: LaAnimations;

  setupTokens(gamedatas: any): void {
    this.tokenInfoCache = {};

    // create the animation manager, and bind it to the `game.bgaAnimationsActive()` function
    this.animationManager = new BgaAnimations.Manager({
      animationsActive: () => this.bgaAnimationsActive()
    });

    this.animationLa = new LaAnimations(this.bga);
    this.animationLa.setup();

    if (!this.gamedatas.tokens) {
      console.error("Missing gamadatas.tokens!");
      this.gamedatas.tokens = {};
    }
    if (!this.gamedatas.token_types) {
      console.error("Missing gamadatas.token_types!");
      this.gamedatas.token_types = {};
    }

    this.gamedatas.tokens["limbo"] = {
      key: "limbo",
      state: 0,
      location: "thething"
    };
    this.placeTokenSetup("limbo");

    this.placeAllTokens();
    this.updateCountersSafe(this.gamedatas.counters);
  }

  cancelLocalStateEffects() {
    //console.log(this.last_server_state);

    this.game.removeAllClasses(this.classActiveSlot, this.classActiveSlotHidden);
    this.game.removeAllClasses(this.classSelected, this.classSelectedAlt);
    //this.restoreServerData();
    //this.updateCountersSafe(this.gamedatas.counters);
  }

  addShowMeButton(scroll: boolean) {
    const firstTarget = document.querySelector("." + this.classActiveSlot);
    if (!firstTarget) return;
    this.bga.statusBar.addActionButton(
      _("Show me"),
      () => {
        const butt = $("button_showme");
        const firstTarget = document.querySelector("." + this.classActiveSlot);
        if (!firstTarget) return;
        if (scroll) $(firstTarget).scrollIntoView({ behavior: "smooth", block: "center" });
        document.querySelectorAll("." + this.classActiveSlot).forEach((node) => {
          const elem = node as HTMLHtmlElement;
          elem.style.removeProperty("animation");
          elem.style.setProperty("animation", "active-pulse 500ms 3");
          butt.classList.add(this.classButtonDisabled);
          setTimeout(() => {
            elem.style.removeProperty("animation");
            butt.classList.remove(this.classButtonDisabled);
          }, 1500);
        });
      },
      {
        color: "secondary",
        id: "button_showme"
      }
    );
  }

  getAllLocations() {
    const res = [];
    for (const key in this.gamedatas.token_types) {
      const info = this.gamedatas.token_types[key];
      if (this.isLocationByType(key) && info.scope != "player") res.push(key);
    }
    for (var token in this.gamedatas.tokens) {
      var tokenInfo = this.gamedatas.tokens[token];
      var location = tokenInfo.location;
      if (location && res.indexOf(location) < 0) res.push(location);
    }
    return res;
  }

  isLocationByType(id: string) {
    return this.getRulesFor(id, "type", "").indexOf("location") >= 0;
  }

  updateCountersSafe(counters: {}) {
    //console.log(counters);

    for (var key in counters) {
      let node = $(key);
      if (counters.hasOwnProperty(key)) {
        if (!node) {
          let deckId = key.replace("counter_", "");
          if (!$(deckId)) {
            deckId = "limbo";
          }
          placeHtml(`<div id='${key}' class='counter'></div>`, deckId);
          node = $(key);
        }
        if (node) {
          const value = counters[key].value;
          node.dataset.state = value;
        } else {
          console.log("unknown counter " + key);
        }
      }
    }
  }

  private placeAllTokens() {
    console.log("Place tokens");

    for (let loc of this.getAllLocations()) {
      this.placeTokenSetup(loc);
    }

    for (let token in this.gamedatas.tokens) {
      const tokenInfo = this.gamedatas.tokens[token];
      const location = tokenInfo.location;
      if (location && !this.gamedatas.tokens[location] && !$(location)) {
        this.placeTokenSetup(location);
      }
      this.placeTokenSetup(token);
    }

    for (let token in this.gamedatas.tokens) {
      this.updateTooltip(token);
    }
    for (let loc of this.getAllLocations()) {
      this.updateTooltip(loc);
    }
  }

  setTokenInfo(token_id: string, place_id?: string, new_state?: number, serverdata?: boolean, args?: any): Token {
    var token = token_id;
    if (!this.gamedatas.tokens[token]) {
      this.gamedatas.tokens[token] = {
        key: token,
        state: 0,
        location: "limbo"
      };
    }

    if (args) {
      args._prev = structuredClone(this.gamedatas.tokens[token]);
    }
    if (place_id !== undefined) {
      this.gamedatas.tokens[token].location = place_id;
    }

    if (new_state !== undefined) {
      this.gamedatas.tokens[token].state = new_state;
    }

    //if (serverdata === undefined) serverdata = true;
    //if (serverdata && this.gamedatas_server) this.gamedatas_server.tokens[token] = dojo.clone(this.gamedatas.tokens[token]);
    return this.gamedatas.tokens[token];
  }

  createToken(placeInfo: TokenMoveInfo) {
    const tokenId = placeInfo.key;
    const location = placeInfo.place_from ?? placeInfo.location ?? this.getRulesFor(tokenId, "location");

    const div = document.createElement("div");
    div.id = tokenId;

    let parentNode = $(location);

    if (location && !parentNode) {
      if (location.indexOf("{") == -1) console.error("Cannot find location [" + location + "] for ", div);
      parentNode = $("limbo");
    }
    parentNode.appendChild(div);
    return div;
  }

  updateToken(tokenNode: HTMLElement, placeInfo: TokenMoveInfo) {
    const tokenId = placeInfo.key;
    const displayInfo = this.getTokenDisplayInfo(tokenId);
    const classes = displayInfo.imageTypes.split(/  */);
    tokenNode.classList.add(...classes);
    if (displayInfo.name) tokenNode.dataset.name = this.getTr(displayInfo.name);
    if (displayInfo.tc) tokenNode.dataset.tc = displayInfo.tc;
    this.addListenerWithGuard(tokenNode, placeInfo.onClick);
  }

  addListenerWithGuard(tokenNode: HTMLElement, handler: EventListener) {
    if (!tokenNode.getAttribute("_lis") && handler) {
      tokenNode.addEventListener("click", handler);
      tokenNode.setAttribute("_lis", "1");
    }
  }

  findActiveParent(element: HTMLElement): HTMLElement | null {
    if (this.isActiveSlot(element)) return element;
    const parent = element.parentElement;
    if (!parent || parent.id == "thething" || parent == element) return null;
    return this.findActiveParent(parent);
  }

  /**
   * Click handler helper: resolves the clicked element's id (walking up to an active parent if inside "thething"),
   * checks for help-mode intercept, and returns whether the click is blocked (no active_slot) or actionable.
   */
  onClickSanity(event: Event): { targetId: string; targetNode: HTMLElement; blocked: boolean; active: boolean } {
    let id = (event.currentTarget as HTMLElement).id;
    let target = event.target as HTMLElement;
    if (id == "thething") {
      let node = this.findActiveParent(target);
      id = node?.id;
      target = node;
    }

    const result = {
      targetId: id,
      targetNode: target,
      blocked: true,
      active: false
    };

    console.log("on slot " + id);
    if (!id) return result;
    if (this.showHelp(id)) return result;

    if (!this.checkActiveSlot(id, false)) {
      result.blocked = false;
      return result;
    }
    if (target?.dataset.targetId) result.targetId = target.dataset.targetId;
    result.active = true;
    return result;
  }

  // override to hook the help
  showHelp(id: string) {
    return false;
  }

  // override to prove additinal animation parameters
  getPlaceRedirect(tokenInfo: Token, args: AnimArgs = {}): TokenMoveInfo {
    return tokenInfo;
  }

  checkActivePlayer(): boolean {
    if (!this.bga.players.isCurrentPlayerActive()) {
      this.bga.dialogs.showMessage(_("This is not your turn"), "error");
      return false;
    }
    return true;
  }
  isActiveSlot(id: ElementOrId): boolean {
    const node = $(id);
    if (!node) return false;
    if (node.classList.contains(this.classActiveSlot)) {
      return true;
    }
    if (node.classList.contains(this.classActiveSlotHidden)) {
      return true;
    }
    if (node.id.startsWith("button_")) {
      return true;
    }

    return false;
  }
  checkActiveSlot(id: ElementOrId, showError: boolean = true) {
    if (!this.isActiveSlot(id)) {
      if (showError) {
        console.error(new Error("unauth"), id);
        this.bga.dialogs.showMoveUnauthorized();
      }
      return false;
    }
    return true;
  }

  async placeTokenServer(tokenId: string, location: string, state?: number, args?: any) {
    const tokenInfo = this.setTokenInfo(tokenId, location, state, true, args);
    await this.placeToken(tokenId, tokenInfo, args);
    this.updateTooltip(tokenId, undefined, { force: true });
    this.updateTooltip(tokenInfo.location, undefined, { force: true });
  }

  prepareToken(tokenId: string, tokenDbInfo?: Token, args: AnimArgs = {}): TokenMoveInfo | undefined {
    if (!tokenDbInfo) {
      tokenDbInfo = this.gamedatas.tokens[tokenId];
    }

    if (!tokenDbInfo) {
      let tokenNode = $(tokenId);
      if (tokenNode) {
        const st = parseInt(tokenNode.dataset.state);
        tokenDbInfo = this.setTokenInfo(tokenId, tokenNode.parentElement.id, st, false);
      } else {
        //console.error("Cannot setup token for " + tokenId);
        tokenDbInfo = this.setTokenInfo(tokenId, undefined, 0, false);
      }
    }
    const placeInfo = this.getPlaceRedirect(tokenDbInfo, args);
    const tokenNode = $(tokenId) ?? this.createToken(placeInfo);
    tokenNode.dataset.state = String(tokenDbInfo.state);
    tokenNode.dataset.location = tokenDbInfo.location;
    this.updateToken(tokenNode, placeInfo);
    // no movement
    if (placeInfo.nop) {
      return placeInfo;
    }
    const location = placeInfo.location;
    if (!$(location)) {
      if (location) console.error(`Unknown place ${location} for ${tokenId}`);
      return undefined;
    }
    return placeInfo;
  }

  placeTokenSetup(tokenId: string, tokenDbInfo?: Token) {
    const placeInfo = this.prepareToken(tokenId, tokenDbInfo);

    if (!placeInfo) {
      return;
    }

    const tokenNode = $(tokenId);
    if (!tokenNode) return;
    void placeInfo.onStart?.(tokenNode);
    if (placeInfo.nop) {
      return;
    }
    $(placeInfo.location).appendChild(tokenNode);
    void placeInfo.onEnd?.(tokenNode);
  }

  async placeToken(tokenId: string, tokenDbInfo?: Token, args: AnimArgs = {}) {
    try {
      const placeInfo = this.prepareToken(tokenId, tokenDbInfo, args);

      if (!placeInfo) {
        return;
      }

      const tokenNode = $(tokenId);
      let animTime = placeInfo.duration ?? this.defaultAnimationDuration;

      if (this.game.bgaAnimationsActive() == false || args.noa || placeInfo.noa || placeInfo.duration === 0 || !tokenNode.parentNode) {
        animTime = 0;
      }

      if (placeInfo.onStart) await placeInfo.onStart(tokenNode);
      if (!placeInfo.nop) await this.slideAndPlace(tokenNode, placeInfo.location, animTime, 0, undefined, placeInfo.onEnd);
      else placeInfo.onEnd?.(tokenNode);

      //if (animTime == 0) $(location).appendChild(tokenNode);
      //else void this.animationManager.slideAndAttach(tokenNode, $(location));
    } catch (e) {
      console.error("Exception thrown", e, e.stack);
      // this.showMessage(token + " -> FAILED -> " + place + "\n" + e, "error");
    }
  }

  updateTooltip(tokenId: string, attachTo?: ElementOrId, options: { delay?: number; force?: boolean } = {}) {
    if (attachTo === undefined) {
      attachTo = tokenId;
    }
    let attachNode = $(attachTo);

    if (!attachNode) return;

    // attach node has to have id
    if (!attachNode.id) attachNode.id = "gen_id_" + Math.random() * 10000000;

    // console.log("tooltips for "+token);
    if (typeof tokenId != "string") {
      console.error("cannot calc tooltip" + tokenId);
      return;
    }
    var tokenInfo = this.getTokenDisplayInfo(tokenId, options.force);
    if (tokenInfo.name) {
      attachNode.dataset.name = this.game.getTr(tokenInfo.name);
    }

    if (tokenInfo.showtooltip == false) {
      return;
    }
    if (tokenInfo.title) {
      attachNode.setAttribute("title", this.game.getTr(tokenInfo.title));
      return;
    }

    if (!tokenInfo.tooltip && !tokenInfo.name) {
      return;
    }

    var main = this.getTooltipHtmlForTokenInfo(tokenInfo);

    if (main) {
      attachNode.classList.add("withtooltip");
      if (attachNode.id != tokenId) attachNode.dataset.tt = tokenId; // id of token that provides the tooltip

      //console.log("addTooltipHtml", attachNode.id);
      this.game.addTooltipHtml(attachNode.id, main, options.delay ?? this.game.defaultTooltipDelay);
      attachNode.removeAttribute("title"); // unset title so both title and tooltip do not show up

      this.handleStackedTooltips(attachNode);
    } else {
      attachNode.classList.remove("withtooltip");
    }
  }

  handleStackedTooltips(attachNode: HTMLElement) {}

  getTooltipHtmlForToken(token: string) {
    if (typeof token != "string") {
      console.error("cannot calc tooltip" + token);
      return null;
    }
    var tokenInfo = this.getTokenDisplayInfo(token, true);
    // console.log(tokenInfo);
    if (!tokenInfo) return;
    return this.getTooltipHtmlForTokenInfo(tokenInfo);
  }

  getTooltipHtmlForTokenInfo(tokenInfo: TokenDisplayInfo) {
    return this.getTooltipHtml(tokenInfo.name, tokenInfo.tooltip, tokenInfo.imageTypes, tokenInfo.reverseImageTypes, tokenInfo.imageData);
  }

  getTokenName(tokenId: string, force: boolean = true): string {
    var tokenInfo = this.getTokenDisplayInfo(tokenId);
    if (tokenInfo) {
      return this.game.getTr(tokenInfo.name);
    } else {
      if (!force) return undefined;
      return "? " + tokenId;
    }
  }

  getTooltipHtml(
    name: string | NotificationMessage,
    message: string | NotificationMessage,
    imgTypes: string = "",
    reverseImgTypes: string = "",
    imageData?: Record<string, string>
  ) {
    if (name == null || message == "-") return "";
    if (!message) message = "";
    var divImg = "";
    var containerType = "tooltipcontainer ";
    if (imgTypes && !imgTypes.includes("_nottimage")) {
      // Check if this is a dual-image tooltip (upgrade tiles with front and reverse)
      if (imgTypes.includes("_dual_image") && reverseImgTypes) {
        const frontImgTypes = imgTypes.replace("_dual_image", "").trim();
        divImg = `
          <div class='tooltipimage ${frontImgTypes}'></div>
          <div class='tooltipimage ${reverseImgTypes}'></div>
        `;
      } else {
        const dataAttrs = imageData
          ? Object.entries(imageData)
              .map(([k, v]) => `data-${k}="${v}"`)
              .join(" ")
          : "";
        divImg = `<div class='tooltipimage ${imgTypes}' ${dataAttrs}></div>`;
      }
      var itypes = imgTypes.split(" ");
      for (var i = 0; i < itypes.length; i++) {
        containerType += itypes[i] + "_tooltipcontainer ";
      }
    }
    const name_tr = this.game.getTr(name);

    let body: any = "";
    if (imgTypes.includes("_override")) {
      body = message;
    } else {
      const message_tr = this.game.getTr(message);
      body = `
           <div class='tooltip-left'>${divImg}</div>
           <div class='tooltip-right'>
             <div class='tooltiptitle'>${name_tr}</div>
             <div class='tooltiptext'>${message_tr}</div>
           </div>
    `;
    }

    return `<div class='${containerType}'>
        <div class='tooltip-body'>${body}</div>
    </div>`;
  }

  getTokenState(tokenId: string) {
    var tokenInfo = this.gamedatas.tokens[tokenId];
    return Number(tokenInfo?.state);
  }
  getTokenLocation(tokenId: string) {
    var tokenInfo = this.gamedatas.tokens[tokenId];
    return tokenInfo?.location;
  }

  getAllRules(tokenId: string) {
    return this.getRulesFor(tokenId, "*", null);
  }

  getRulesFor(tokenId: string, field?: string, def?: any) {
    if (field === undefined) field = "r";
    var key = tokenId;
    let chain = [key];
    while (key) {
      var info = this.gamedatas.token_types[key];
      if (info === undefined) {
        key = getParentParts(key);
        if (!key) {
          //console.error("Undefined info for " + tokenId);
          return def;
        }
        chain.push(key);
        continue;
      }
      if (field === "*") {
        info["_chain"] = chain.join(" ");
        return info;
      }
      var rule = info[field];
      if (rule === undefined) return def;
      return rule;
    }
    return def;
  }

  getTokenDisplayInfo(tokenId: string, force: boolean = false): TokenDisplayInfo {
    tokenId = String(tokenId);
    const cache = this.tokenInfoCache[tokenId];
    if (!force && cache) {
      return cache;
    }
    let tokenInfo = this.getAllRules(tokenId);

    if (!tokenInfo) {
      tokenInfo = {
        key: tokenId,
        _chain: tokenId,
        name: tokenId,
        showtooltip: false
      };
    } else {
      tokenInfo = structuredClone(tokenInfo);
    }

    const imageTypes = tokenInfo._chain ?? tokenId ?? "";
    const ita = imageTypes.split(" ");
    const tokenKey = ita[ita.length - 1];

    const parentParts = getParentParts(tokenId);
    tokenInfo.type ??= this.getRulesFor(parentParts, "type", "token");
    const declaredTypes = tokenInfo.type;

    tokenInfo.typeKey = tokenKey; // this is key in token_types structure
    tokenInfo.mainType = getPart(tokenId, 0); // first type
    tokenInfo.imageTypes = `${tokenInfo.mainType} ${declaredTypes} ${imageTypes}`.trim(); // other types used for div

    tokenInfo.location ??= this.getRulesFor(parentParts, "location", undefined);
    const create = tokenInfo.create ?? 0;
    if (create == 3 || create == 4) {
      const prefix = tokenKey.split("_").length;
      tokenInfo.color = getPart(tokenId, prefix);
      tokenInfo.imageTypes += " color_" + tokenInfo.color;
    }

    if (create == 3) {
      const part = getPart(tokenId, -1);
      tokenInfo.imageTypes += " n_" + part;
    }

    if (!tokenInfo.key) {
      tokenInfo.key = tokenId;
    }

    tokenInfo.tokenId = tokenId;

    try {
      this.updateTokenDisplayInfo(tokenInfo);
    } catch (e) {
      console.error(`Failed to update token info for ${tokenId}`, e);
    }
    this.tokenInfoCache[tokenId] = tokenInfo;
    //console.log("cached", tokenId);
    return tokenInfo;
  }

  getTokenPresentaton(type: string, value: string, args?: any, strict?: false): string;
  getTokenPresentaton(type: string, value: string, args: any, strict: true): string | null;
  getTokenPresentaton(type: string, value: string, _args: any = {}, strict: boolean = false): string | null {
    if (type.includes("_div")) return this.createTokenImage(value);
    if (value.includes("wicon")) return this.createTokenImage(value);
    const wicon = this.getRulesFor(value, "wicon", "");
    if (wicon) return this.createTokenImage(value, 0, wicon);
    if (this.getRulesFor(value, "type", "").includes("wicon")) return this.createTokenImage(value);
    if (strict) {
      const rules = this.getRulesFor(value, "*", null);
      if (rules === null) return null;
      if (rules.name) return this.game.getTr(rules.name);
      return null;
    }
    if (type == "reason" && value) {
      return "(" + this.getTokenName(value) + ")";
    }
    return "<b>" + this.getTokenName(value) + "</b>";
  }
  // override to generate dynamic tooltips and such
  updateTokenDisplayInfo(tokenDisplayInfo: TokenDisplayInfo) {}

  ttSection(prefix: string, text: string) {
    if (prefix) return `<p><b>${prefix}:</b> ${text}</p>`;
    else return `<p>${text}</p>`;
  }
  iiSection(text: string) {
    return `<p><i>${text}</i></p>`;
  }
  createTokenImage(tokenId: string, state: number = 0, extraClass: string = "") {
    const span = document.createElement("span");
    span.id = tokenId + "_tt_" + this.globlog++;
    this.updateToken(span, { key: tokenId, location: "log", state });
    if (extraClass) span.classList.add("wicon", ...extraClass.split(/ +/));
    const name = this.getRulesFor(tokenId, "name", null);
    if (name) span.title = this.game.getTr(name);
    return span.outerHTML;
  }

  isMarkedForTranslation(key: string, args: any) {
    if (!args.i18n) {
      return false;
    } else {
      var i = args.i18n.indexOf(key);
      if (i >= 0) {
        return true;
      }
    }
    return false;
  }
  bgaFormatText(log: string, args: any) {
    try {
      if (log && args) {
        // if adding key here and it ends with _name make sure also exclude from rtr in dbSetTokenLocation
        var keys = [
          "token_name",
          "token_name2",
          "char_name",
          "char_name2",
          "token_divs",
          "token_names",
          "place_name",
          "token_div",
          "token2_div",
          "token3_div",
          "reason",
          "token_icon"
        ];
        for (var i in keys) {
          const key = keys[i];
          // console.log("checking " + key + " for " + log);
          if (args[key] === undefined) continue;
          const arg_value = args[key];

          if (key == "token_divs" || key == "token_names") {
            var list = args[key].split(",");
            var res = "";
            for (let l = 0; l < list.length; l++) {
              const value = list[l];
              if (l > 0) res += ", ";
              res += this.getTokenPresentaton(key, value, args);
            }
            res = res.trim();
            if (res) args[key] = res;
            continue;
          }
          res = this.getTokenPresentaton(key, arg_value, args);
          if (res) args[key] = res;
        }
      }
    } catch (e) {
      console.error(log, args, "Exception thrown", e.stack);
    }
    log = this.replaceSimpleIconsInLog(log, args);
    return { log, args };
  }

  replaceSimpleIconsInLog(log: string, args: any = {}): string {
    if (!log || !log.includes("[")) return log;
    return log.replace(/\[([^\]]+)\]/g, (match, keyExpr) => {
      try {
        const x = this.getTokenPresentaton(keyExpr, keyExpr, args, true);
        if (!x) return match;
        return x;
      } catch (e) {
        console.error(`Failed to get token presentation for [${keyExpr}]`, e);
        return match;
      }
    });
  }

  async slideAndPlace(
    token: ElementOrId,
    finalPlace: ElementOrId,
    duration?: number,
    delay: number = 0,
    mobileStyle?: StringProperties,
    onEnd?: (node?: HTMLElement) => void
  ) {
    if (!$(token)) console.error(`token not found for ${token}`);
    if ($(token)?.parentNode == $(finalPlace)) return;
    if (gameui.bgaAnimationsActive() == false) {
      duration = 0;
      delay = 0;
    }
    if (delay) await gameui.wait(delay);
    this.animationLa.phantomMove(token, finalPlace, duration, mobileStyle, onEnd);
    return gameui.wait(duration ?? 0);
  }

  showHiddenContent(id: ElementOrId, title: string, selectedId?: string | number, sort?: any) {
    let dialog = new ebg.popindialog();
    dialog.create("pile");
    dialog.setTitle(title);
    const node = this.cloneAndFixIds(id, "_tt", true);
    node.removeAttribute("_lis");
    const cards_htm = node.innerHTML;
    const html = `
    <div id="card_pile_selector" class="card_pile_selector"></div>
    <div id="card_pile_help" class="card_pile_help">${_("Click on element below to see details")}</div>
    <div id="pile_content" class="pile_content">${cards_htm}</div>`;
    dialog.setContent(html);
    const parent = $("pile_content");

    let children = Array.from(parent.children);
    if (sort) {
      children.sort(sort);
      parent.replaceChildren(...children);
    }
    children.forEach((node: HTMLElement, index) => {
      const origId = node.id.replace("_tt", "");
      node.addEventListener("click", (e) => {
        const selected_html = this.getTooltipHtmlForToken(origId);
        $("card_pile_selector").innerHTML = selected_html;
      });
      if (index === selectedId) selectedId = origId;
    });
    if (children.length == 0) {
      $("card_pile_help").remove();
    }
    if (selectedId && typeof selectedId === "string") {
      const selected_html = this.getTooltipHtmlForToken(selectedId);
      $("card_pile_selector").innerHTML = selected_html;
    }
    dialog.show();
    return dialog;
  }

  async notif_animate(args: any) {
    return gameui.wait(args.time ?? 1);
  }

  async notif_tokenMovedAsync(args: any) {
    void this.notif_tokenMoved(args);
  }

  async notif_tokenMoved(args: any) {
    if (args.list !== undefined) {
      // move bunch of tokens

      const moves = [];
      for (var i = 0; i < args.list.length; i++) {
        var one = args.list[i];
        var new_state = args.new_state;
        if (new_state === undefined) {
          if (args.new_states !== undefined && args.new_states.length > i) {
            new_state = args.new_states[i];
          }
        }
        moves.push(this.placeTokenServer(one, args.place_id, new_state, args));
      }
      return Promise.all(moves);
    } else {
      return this.placeTokenServer(args.token_id, args.place_id, args.new_state, args);
    }
  }
  async notif_counterAsync(args: any) {
    void this.notif_counter(args);
  }

  /**
   * 
   * name: the name of the counter
value: the new value
oldValue: the value before the update
inc: the increment
absInc: the absolute value of the increment, allowing you to use '...loses ${absInc} ...' in the notif message if you are incrementing with a negative value
playerId (only for PlayerCounter)
player_name (only for PlayerCounter)
   * @param args 
   * @returns 
   * 
   */
  async notif_counter(args: any) {
    try {
      const name = args.name;
      const value = args.value;
      const node = $(name);
      if (node && this.gamedatas.tokens[name]) {
        args.nop = true; // no move animation
        return Promise.all([this.placeTokenServer(name, this.gamedatas.tokens[name].location, value, args), gameui.wait(500)]);
      } else if (node) {
        node.dataset.state = value;
      }
    } catch (ex) {
      console.error("Cannot update " + args.counter_name, ex, ex.stack);
    }
    return gameui.wait(500);
  }
}
