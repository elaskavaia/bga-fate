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

import { CustomGamedatas, NotificationMessage } from "./types";

/** Class that extends default bga core game class with more functionality
 */
export type StringProperties = { [key: string]: string };
export class Game0Basics {
  player_color: string;
  defaultTooltipDelay: number = 800;
  public gamedatas!: CustomGamedatas;

  constructor(public bga: Bga) {
    //console.log("game constructor");
  }

  // proxies for GameGui properties/methods accessed via gameui
  get player_id() {
    return gameui.player_id;
  }

  format_string_recursive(log: string, args: any): string {
    return gameui.format_string_recursive(log, args);
  }

  addTooltipHtml(nodeId: string, html: string, delay?: number): void {
    gameui.addTooltipHtml(nodeId, html, delay);
  }

  bgaAnimationsActive(): boolean {
    return gameui.bgaAnimationsActive();
  }

  setup(gamedatas: any) {
    this.gamedatas = gamedatas;
    console.log("Starting game setup", gamedatas);
    const first_player_id = Object.keys(gamedatas.players)[0];
    if (!this.bga.players.isCurrentPlayerSpectator()) this.player_color = gamedatas.players[this.player_id].color;
    else this.player_color = gamedatas.players[first_player_id].color;
  }

  // utils
  /**
   * Remove all listed class from all document elements
   * @param classList - list of classes  array
   */
  removeAllClasses(...classList: string[]) {
    if (!classList) return;

    classList.forEach((className) => {
      document.querySelectorAll(`.${className}`).forEach((node) => {
        node.classList.remove(className);
      });
    });
  }

  onCancel(event?: Event) {
    this.cancelLocalStateEffects();
  }

  cancelLocalStateEffects() {
    //console.log(this.last_server_state);

    if (gameui.on_client_state) gameui.restoreServerGameState();

    gameui.updatePageTitle();
  }

  destroyDivOtherCopies(id: string) {
    const panels = document.querySelectorAll("#" + id);
    panels.forEach((p, i) => {
      if (i < panels.length - 1) p.parentNode.removeChild(p);
    });
    return panels[0] ?? null;
  }

  setupLocalControls(divId: string) {
    // undo adds more of these
    this.destroyDivOtherCopies(divId);
    if (this.bga.players.isCurrentPlayerSpectator()) {
      const loc = document.querySelector("#right-side .spectator-mode");
      if (loc) loc.insertAdjacentElement("beforeend", $(divId));
    } else {
      const loc = document.querySelector("#current_player_board");
      if (loc) loc.insertAdjacentElement("beforeend", $(divId));
    }
  }

  addCancelButton(name?: string, handler?: any) {
    if (!name) name = _("Cancel");
    if (!handler) handler = () => this.onCancel();
    if ($("button_cancel")) $("button_cancel").remove();
    this.bga.statusBar.addActionButton(name, handler, { id: "button_cancel", color: "alert" });
  }

  /** Show pop in dialog. If you need div id of dialog its `popin_${id}` where id is second parameter here */
  showPopin(html: string, id = "gg_dialog", title: string = undefined, refresh: boolean = false) {
    const content_id = `popin_${id}_contents`;
    if (refresh && $(content_id)) {
      $(content_id).innerHTML = html;
      return undefined;
    }

    const dialog = new ebg.popindialog();
    dialog.create(id);
    if (title) dialog.setTitle(title);
    dialog.setContent(html);
    dialog.show();
    return dialog;
  }

  getStateName() {
    return this.gamedatas.gamestate.name;
  }

  getPlayerColor(playerId: number) {
    return this.gamedatas.players[playerId]?.color ?? "ffffff";
  }

  getPlayerName(playerId: number) {
    return this.gamedatas.players[playerId]?.name ?? _("Not a Player");
  }

  custom_getPlayerIdByColor(color: string): number | undefined {
    for (var playerId in this.gamedatas.players) {
      var playerInfo = this.gamedatas.players[playerId];
      if (color == playerInfo.color) {
        return parseInt(playerId);
      }
    }
    return undefined;
  }
  removeTooltip(nodeId: string): void {
    // if (this.tooltips[nodeId])
    if (!nodeId) return;
    //console.log("removeTooltip", nodeId);
    $(nodeId)?.classList.remove("withtooltip");
    gameui.removeTooltip(nodeId);
    delete (gameui as any).tooltips[nodeId]; // HACK: removeTooltip leaking this entry, removing manually
  }

  /** @Override onScriptError from gameui */
  onScriptError(msg: any, url: any, linenumber: any) {
    if ((gameui as any).page_is_unloading) {
      // Don't report errors during page unloading
      return;
    }
    console.error(msg);
  }

  divYou() {
    var color = "black";
    var color_bg = "";
    if (this.gamedatas.players[this.player_id]) {
      color = this.gamedatas.players[this.player_id].color;
    }
    if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
      color_bg = "background-color:#" + this.gamedatas.players[this.player_id].color_back + ";";
    }
    var you = '<span style="font-weight:bold;color:#' + color + ";" + color_bg + '">' + _("You") + "</span>";
    return you;
  }

  cloneAndFixIds(orig: ElementOrId, postfix: string, removeInlineStyle?: boolean) {
    if (!$(orig)) {
      const div = document.createElement("div");
      div.innerHTML = _("NOT FOUND") + " " + orig.toString();
      return div;
    }
    const fixIds = function (node: HTMLElement) {
      if (node.id) {
        node.id = node.id + postfix;
      }
      if (removeInlineStyle) {
        node.removeAttribute("style");
      }
    };
    const div = $(orig).cloneNode(true) as HTMLElement;
    div.querySelectorAll("*").forEach(fixIds);
    fixIds(div);

    return div;
  }

  getTr(name: string | NotificationMessage, args: any = {}) {
    if (!name) return "";

    if ((name as any).log !== undefined) {
      const notif = name as NotificationMessage;
      const log = this.format_string_recursive((gameui as any).clienttranslate_string(notif.log), notif.args);
      return log;
    }
    if (typeof name !== "string") return name.toString();

    //if (name.includes("$"))
    {
      const log = this.format_string_recursive((gameui as any).clienttranslate_string(name) as string, args);
      return log;
    }

    //return this.clienttranslate_string(name);
  }
  setSubPrompt(text: string, args: any = {}) {
    if (!text) text = "";
    if (!args) args = [];
    const message = this.format_string_recursive(this.getTr(text, args), args);

    // have to set after otherwise status update wipes it
    setTimeout(() => {
      $("gameaction_status").innerHTML = `<div class="subtitle">${message}</div>`;
    }, 100);
  }

  reloadCss() {
    var links = document.getElementsByTagName("link");
    for (var cl in links) {
      var link = links[cl];
      if (link.rel === "stylesheet" && link.href.includes("99999")) {
        var index = link.href.indexOf("?timestamp=");
        var href = link.href;
        if (index >= 0) {
          href = href.substring(0, index);
        }
        link.href = href + "?timestamp=" + Date.now();
        console.log("reloading " + link.href);
      }
    }
  }
  isSolo() {
    return this.gamedatas.playerorder.length == 1;
  }

  protected lastMoveId = 0;
  private prevLogId = 0;
  addTooltipToLogItems(log_id: number) {
    // override
  }

  addMoveToLog(log_id: number, move_id: number) {
    if (move_id) this.lastMoveId = move_id;
    if (this.prevLogId + 1 < log_id) {
      // we skip over some logs, but we need to look at them also
      for (let i = this.prevLogId + 1; i < log_id; i++) {
        this.addTooltipToLogItems(i);
      }
    }

    this.addTooltipToLogItems(log_id);

    // add move #
    var prevmove = document.querySelector('[data-move-id="' + move_id + '"]');
    if (prevmove) {
      // ?
    } else if (move_id) {
      const tsnode = document.createElement("div");
      tsnode.classList.add("movestamp");
      tsnode.innerHTML = _("Move #") + move_id;
      const lognode = $("log_" + log_id);
      lognode.appendChild(tsnode);

      tsnode.setAttribute("data-move-id", String(move_id));
    }
    this.prevLogId = log_id;
  }

  notif_log(args: any, notif: Notif) {
    // if (notif.log) {
    //   console.log(notif.log, notif.args);
    //   var message = this.format_string_recursive(notif.log, notif.args);
    //   if (message != notif.log) console.log(message);
    // } else {
    if (notif.log) {
      var message = this.format_string_recursive(notif.log, notif.args);
      delete notif.log;
      console.log("debug log", message, args);
    } else {
      console.log("hidden log", args);
    }
  }

  notif_message_warning(notif: Notif) {
    if (gameui.bgaAnimationsActive()) {
      var message = this.format_string_recursive(notif.log, notif.args);
      this.bga.dialogs.showMessage(_("Warning:") + " " + message, "info");
    }
  }

  notif_message_info(notif: Notif) {
    if (gameui.bgaAnimationsActive()) {
      var message = this.format_string_recursive(notif.log, notif.args);
      this.bga.dialogs.showMessage(_("Announcement:") + " " + message, "info");
    }
  }
}

/** This is essentically dojo.place but without dojo */
export function placeHtml(html: string, parent: ElementOrId, how: InsertPosition = "beforeend") {
  $(parent).insertAdjacentHTML(how, html);
}
export function getIntPart(word: string, i: number) {
  return parseInt(getPart(word, i));
}
export function getPart(word: string, i: number) {
  var arr = word.split("_");
  if (i < 0) i = arr.length + i;
  if (arr.length <= i) return "";
  return arr[i];
}
export function getFirstParts(word: string, count: number) {
  var arr = word.split("_");
  var res = arr[0];
  for (var i = 1; i < arr.length && i < count; i++) {
    res += "_" + arr[i];
  }
  return res;
}
export function getParentParts(word: string) {
  var arr = word.split("_");
  if (arr.length <= 1) return "";
  return getFirstParts(word, arr.length - 1);
}
