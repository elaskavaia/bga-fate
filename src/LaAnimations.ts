import { placeHtml, StringProperties } from "./Game0Basics";

export class LaAnimations {
  public defaultAnimationDuration: number = 500;
  phantomMove(
    mobileId: ElementOrId,
    newparentId: ElementOrId,
    duration?: number,
    mobileStyle?: StringProperties,
    onEnd?: (node?: HTMLElement) => void
  ) {
    var mobileNode = $(mobileId) as HTMLElement;

    if (!mobileNode) throw new Error(`Does not exists ${mobileId}`);
    var newparent = $(newparentId);
    if (!newparent) throw new Error(`Does not exists ${newparentId}`);
    if (duration === undefined) duration = this.defaultAnimationDuration;
    if (!duration || duration < 0) duration = 0;
    const noanimation = duration <= 0 || !mobileNode.parentNode;
    const oldParent = mobileNode.parentElement;
    var clone = null;
    if (!noanimation) {
      // do animation
      clone = this.projectOnto(mobileNode, "_temp");
      mobileNode.style.opacity = "0"; // hide original
    }

    const rel = mobileStyle?.relation;
    if (rel) {
      delete mobileStyle.relation;
    }
    if (rel == "first") {
      newparent.insertBefore(mobileNode, null);
    } else {
      newparent.appendChild(mobileNode); // move original
    }

    setStyleAttributes(mobileNode, mobileStyle);
    newparent.classList.add("move_target");
    oldParent?.classList.add("move_source");
    mobileNode.offsetHeight; // recalc

    if (noanimation) {
      setTimeout(() => {
        newparent.offsetHeight;
        newparent.classList.remove("move_target");
        oldParent?.classList.remove("move_source");
        if (onEnd) onEnd(mobileNode);
      }, 0);
      return;
    }

    var desti = this.projectOnto(mobileNode, "_temp2"); // invisible destination on top of new parent
    try {
      //setStyleAttributes(desti, mobileStyle);
      clone.style.transitionDuration = duration + "ms";
      clone.style.transitionProperty = "all";
      clone.style.visibility = "visible";
      clone.style.opacity = "1";
      // that will cause animation
      clone.style.left = desti.style.left;
      clone.style.top = desti.style.top;
      clone.style.transform = desti.style.transform;
      // now we don't need destination anymore
      desti.parentNode?.removeChild(desti);
      setTimeout(() => {
        newparent.classList.remove("move_target");
        oldParent?.classList.remove("move_source");
        mobileNode.style.removeProperty("opacity"); // restore visibility of original
        clone.parentNode?.removeChild(clone); // destroy clone
        if (onEnd) onEnd(mobileNode);
      }, duration);
    } catch (e) {
      // if bad thing happen we have to clean up clones
      console.error("ERR:C01:animation error", e);
      desti.parentNode?.removeChild(desti);
      clone.parentNode?.removeChild(clone); // destroy clone
      //if (onEnd) onEnd(mobileNode);
    }
  }

  getFulltransformMatrix(from: Element, to: Element) {
    let fullmatrix = "";
    let par = from;

    while (par != to && par != null && par != document.body) {
      var style = window.getComputedStyle(par as Element);
      var matrix = style.transform; //|| "matrix(1,0,0,1,0,0)";

      if (matrix && matrix != "none") fullmatrix += " " + matrix;
      par = par.parentNode as Element;
      // console.log("tranform  ",fullmatrix,par);
    }

    return fullmatrix;
  }

  projectOnto(from: ElementOrId, postfix: string, ontoWhat?: ElementOrId) {
    const elem: Element = $(from);
    let over: Element;
    if (ontoWhat) over = $(ontoWhat);
    else over = $("oversurface"); // this div has to exists with pointer-events: none and cover all area with high zIndex
    var elemRect = elem.getBoundingClientRect();

    //console.log("elemRect", elemRect);

    var newId = elem.id + postfix;
    var old = $(newId);
    if (old) old.parentNode.removeChild(old);

    var clone = elem.cloneNode(true) as HTMLElement;
    clone.id = newId;
    clone.classList.add("phantom");
    clone.classList.add("phantom" + postfix);
    clone.style.transitionDuration = "0ms"; // disable animation during projection

    var fullmatrix = this.getFulltransformMatrix(elem.parentNode as Element, over.parentNode as Element);

    // Calculate the scale factor of oversurface relative to viewport
    // This handles cases where oversurface or its ancestors are scaled
    const overElement = over as HTMLElement;
    const overRect = over.getBoundingClientRect();
    const scaleX = overElement.offsetWidth > 0 ? overRect.width / overElement.offsetWidth : 1;
    const scaleY = overElement.offsetHeight > 0 ? overRect.height / overElement.offsetHeight : 1;

    // Set dimensions adjusted for scale so clone appears same visual size as original
    if (elemRect.width > 1) {
      clone.style.width = elemRect.width / scaleX + "px";
      clone.style.height = elemRect.height / scaleY + "px";
    }

    // Set initial position before appending so we measure from a known baseline
    clone.style.position = "absolute";
    clone.style.left = "0px";
    clone.style.top = "0px";
    over.appendChild(clone);
    var cloneRect = clone.getBoundingClientRect();

    const centerY = elemRect.y + elemRect.height / 2;
    const centerX = elemRect.x + elemRect.width / 2;
    // centerX/Y is where the center point must be
    // I need to calculate the offset from top and left
    // Therefore I remove half of the dimensions + the existing offset
    const offsetX = centerX - cloneRect.width / 2 - cloneRect.x;
    const offsetY = centerY - cloneRect.height / 2 - cloneRect.y;

    // Then remove the clone's parent position (since left/top is from the parent)
    // Divide by scale factor to convert from viewport pixels to CSS pixels
    clone.style.left = offsetX / scaleX + "px";
    clone.style.top = offsetY / scaleY + "px";
    clone.style.transform = fullmatrix;
    clone.style.transitionDuration = undefined;

    return clone;
  }

  /**
   * Pulse an element: scale up then back to normal size.
   * If called again while already pulsing, queues the next pulse after the current one.
   */
  pulse(targetId: ElementOrId, scale: number = 2, duration: number = 400) {
    const node = $(targetId) as HTMLElement;
    if (!node) return;
    const pending = Number(node.dataset.pulseQueue || 0);
    if (pending > 0) {
      node.dataset.pulseQueue = String(pending + 1);
      return;
    }
    node.dataset.pulseQueue = "1";
    this.doPulse(node, scale, duration);
  }

  private doPulse(node: HTMLElement, scale: number, duration: number) {
    const half = duration / 2;
    node.style.transitionDuration = half + "ms";
    node.style.transitionProperty = "transform";
    node.style.transitionTimingFunction = "ease-out";
    node.offsetHeight;
    node.style.transform = `scale(${scale})`;
    setTimeout(() => {
      node.style.transitionTimingFunction = "ease-in";
      node.style.transform = "";
      setTimeout(() => {
        const remaining = Number(node.dataset.pulseQueue || 0) - 1;
        if (remaining > 0) {
          node.dataset.pulseQueue = String(remaining);
          this.doPulse(node, scale, duration);
        } else {
          delete node.dataset.pulseQueue;
          node.style.removeProperty("transition-duration");
          node.style.removeProperty("transition-property");
          node.style.removeProperty("transition-timing-function");
        }
      }, half);
    }, half);
  }

  /**
   * Clone an element, position it over a target, then float up and fade out.
   * The original element is not affected.
   */
  evaporate(mobileId: ElementOrId, targetId: ElementOrId, duration?: number) {
    const mobileNode = $(mobileId) as HTMLElement;
    const targetNode = $(targetId) as HTMLElement;
    if (!mobileNode || !targetNode) return;
    if (duration === undefined) duration = 1200;

    // Project a clone of the target to get its position on oversurface
    const targetClone = this.projectOnto(targetNode, "_evap_dest");
    const targetLeft = targetClone.style.left;
    const targetTop = targetClone.style.top;
    targetClone.remove();

    // Project a clone of the mobile onto oversurface
    const clone = this.projectOnto(mobileNode, "_evap");
    // Reposition clone over the target (centered horizontally, above vertically)
    clone.style.left = targetLeft;
    clone.style.top = targetTop;
    clone.style.pointerEvents = "none";
    clone.offsetHeight; // force reflow

    // Animate: float up + fade out
    clone.style.transitionDuration = duration + "ms";
    clone.style.transitionProperty = "opacity, transform";
    clone.style.transitionTimingFunction = "ease-out";
    clone.offsetHeight; // force reflow
    clone.style.opacity = "0";
    clone.style.transform = (clone.style.transform || "") + " translateY(-60px) scale(1.3)";

    setTimeout(() => clone.remove(), duration);
  }

  /**
   * Shrink and fade an element in place.
   * The element is hidden (opacity 0) during the animation; a clone performs the visual effect.
   */
  shrinkAndFade(mobileId: ElementOrId, duration?: number): Promise<void> {
    const mobileNode = $(mobileId) as HTMLElement;
    if (!mobileNode) return Promise.resolve();
    if (duration === undefined) duration = 600;

    const clone = this.projectOnto(mobileNode, "_shrink");
    clone.style.pointerEvents = "none";
    mobileNode.style.opacity = "0";
    clone.offsetHeight; // force reflow

    clone.style.transitionDuration = duration + "ms";
    clone.style.transitionProperty = "opacity, transform";
    clone.style.transitionTimingFunction = "ease-in";
    clone.offsetHeight; // force reflow
    clone.style.opacity = "0";
    clone.style.transform = (clone.style.transform || "") + " scale(0)";

    return new Promise((resolve) => {
      setTimeout(() => {
        clone.remove();
        mobileNode.style.removeProperty("opacity");
        resolve();
      }, duration);
    });
  }

  cardFlip(mobileId: ElementOrId, newState: string, duration?: number, onEnd?: (node?: HTMLElement) => void) {
    var mobileNode = $(mobileId) as HTMLElement;

    if (!mobileNode) throw new Error(`Does not exists ${mobileId}`);

    if (duration === undefined) duration = this.defaultAnimationDuration;
    if (!duration || duration < 0) duration = 0;
    const noanimation = duration <= 0 || !mobileNode.parentNode;
    if (noanimation) {
      mobileNode.dataset.state = newState;
      setTimeout(() => {
        if (onEnd) onEnd(mobileNode);
      }, 0);
      return;
    }

    const clone = this.projectOnto(mobileNode, "_temp");
    clone.innerHTML = "";

    mobileNode.dataset.state = newState;
    mobileNode.offsetHeight; // recalc
    const desti = this.projectOnto(mobileNode, "_temp2"); // invisible destination on top of new parent
    desti.innerHTML = "";
    mobileNode.style.opacity = "0"; // hide original
    placeHtml(`<div id="card_temp"></div>`, "oversurface");
    const group = $("card_temp");

    group.style.left = clone.style.left;
    group.style.top = clone.style.top;
    group.style.transform = clone.style.transform;
    group.style.width = clone.style.width;
    group.style.height = clone.style.height;
    group.style.position = "absolute";
    group.style.transformStyle = "preserve-3d";
    group.style.transitionProperty = "all";

    group.appendChild(clone);
    group.appendChild(desti);
    delete clone.style.left;
    delete clone.style.top;
    delete desti.style.left;
    delete desti.style.top;
    desti.style.transform = "rotateY(180deg)";
    desti.style.backfaceVisibility = "hidden";
    clone.style.backfaceVisibility = "hidden";

    try {
      //setStyleAttributes(desti, mobileStyle);
      group.style.transitionDuration = duration + "ms";

      //group.style.visibility = "visible";
      //group.style.opacity = "1";
      // that will cause animation
      //group.style.scale = "2.0";
      group.style.animation = `flip ${duration}ms`;

      setTimeout(() => {
        mobileNode.style.removeProperty("opacity"); // restore visibility of original
        group.remove();
        if (onEnd) onEnd(mobileNode);
      }, duration);
    } catch (e) {
      // if bad thing happen we have to clean up clones
      console.error("ERR:C01:animation error", e);
      group.remove();
      if (onEnd) onEnd(mobileNode);
    }
  }
}

function setStyleAttributes(element: HTMLElement, attrs: { [key: string]: string }): void {
  if (attrs !== undefined) {
    Object.keys(attrs).forEach((key: string) => {
      element.style.setProperty(key, attrs[key]);
    });
  }
}
