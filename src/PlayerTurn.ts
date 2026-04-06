import { getPart } from "./Game0Basics";
import { GameMachine } from "./GameMachine";
import type { Game } from "./Game";
import { ParamInfo } from "./types";

export class PlayerTurn extends GameMachine {
  constructor(game: Game, bga: Bga) {
    super(game, bga);
  }

  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    if (args._private) {
      // merge private
      const priv = args._private;
      delete args._private;
      super.onEnteringStatePrivate({ ...args, ...priv });
    } else {
      super.onEnteringStatePrivate(args);
    }
  }

  onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    super.onLeavingState(args);
  }

  onPlayerActivationChange(args: any, isCurrentPlayerActive: boolean) {}

  createCustomButtonImageHtml(target: string, paramInfo: ParamInfo): string | undefined {
    if (target.startsWith("action")) {
      let icon = "";
      switch (target) {
        case "actionMove":
          icon = "wicon_move";
          break;
        case "actionAttack":
          icon = "wicon_strength";
          break;
        case "actionMend":
          icon = "wicon_damage";
          break;
        case "actionPrepare":
          icon = "wicon_hand";
          break;
        case "actionFocus":
          icon = "wicon_mana";
          break;
        case "actionPractice":
          icon = "wicon_gold";
          break;
      }
      const name = this.game.getRulesFor(`Op_${target}`, "name");
      const iconHtml = icon ? `<div class="wicon ${icon}"></div>` : "";
      return `<div id='${target}' class="fateaction">${iconHtml}<span>${name}</span></div>`;
    }
    return undefined;
  }

  onToken_nonActive(target: string, node: HTMLElement) {
    if (!target) return false;
    const mainType = getPart(target, 0);
    switch (mainType) {
      case "card": {
        const cardType = getPart(target, 1);
        const container = $(target).parentElement?.id;
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
