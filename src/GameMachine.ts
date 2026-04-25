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

import type { Game } from "./Game";
import { OpInfo, ParamInfo } from "./types";

/**  Generic processing related to Operation Machine */
export class GameMachine {
  game: Game;
  bga: Bga;
  opInfo?: OpInfo;

  constructor(game: Game, bga: Bga) {
    this.game = game;
    this.bga = bga;
  }

  callfn(methodName: string, ...args: any) {
    if (this[methodName] !== undefined) {
      console.log("Calling " + methodName, args);
      return this[methodName](...args);
    }
    return undefined;
  }

  onEnteringStatePrivate(opInfo: OpInfo) {
    console.log("onEnteringStatePrivate", opInfo);
    if (!this.bga.players.isCurrentPlayerActive()) {
      if (opInfo?.description) this.bga.statusBar.setTitle(this.game.getTr(opInfo.description, opInfo));
      this.addUndoButton(opInfo?.ui?.undo); // opInfo not sanitized on this path
      return;
    }
    this.completeOpInfo(opInfo);
    this.opInfo = opInfo;

    const prompt = opInfo.prompt ? this.game.getTr(opInfo.prompt, opInfo) : "";
    let subprompt = "";
    if (opInfo.err) {
      subprompt = _("Error") + ": " + this.game.getTr(opInfo.err, opInfo);
    } else if (opInfo.data?.reason) {
      subprompt = this.getReasonText(opInfo.data.reason);
    }

    if (subprompt && prompt) {
      this.bga.statusBar.setTitle(`[${subprompt}] ${prompt}`);
    } else if (prompt) {
      this.bga.statusBar.setTitle(prompt);
    }

    const multiselect = this.isMultiSelectArgs(opInfo);

    const sortedTargets = Object.keys(opInfo.info);
    sortedTargets.sort((a, b) => opInfo.info[a].o - opInfo.info[b].o);

    for (const target of sortedTargets) {
      const paramInfo = opInfo.info[target];
      if (paramInfo.sec) {
        continue; // secondary buttons
      }
      const altTarget = paramInfo.tokenIdUi;
      const div = $(target) ?? $(altTarget);
      const q = paramInfo.q;
      const active = q == 0;

      // simple case we select element (dom node) which is target of operation
      if (div && active && paramInfo.noactive !== true) {
        const doNotShowActive = paramInfo.noactive ?? opInfo.ui.noactive ?? false;
        if (doNotShowActive == false) {
          div.classList.add(this.game.classActiveSlot);
          div.dataset.targetOpType = opInfo.type;
        }
      }

      // we also can have one addition way of selection (possibly)
      let altNode: HTMLElement | undefined;

      if (opInfo.ui.replicate == true || paramInfo.replicate == true) {
        altNode = this.replicateTargetOnSelectionArea(target, paramInfo);
      }

      if (opInfo.ui.imagebuttons == true || paramInfo.imagebuttons == true) {
        altNode = this.replicateTargetOnToolbar(target, paramInfo);
      }

      if (!altNode && (opInfo.ui.buttons || !div)) {
        altNode = this.createTargetButton(target, paramInfo);
      }

      if (!altNode) continue;

      altNode.dataset.targetId = target;
      altNode.dataset.targetOpType = opInfo.type;
      if (!active) {
        altNode.title = this.game.getTr(paramInfo.err ?? _("Operation cannot be performed now"), paramInfo);
        altNode.classList.add(this.game.classButtonDisabled);
      } else {
        const title = paramInfo.tooltip;
        if (title) altNode.title = this.game.getTr(title, paramInfo);
        else this.game.updateTooltip(altTarget ?? target, altNode);
      }

      if (paramInfo.max !== undefined) {
        altNode.dataset.max = String(paramInfo.max);
      } else {
        altNode.dataset.max = "1";
      }
    }

    // secondary buttons
    for (const target of sortedTargets) {
      const paramInfo = opInfo.info[target];
      if (paramInfo.sec) {
        // skip, whatever TODO: anytime
        const color: any = paramInfo.color ?? "secondary";
        const call = (paramInfo as any).call ?? target;
        const button = this.bga.statusBar.addActionButton(
          this.getTargetButtonName(target, paramInfo),
          () =>
            this.bga.actions.performAction(`action_${call}`, {
              data: JSON.stringify({ target })
            }),
          {
            color: color,
            id: "button_" + target,
            confirm: this.game.getTr((paramInfo as any).confirm)
          }
        );
        button.dataset.targetId = target;
        if (paramInfo.q) button.classList.add(this.game.classButtonDisabled);
      }
    }

    if (multiselect) {
      this.activateMultiSelectPrompt(opInfo);
    }

    if (opInfo.ui.buttons == false || opInfo.ui.replicate) {
      this.game.addShowMeButton(true);
    }

    if (opInfo.subtitle) {
      this.addInfoButton(this.game.getTr(opInfo.subtitle, opInfo));
    }

    // need a global condition when this can be added
    this.addUndoButton(this.bga.players.isCurrentPlayerActive() || opInfo.ui.undo);
  }

  createTargetButton(target: string, paramInfo: ParamInfo): HTMLElement | undefined {
    const q = paramInfo.q;
    const active = q == 0;
    const color: any = paramInfo.color ?? this.opInfo?.ui.color;
    const button = this.bga.statusBar.addActionButton(this.getTargetButtonName(target, paramInfo), (event: Event) => this.onToken(event), {
      color: color,
      disabled: !active,
      id: "button_" + target
    });
    return button;
  }
  replicateTargetOnToolbar(target: string, paramInfo: ParamInfo): HTMLElement | undefined {
    const q = paramInfo.q;
    const active = q == 0;
    const color: any = paramInfo.color ?? "secondary";
    const div = $(target);
    let cloneHtml = this.createCustomTargetImageHtml(target, paramInfo);
    if (!cloneHtml) {
      return undefined;
    }

    const button = this.bga.statusBar.addActionButton(cloneHtml, (event: Event) => this.onToken(event), {
      color,
      disabled: !active,
      id: "button_" + target
    });
    return button;
  }
  createCustomButtonImageHtml(target: string, paramInfo: ParamInfo): string | undefined {
    const altTarget = paramInfo.tokenIdUi;
    if (!altTarget) return undefined;

    const cardId = altTarget;
    if (cardId) {
      let tokenNode = $(cardId);
      if (!tokenNode) {
        this.game.prepareToken(cardId);
        tokenNode = $(cardId);
        tokenNode.id = `${cardId}_temp`;
        return tokenNode?.outerHTML;
      }
      return this.cloneForReplication(tokenNode);
    }
  }

  cloneForReplication(div: HTMLElement) {
    const clone = div.cloneNode(true) as HTMLElement;
    clone.id = div.id + "_temp";
    clone.classList.remove(this.game.classActiveSlot);
    clone.classList.add(this.game.classActiveSlotHidden);
    const cloneHtml = clone.outerHTML;
    return cloneHtml;
  }

  createCustomTargetImageHtml(target: string, paramInfo: ParamInfo): string | undefined {
    let cloneHtml = this.createCustomButtonImageHtml(target, paramInfo);
    if (cloneHtml) return cloneHtml;
    const div = $(target);
    if (div) {
      return this.cloneForReplication(div);
    }
    return undefined;
  }

  replicateTargetOnSelectionArea(target: string, paramInfo: ParamInfo): HTMLElement | undefined {
    let cloneHtml = this.createCustomTargetImageHtml(target, paramInfo);
    if (!cloneHtml) return;
    const parent = document.createElement("div");
    parent.classList.add("target_container");
    parent.innerHTML = cloneHtml;
    $("selection_area").appendChild(parent);
    const child = parent.children.item(0) as HTMLElement;
    child.classList.remove(this.game.classActiveSlot);
    child.classList.add(this.game.classActiveSlotHidden);
    child.addEventListener("click", (event: Event) => this.onToken(event));
    return child;
  }

  getReasonText(reason: string) {
    if (!reason) return "";
    return this.game.getTokenName(reason);
  }
  getTargetButtonName(target: string, paramInfo: ParamInfo) {
    const div = $(target);

    let name = paramInfo.name;
    if (!name && div) {
      name = div.dataset.name;
    }
    if (!name) return this.game.getTokenName(target);
    else return this.game.getTr(name, paramInfo.args ?? paramInfo);
  }

  isMultiSelectArgs(args: OpInfo) {
    return args.ttype == "token_count" || args.ttype == "token_array";
  }
  isMultiCountArgs(args: OpInfo) {
    return args.ttype == "token_count";
  }

  onLeavingState(args?: any, isCurrentPlayerActive?: boolean): void {
    console.log("onLeavingState");
    this.game.removeAllClasses(this.game.classActiveSlot, this.game.classActiveSlotHidden);
    if (!this.bga.states.isOnClientState()) {
      this.game.removeAllClasses(this.game.classSelected, this.game.classSelectedAlt);
    }
    $("button_undo")?.remove();
    // remove children
    $("selection_area").replaceChildren();
  }

  /** default click processor */
  onToken(event: Event, fromMethod?: string) {
    console.log(event);
    let result = this.game.onClickSanity(event);
    if (!result.targetId) {
      return true;
    }
    if (!fromMethod) fromMethod = "onToken";
    event.stopPropagation();
    event.preventDefault();
    const ttype = this.opInfo?.ttype;
    if (!result.active) {
      return this.onToken_nonActive(result.targetId, result.targetNode);
    }
    if (ttype) {
      var methodName = "onToken_" + ttype;
      let ret = this.callfn(methodName, result.targetId, result.targetNode);
      if (ret === undefined) return false;
      return true;
    }
    console.error("no handler for ", ttype);
    return false;
  }

  onToken_nonActive(target: string, node: HTMLElement) {
    return false;
  }

  onToken_token(target: string) {
    if (!target) return false;
    this.resolveAction({ target });
    return true;
  }

  onToken_token_array(target: string, node: HTMLElement) {
    if (!this.opInfo) return false;
    return this.onMultiCount(target, this.opInfo, node);
  }

  onToken_token_count(target: string, node: HTMLElement) {
    if (!this.opInfo) return false;
    return this.onMultiCount(target, this.opInfo, node);
  }

  activateMultiSelectPrompt(opInfo: OpInfo) {
    const ttype = opInfo.ttype;

    const buttonName = _("Submit");
    const doneButtonId = "button_done";
    const resetButtonId = "button_reset";

    this.bga.statusBar.addActionButton(
      buttonName,
      () => {
        const res = {};
        const count = this.getMultiSelectCountAndSync(res);
        if (opInfo.ttype == "token_count") {
          this.resolveAction({ target: res, count });
        } else {
          this.resolveAction({ target: Object.keys(res), count });
        }
      },
      {
        color: "primary",
        id: doneButtonId
      }
    );
    this.bga.statusBar.addActionButton(
      _("Reset"),
      () => {
        const allSel = document.querySelectorAll(`.${this.game.classSelectedAlt},.${this.game.classSelected}`);
        allSel.forEach((node: HTMLElement) => {
          delete node.dataset.count;
        });

        this.game.removeAllClasses(this.game.classSelected, this.game.classSelectedAlt);
        this.onMultiSelectionUpdate(opInfo);
      },
      {
        color: "alert",
        id: resetButtonId
      }
    );

    // this.replicateTokensOnToolbar(opInfo, (target) => {
    //   return this.onMultiCount(target, opInfo);
    // });

    this.onMultiSelectionUpdate(opInfo);

    // this[`onToken_${ttype}`] = (tid: string, o: OpInfo, node: HTMLElement) => {
    //   return this.onMultiCount(tid, opInfo, node);
    // };
  }

  onUpdateActionButtons_PlayerTurnConfirm(args: any) {
    this.bga.statusBar.addActionButton(_("Confirm"), () => this.resolveAction());

    this.addUndoButton();
  }

  resolveAction(args: any = {}) {
    this.bga.actions
      .performAction("action_resolve", {
        data: JSON.stringify(args)
      })
      ?.then((x) => {
        console.log("action complete", x);
      })
      .catch((e: any) => {
        this.game.setActionStatus(e.message, e.args);
      });
  }

  addInfoButton(helpText: string) {
    const escaped = document.createElement("div");
    escaped.textContent = helpText;
    const div = this.bga.statusBar.addActionButton(
      _("Info"),
      () => {
        this.game.showPopin(escaped.innerHTML);
      },
      {
        color: "secondary",
        id: "button_info"
      }
    );
    div.classList.add("button_info");
    div.title = _("Click to see additional information about this prompt");
  }

  addUndoButton(cond: boolean = true) {
    if (!$("button_undo") && !this.bga.players.isCurrentPlayerSpectator() && cond) {
      const div = this.bga.statusBar.addActionButton(
        _("Undo"),
        () =>
          this.bga.actions
            .performAction("action_undo", [], {
              checkAction: false
            })
            ?.catch((e: any) => {
              this.game.setActionStatus(e.message, e.args);
            }),
        {
          color: "alert",
          id: "button_undo"
        }
      );
      div.classList.add("button_undo");
      div.title = _("Undo all possible steps");
      $("undoredo_wrap")?.appendChild(div);

      // const div2 = this.addActionButtonColor("button_undo_last", _("Undo"), () => this.sendActionUndo(-1), "red");
      // div2.classList.add("button_undo");
      // div2.title = _("Undo One Step");
      // $("undoredo_wrap")?.appendChild(div2);
    }
  }

  getMultiSelectCountAndSync(result: any = {}) {
    // sync alternative selection on toolbar
    const allSel = document.querySelectorAll(`.${this.game.classSelected}`);
    const selectedAlt = this.game.classSelectedAlt;
    this.game.removeAllClasses(selectedAlt);
    let totalCount = 0;
    allSel.forEach((node: any) => {
      let altnode = document.querySelector(`[data-target-id="${node.id}"]`);
      // if (!altnode) {
      //   altnode = $(node.dataset.targetId);
      // }
      if (altnode && altnode != node) {
        altnode.classList.add(selectedAlt);
      }
      const cnode = altnode ?? node;
      const tid = cnode.dataset.targetId ?? node.id;
      const count = cnode.dataset.count === undefined ? 1 : Number(cnode.dataset.count);
      result[tid] = count;
      totalCount += count;
    });
    return totalCount;
  }

  onMultiCount(tid: string, opInfo: OpInfo, clicknode: HTMLElement | undefined) {
    if (!tid) return false;
    // Prefer the element whose id matches tid. clicknode may be a child element
    // of the real target (e.g. a monster inside a hex) and must not receive the
    // selection class.
    let node = $(tid) ?? clicknode;
    let altnode: HTMLElement | undefined | null;
    if (clicknode) {
      altnode = $(clicknode.dataset.primaryId);
    }
    if (!altnode) altnode = document.querySelector(`[data-target-id="${tid}"]`);

    const cnode = altnode ?? node;
    const count = Number(cnode.dataset.count ?? 0);
    cnode.dataset.count = String(count + 1);
    const max = Number(cnode.dataset.max ?? 1);

    const selNode = cnode;
    if (count + 1 > max) {
      cnode.dataset.count = "0";
      selNode.classList.remove(this.game.classSelected);
    } else {
      selNode.classList.add(this.game.classSelected);
    }

    this.onMultiSelectionUpdate(opInfo);
    return true;
  }

  onMultiSelectionUpdate(opInfo: OpInfo) {
    const ttype = opInfo.ttype;
    const skippable = false; // XXX
    const doneButtonId = "button_done";
    const resetButtonId = "button_reset";
    const skipButton = $("button_skip");
    const buttonName = _("Submit");

    // sync real selection to alt selection on toolbar
    const count = this.getMultiSelectCountAndSync();

    const doneButton = $(doneButtonId);
    if (doneButton) {
      if ((count == 0 && skippable) || count < opInfo.mcount) {
        doneButton.classList.add(this.game.classButtonDisabled);
        doneButton.title = _("Cannot use this action because insuffient amount of elements selected");
      } else if (count > opInfo.count) {
        doneButton.classList.add(this.game.classButtonDisabled);
        doneButton.title = _("Cannot use this action because superfluous amount of elements selected");
      } else {
        doneButton.classList.remove(this.game.classButtonDisabled);
        doneButton.title = "";
      }
      doneButton.innerHTML = buttonName + ": " + count;
    }
    if (count > 0) {
      $(resetButtonId)?.classList.remove(this.game.classButtonDisabled);

      if (skipButton) {
        skipButton.classList.add(this.game.classButtonDisabled);
        skipButton.title = _("Cannot use this action because there are some elements selected");
      }
    } else {
      $(resetButtonId)?.classList.add(this.game.classButtonDisabled);

      if (skipButton) {
        skipButton.title = "";
        skipButton.classList.remove(this.game.classButtonDisabled);
      }
    }
  }

  completeOpInfo(opInfo: OpInfo) {
    try {
      // server may skip sending some data, this will feel all omitted fields

      if (opInfo.data?.count !== undefined && opInfo.count === undefined) opInfo.count = parseInt(opInfo.data.count);
      if (opInfo.data?.mcount !== undefined && opInfo.mcount === undefined) opInfo.mcount = parseInt(opInfo.data.mcount);
      if (opInfo.void === undefined) opInfo.void = false;
      opInfo.confirm = opInfo.confirm ?? false;

      if (!opInfo.info) opInfo.info = {};
      if (!opInfo.target) opInfo.target = [];
      if (!opInfo.ui) opInfo.ui = {};

      const infokeys = Object.keys(opInfo.info);
      if (infokeys.length == 0 && opInfo.target.length > 0) {
        opInfo.target.forEach((element) => {
          opInfo.info[element] = { q: 0 };
        });
      } else if (infokeys.length > 0 && opInfo.target.length == 0) {
        infokeys.forEach((element) => {
          if (opInfo.info[element].q == 0) opInfo.target.push(element);
        });
      }

      // set default order
      let i = 1;
      for (const target of opInfo.target) {
        const paramInfo = opInfo.info[target];
        if (!paramInfo.o) paramInfo.o = i;
        i++;
      }

      if (opInfo.info.confirm && !opInfo.info.confirm.name) {
        opInfo.info.confirm.name = _("Confirm");
      }
      if (opInfo.info.skip && !opInfo.info.skip.name) {
        opInfo.info.skip.name = _("Skip");
      }
      if (this.isMultiSelectArgs(opInfo)) {
        opInfo.ui.replicate ??= true;
        opInfo.ui.color ??= "secondary";
      } else {
        opInfo.ui.color ??= "primary";
      }
      if (opInfo.ui.buttons === undefined && !opInfo.ui.replicate) {
        opInfo.ui.buttons = true;
      }
    } catch (e) {
      console.error(e);
    }
  }
}
