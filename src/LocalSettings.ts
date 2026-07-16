/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * Browser-local settings (localStorage only). Deliberately NOT BGA preferences:
 * nothing is sent to the server, so none of BGA's preference classes or
 * data-preference-id hooks are used - their JS would try to persist these.
 *
 * Modelled on bga-mars LocalSettings, trimmed to the select-style choices this
 * game needs and with the dojo calls dropped (fate does not load dojo).
 * -----
 */

export interface LocalProp {
  // internal name; also becomes the localStorage key and the ebd-body dataset name
  key: string;
  // display label shown next to the control
  label: string;
  // selectable values, as { storedValue: displayLabel }
  choice: { [value: string]: string };
  default: string;
  // called whenever the value is applied, including on initial setup
  onChange?: (value: string) => void;
  value?: string;
}

export class LocalSettings {
  constructor(
    private gameName: string,
    private props: LocalProp[] = []
  ) {}

  setup() {
    for (const prop of this.props) {
      this.applyChanges(prop, this.readProp(prop.key), false);
    }
  }

  getLocalSettingById(key: string): LocalProp | null {
    return this.props.find((prop) => prop.key == key) ?? null;
  }

  getDivId() {
    return `${this.gameName}_localsettings`;
  }

  /**
   * Renders the settings block into the in-game menu. Returns false when the menu is not
   * present (e.g. the local harness), so callers can ignore it.
   */
  renderContents(parentId: string): boolean {
    const parent = document.getElementById(parentId);
    if (!parent) return false;

    // BGA re-runs setup on undo/reconnect; drop a previous copy rather than stack duplicates.
    document.getElementById(this.getDivId())?.remove();

    const groups = this.props.map((prop) => this.renderProp(prop)).join("");
    const html = `
      <div id="${this.getDivId()}" class="localsettings">
        <h2>${_("Local Settings")}</h2>
        ${groups}
      </div>`;

    // Sit right after the last native preference, so we land at the end of the
    // preferences block instead of below the game options.
    const prefs = parent.querySelectorAll(".preference_choice");
    const anchor = prefs.length ? prefs[prefs.length - 1] : null;
    if (anchor) anchor.insertAdjacentHTML("afterend", html);
    else parent.insertAdjacentHTML("beforeend", html);

    for (const prop of this.props) {
      $(this.getInputId(prop))!.addEventListener("change", (event) => {
        this.applyChanges(prop, (event.target as HTMLSelectElement).value);
      });
    }
    return true;
  }

  private getInputId(prop: LocalProp) {
    return `localsettings_prop_${prop.key}`;
  }

  private renderProp(prop: LocalProp): string {
    const inputId = this.getInputId(prop);
    const options = Object.entries(prop.choice)
      .map(([value, label]) => `<option value="${value}" ${value == prop.value ? "selected" : ""}>${label}</option>`)
      .join("");
    // row-data/row-label/row-value are BGA's layout-only classes (as used by the static
    // "Game options" rows) - borrowed for a native look, without any of the classes or
    // data-preference-id hooks their preference JS binds to.
    return `
      <div class="localsettings_group row-data row-data-large">
        <div class="row-label"><label for="${inputId}">${prop.label}</label></div>
        <div class="row-value">
          <select id="${inputId}" class="localsettings_select">${options}</select>
        </div>
      </div>`;
  }

  applyChanges(prop: LocalProp, newValue: string | undefined, write: boolean = true) {
    prop.value = newValue !== undefined && prop.choice[newValue] ? newValue : prop.default;

    const input = $(this.getInputId(prop)) as HTMLSelectElement | null;
    if (input && input.value != prop.value) input.value = prop.value;

    $("ebd-body").dataset[`localsetting_${prop.key}`] = prop.value;
    if (write) this.writeProp(prop.key, prop.value);
    prop.onChange?.(prop.value);
  }

  private getLocalStorageItemId(key: string) {
    return `${this.gameName}.${key}`;
  }

  readProp(key: string): string | undefined {
    return localStorage.getItem(this.getLocalStorageItemId(key)) ?? undefined;
  }

  writeProp(key: string, value: string) {
    try {
      localStorage.setItem(this.getLocalStorageItemId(key), value);
    } catch (e) {
      console.error(e);
    }
  }
}
