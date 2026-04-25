/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * Reusable board zoom controls: Fit / Zoom-in / Zoom-out buttons anchored to
 * the sticky action bar (#page-title). Uses CSS `zoom` to scale a target
 * element; persists mode + scale in localStorage.
 * -----
 */

type ZoomMode = "fit" | "manual";

export interface LaZoomOptions {
  // id of the element to zoom (e.g. "thething"). Its parentElement is used as the scroll container.
  targetId: string;
  // prefix for localStorage keys (e.g. "fate" => "fate_board_zoom_mode")
  storagePrefix: string;
  // min/max zoom scale. Defaults: 0.3 .. 4.0
  minScale?: number;
  maxScale?: number;
  // zoom step multiplier (defaults to 1.1 = +10% per click)
  stepFactor?: number;
}

export class LaZoom {
  private mode: ZoomMode = "fit";
  private scale: number = 1;
  private readonly opts: Required<LaZoomOptions>;

  constructor(
    protected bga: Bga,
    opts: LaZoomOptions
  ) {
    this.opts = {
      minScale: 0.3,
      maxScale: 4.0,
      stepFactor: 1.1,
      ...opts
    };
  }

  private get modeKey() {
    return `${this.opts.storagePrefix}_board_zoom_mode`;
  }
  private get scaleKey() {
    return `${this.opts.storagePrefix}_board_zoom_scale`;
  }

  setup() {
    this.destroyDivOtherCopies("board_layout_controls");
    const host = document.getElementById("page-title");
    if (!host) {
      console.error("LaZoom: host element #page-title not found, zoom controls disabled");
      return;
    }

    host.insertAdjacentHTML(
      "beforeend",
      `<div id="board_layout_controls" class="board_layout_controls">
        <button id="layout_home" class="layout_button active" title="${_("Fit to screen")}"><i class="fa6 fa6-arrows-to-dot"></i></button>
        <button id="layout_zoom_in" class="layout_button" title="${_("Zoom in")}"><i class="fa fa-search-plus"></i></button>
        <button id="layout_zoom_out" class="layout_button" title="${_("Zoom out")}"><i class="fa fa-search-minus"></i></button>
      </div>`
    );

    const savedMode = localStorage.getItem(this.modeKey);
    const savedScale = parseFloat(localStorage.getItem(this.scaleKey) ?? "");
    this.mode = savedMode === "manual" ? "manual" : "fit";
    this.scale = Number.isFinite(savedScale) && savedScale > 0 ? savedScale : 1;

    $("layout_home").addEventListener("click", () => this.setMode("fit"));
    $("layout_zoom_in").addEventListener("click", () => this.zoomByFactor(this.opts.stepFactor));
    $("layout_zoom_out").addEventListener("click", () => this.zoomByFactor(1 / this.opts.stepFactor));

    window.addEventListener("resize", this.boundOnResize);

    this.apply();
  }

  private boundOnResize = () => {
    this.apply();
  };

  setMode(mode: ZoomMode) {
    this.mode = mode;
    localStorage.setItem(this.modeKey, mode);
    this.apply();
  }

  zoomByFactor(factor: number) {
    const target = $(this.opts.targetId);
    const current = this.mode === "fit" ? parseFloat(target.dataset.scale ?? "1") || 1 : this.scale;
    const next = Math.min(this.opts.maxScale, Math.max(this.opts.minScale, current * factor));
    this.scale = next;
    localStorage.setItem(this.scaleKey, String(next));
    this.setMode("manual");
  }

  apply() {
    const target = $(this.opts.targetId);
    $("ebd-body").dataset.boardZoom = this.mode;

    document.querySelectorAll(".layout_button").forEach((btn) => btn.classList.remove("active"));
    if (this.mode === "fit") {
      $("layout_home")?.classList.add("active");
      this.applyFitZoom(target);
    } else {
      this.applyManualZoom(target);
    }
  }

  private resetScale(target: HTMLElement) {
    target.style.zoom = "";
    target.dataset.scale = "1";
    if (target.parentElement) target.parentElement.scrollLeft = 0;
  }

  private applyFitZoom(target: HTMLElement) {
    this.resetScale(target);
    const parent = target.parentElement;
    if (!parent) return;
    const availableWidth = parent.clientWidth;
    const naturalWidth = target.scrollWidth;
    if (naturalWidth <= availableWidth) return;
    this.applyZoom(target, availableWidth / naturalWidth);
  }

  private applyManualZoom(target: HTMLElement) {
    this.resetScale(target);
    this.applyZoom(target, this.scale);
    const wrap = target.parentElement;
    if (wrap && wrap.scrollWidth > wrap.clientWidth) {
      wrap.scrollLeft = (wrap.scrollWidth - wrap.clientWidth) / 2;
    }
  }

  private applyZoom(target: HTMLElement, scale: number) {
    target.dataset.scale = String(scale);
    target.style.zoom = String(scale);
  }

  // undo may duplicate the div; keep only the last
  private destroyDivOtherCopies(id: string) {
    const panels = document.querySelectorAll("#" + id);
    panels.forEach((p, i) => {
      if (i < panels.length - 1) p.parentNode?.removeChild(p);
    });
  }
}
