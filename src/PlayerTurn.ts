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

import { getPart } from "./Game0Basics";
import { GameMachine } from "./GameMachine";
import type { Game } from "./Game";
import { OpInfo, ParamInfo } from "./types";

type OpRenderer = (opInfo: OpInfo) => void;

export class PlayerTurn extends GameMachine {
  private onLeavingHook?: () => void;

  constructor(game: Game, bga: Bga) {
    super(game, bga);
  }

  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    if (args._private) {
      // merge private
      const priv = args._private;
      delete args._private;
      this.onEnteringStatePrivate({ ...args, ...priv });
    } else {
      this.onEnteringStatePrivate(args);
    }
  }

  onEnteringStatePrivate(opInfo: OpInfo) {
    this.runLeavingHook();
    $("ebd-body").dataset.optype = opInfo.type;
    super.onEnteringStatePrivate(opInfo);
    const renderer = (this as any)[`onEntering_Op_${opInfo.type}`] as OpRenderer | undefined;
    if (renderer) renderer.call(this, opInfo);
  }

  onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    super.onLeavingState(args);
    this.runLeavingHook();
  }

  private runLeavingHook() {
    const hook = this.onLeavingHook;
    this.onLeavingHook = undefined;
    if (hook) hook();
    $("ebd-body").dataset.optype = undefined;
  }

  onPlayerActivationChange(args: any, isCurrentPlayerActive: boolean) {}

  onEntering_Op_turn(opInfo: OpInfo) {
    let anyActiveHexes = false;
    document.querySelectorAll(".hex").forEach((node: any) => {
      if (opInfo.info[node.id]) {
        node.dataset.action = (opInfo.info[node.id] as any).action;
        anyActiveHexes = true;
      }
    });
    if (anyActiveHexes) {
      $("map_area").classList.add("fadeinactive");
    }
    this.onLeavingHook = () => {
      document.querySelectorAll(".hex").forEach((node: any) => {
        node.dataset.action = undefined;
      });
      $("map_area").classList.remove("fadeinactive");
    };
  }

  createCustomButtonImageHtml(target: string, paramInfo: ParamInfo): string | undefined {
    if (target.startsWith("action")) {
      const opKey = `Op_${target}`;
      const icon = this.game.getRulesFor(opKey, "wicon", "");
      const name = this.game.getTokenName(opKey);
      const q = paramInfo.q;
      const iconHtml = icon ? `<div class="wicon ${icon}"></div>` : "";
      return `<div id='${target}' class="fateaction err_${q}">${iconHtml}<span>${name}</span></div>`;
    }

    return super.createCustomButtonImageHtml(target, paramInfo);
  }

  onToken_nonActive(target: string, node: HTMLElement) {
    if (!target) return false;
    if (!$(target)) return false;
    const mainType = getPart(target, 0);
    switch (mainType) {
      case "card": {
        const container = $(target)!.parentElement?.id;
        if (container?.startsWith("discard") || container?.startsWith("deck")) {
          this.game.showHiddenContent(container, _("Pile contents"), 0, function (a: HTMLElement, b: HTMLElement) {
            const orderA = parseInt(a.dataset.state);
            const orderB = parseInt(b.dataset.state);
            return -orderA + orderB; // descending
          });
          return false;
        }
        break;
      }
    }
    return true;
  }
}
