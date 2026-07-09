/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * Floating hand controls: lets the current player's hand either float (fixed at
 * the bottom of the screen, collapsible) or sit parked on the table where it was
 * originally placed. Mode + open/closed state persist in localStorage.
 *
 * The floating host lives OUTSIDE the zoomed board (`thething`) so `position:
 * fixed` anchors to the viewport instead of the scaled board.
 * -----
 */

type HandPlace = "floating" | "parked";

export interface LaHandOptions {
  // the hand wrapper element to relocate (e.g. the ".hand_wrapper" div)
  handWrapper: HTMLElement;
  // where the hand sits when parked (its original parent)
  parkedHome: HTMLElement;
  // where the floating host is attached (outside the zoomed board)
  floatHostParent: HTMLElement;
  // prefix for localStorage keys (e.g. "fate")
  storagePrefix: string;
}

export class LaHand {
  private place: HandPlace = "floating";
  private open: boolean = true;
  private readonly wrapper: HTMLElement;
  private readonly parkedHome: HTMLElement;
  private floatHost!: HTMLElement;
  private readonly storagePrefix: string;

  constructor(opts: LaHandOptions) {
    this.wrapper = opts.handWrapper;
    this.parkedHome = opts.parkedHome;
    this.storagePrefix = opts.storagePrefix;

    this.floatHost = document.createElement("div");
    this.floatHost.id = "hand_float_host";
    this.floatHost.className = "hand_float_host";
    opts.floatHostParent.appendChild(this.floatHost);
  }

  private get placeKey() {
    return `${this.storagePrefix}_hand_place`;
  }
  private get openKey() {
    return `${this.storagePrefix}_hand_open`;
  }

  setup() {
    this.place = localStorage.getItem(this.placeKey) === "parked" ? "parked" : "floating";
    this.open = localStorage.getItem(this.openKey) !== "0";

    this.addControls();
    this.apply();
  }

  private addControls() {
    this.wrapper.insertAdjacentHTML(
      "afterbegin",
      `<div class="hand_controls">
        <button id="button_hand_open" class="hand_button" title="${_("Click to open or close your hand")}">
          <i class="fa fa-arrow-circle-o-down icon_down"></i>
          <i class="fa fa-arrow-circle-o-up icon_up"></i>
        </button>
        <button id="button_hand_place" class="hand_button" title="${_("Click to float your hand or park it on the table")}">
          <i class="fa fa-hand-paper-o icon_float"></i>
          <i class="fa fa-window-maximize icon_park"></i>
        </button>
      </div>`
    );

    $("button_hand_place")!.addEventListener("click", () => this.setPlace(this.place === "floating" ? "parked" : "floating"));
    $("button_hand_open")!.addEventListener("click", () => this.setOpen(!this.open));
  }

  setPlace(place: HandPlace) {
    this.place = place;
    localStorage.setItem(this.placeKey, place);
    this.apply();
  }

  setOpen(open: boolean) {
    this.open = open;
    localStorage.setItem(this.openKey, open ? "1" : "0");
    this.apply();
  }

  private apply() {
    if (this.place === "floating") {
      this.floatHost.appendChild(this.wrapper);
    } else {
      this.parkedHome.appendChild(this.wrapper);
    }
    this.wrapper.dataset.place = this.place;
    this.wrapper.dataset.open = this.open ? "1" : "0";
  }
}
