/**
 * Harness renderer: reads staging/gamedatas.json + staging/notifications.json,
 * loads template.html into JSDOM, runs game.setup(), replays notifications,
 * inlines fate.css, and writes staging/snapshot.html.
 *
 * Usage: ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
 */

import fs from "fs";
import path from "path";
import { JSDOM } from "jsdom";
import Module from "module";

// ── Suppress verbose game console.log output ──────────────────────────────────
// Game code logs a lot of debug info; suppress it unless HARNESS_VERBOSE=1.
const log = console.log.bind(console);
if (!process.env.HARNESS_VERBOSE) {
  console.log = () => {};
}

// ── Path constants ─────────────────────────────────────────────────────────────

const repoRoot = path.resolve(__dirname, "../..");
const stagingDir = path.join(repoRoot, "staging");

function readJson(file: string): any {
  const p = path.join(stagingDir, file);
  if (!fs.existsSync(p)) {
    console.error(`Missing: ${p}`);
    process.exit(1);
  }
  return JSON.parse(fs.readFileSync(p, "utf8"));
}

const playName = process.argv[2] ?? "setup";
const scriptPath = path.join(stagingDir, "plays", playName, "script.json");
const script: any = fs.existsSync(scriptPath) ? JSON.parse(fs.readFileSync(scriptPath, "utf8")) : {};

const gamedatas: any = readJson("gamedatas.json");
const notifications: any[] = readJson("notifications.json");

// ── Intercept require("./libs") → libs.stub.ts (top-level await breaks CommonJS) ──

const originalResolve = (Module as any)._resolveFilename;
const libsStubPath = path.resolve(repoRoot, "src/tests/libs.stub.ts");
const libsSrcPath = path.resolve(repoRoot, "src/libs.ts");
(Module as any)._resolveFilename = function (request: string, parent: any, ...args: any[]) {
  const resolved = originalResolve.call(this, request, parent, ...args);
  if (resolved === libsSrcPath) return libsStubPath;
  return resolved;
};

// ── Load template.html into JSDOM ─────────────────────────────────────────────

const templatePath = path.join(__dirname, "template.html");
const templateHtml = fs.readFileSync(templatePath, "utf8");
const dom = new JSDOM(templateHtml, {
  runScripts: "dangerously",
  virtualConsole: (() => { const vc = new (require("jsdom").VirtualConsole)(); vc.forwardTo(console, { omitJSDOMErrors: true }); return vc; })()
});
const { window } = dom;
const { document } = window;

// ── Inject player panels (framework normally does this server-side) ────────────

function buildPlayerPanels(gamedatas: any, currentPlayerId: number): void {
  const playerBoards = document.getElementById("player_boards");
  if (!playerBoards) return;

  Object.entries(gamedatas.players as Record<string, any>).forEach(([id, player]: [string, any]) => {
    const color = player.player_color ?? player.color;
    const name = player.player_name ?? "Player";
    const isCurrent = id == currentPlayerId;

    const panel = document.createElement("div");
    panel.id = `overall_player_board_${id}`;
    panel.className = `player-board${isCurrent ? " current-player-board" : ""}`;
    panel.innerHTML = `
      <div class="player_board_inner" id="player_board_inner_${color}">
        <div class="player-name" id="player_name_${id}">
          <a style="color:#${color}">${name}</a>
        </div>
        <div id="player_board_${id}" class="player_board_content">
          <div class="player_score">
            <span id="player_score_${id}" class="player_score_value"></span>
          </div>
          <div class="player-board-game-specific-content"></div>
          <div class="player_table_status" id="player_table_status_${id}"></div>
        </div>
        ${isCurrent ? '<div id="current_player_board"></div>' : ""}
        <div id="player_panel_content_${color}" class="player_panel_content"></div>
      </div>`;
    playerBoards.appendChild(panel);
  });
}

// current_player_id from script.json; fall back to first player in gamedatas
const currentPlayerId: number = script.current_player_id ?? parseInt(Object.keys(gamedatas.players)[0], 10);
buildPlayerPanels(gamedatas, currentPlayerId);

// Populate player.id from key (real BGA framework sets this; harness gamedatas may omit it)
Object.entries(gamedatas.players as Record<string, any>).forEach(([id, player]) => {
  if (!player.id) player.id = id;
});

// ── Expose DOM globals ─────────────────────────────────────────────────────────

(global as any).window = window;
(global as any).document = document;
(global as any).HTMLElement = window.HTMLElement;
(global as any).Element = window.Element;
(global as any).DOMMatrix = (window as any).DOMMatrix ?? class {};

// ── BGA framework stubs ────────────────────────────────────────────────────────

(global as any).$ = function $(id: any): any {
  if (typeof id === "string") return document.getElementById(id);
  return id;
};

(global as any)._ = function _(str: string) {
  return str;
};

const tooltipRegistry = new Map<string, string>();

(global as any).gameui = {
  player_id: currentPlayerId,
  on_client_state: false,
  format_string_recursive_sub_logs: function(args: any): void {
    const gm = (global as any).gameui;
    for (const key in args) {
      if (key === "i18n") continue;
      const val = args[key];
      if (val === null || typeof val !== "object" || Array.isArray(val) || (typeof Node !== "undefined" && val instanceof Node)) continue;
      if (val.log !== undefined && val.args !== undefined) {
        args[key] = gm.format_string_recursive(val.log, val.args);
      } else {
        gm.format_string_recursive_sub_logs(val);
      }
    }
  },
  format_string_recursive: function(str: string, args: any): string {
    if (str === null) { console.error("format_string_recursive called with null string", args); return "null_tr_string"; }
    const gm = (global as any).gameui;
    // Allow game to pre-process log via bgaFormatText
    if (typeof gm.bgaFormatText === "function") {
      try {
        const r = gm.bgaFormatText(str, args);
        if (r) { str = r.log ?? str; args = r.args ?? args; }
      } catch (e: any) { console.error(str, args, "bgaFormatText threw", e.stack); }
    }
    if (!str) return "";
    // Translate i18n fields
    if (args?.i18n) {
      for (const key of Object.values(args.i18n) as string[]) {
        if (Array.isArray(args[key])) {
          args[key] = args[key].map((v: string) => gm.clienttranslate_string(v));
        } else if (args[key]) {
          args[key] = gm.clienttranslate_string(args[key]);
        }
      }
    }
    // Recursively format nested {log, args} objects
    gm.format_string_recursive_sub_logs(args);
    // Join array args with separator if specified
    if (args?.separator && typeof args.separator === "object") {
      for (const key of Object.keys(args.separator)) {
        if (Array.isArray(args[key]) && args[key].length > 1) {
          const sep = args.separator[key];
          if (sep === "and" || sep === "or") {
            const arr = args[key];
            args[key] = `${arr.slice(0, -1).join(", ")} ${sep} ${arr[arr.length - 1]}`;
          } else {
            args[key] = args[key].join(sep);
          }
        }
      }
    }
    // Substitute ${key} placeholders (dojo string.substitute style)
    return str.replace(/\$\{(\w+)\}/g, (_m: string, key: string) => args?.[key] ?? `\${${key}}`);
  },
  addTooltipHtml: (nodeId: string, html: string, _delay?: number) => {
    tooltipRegistry.set(nodeId, html);
  },
  removeTooltip: (nodeId: string) => {
    tooltipRegistry.delete(nodeId);
  },
  bgaAnimationsActive: () => false,
  restoreServerGameState: () => {},
  updatePageTitle: () => {},
  wait: (_ms: number) => Promise.resolve(),
  clienttranslate_string: (s: string) => s,
  tooltips: {}
};

(global as any).ebg = {
  core: { gamegui: {} },
  counter: class {},
  popindialog: class {
    create() {}
    setTitle() {}
    setContent() {}
    show() {}
  }
};

(global as any).define = function () {};

// localStorage stub (not available in Node.js)
(global as any).localStorage = {
  _store: {} as Record<string, string>,
  getItem(k: string) {
    return this._store[k] ?? null;
  },
  setItem(k: string, v: string) {
    this._store[k] = v;
  },
  removeItem(k: string) {
    delete this._store[k];
  }
};

// ── Mock Bga object (mirrors Game.spec.ts) ─────────────────────────────────────

const gameArea = document.getElementById("game_play_area")!;

// ── statusBar: writes title + buttons into #pagemaintitletext / #generalactions ──

const statusBar = {
  setTitle(html: string) {
    const el = document.getElementById("pagemaintitletext");
    if (el) el.innerHTML = html;
  },
  addActionButton(label: string, handler: any, options: any = {}) {
    const el = document.getElementById("generalactions");
    if (!el) return null;
    const btn = document.createElement("button");
    btn.className = `action-button bgabutton bgabutton_${options.color ?? "blue"}`;
    if (options.id) btn.id = options.id;
    btn.innerHTML = label;
    if (typeof handler === "function") {
      // Strategy 1: if button id is "button_<target>", construct action payload directly
      // (onToken uses event.currentTarget.id to derive target, which won't work with synthetic events)
      if (options.id && options.id.startsWith("button_")) {
        const target = options.id.replace(/^button_/, "");
        if (target && target !== "cancel" && target !== "undo" && target !== "done" && target !== "reset") {
          btn.setAttribute("data-action", JSON.stringify({ endpoint: "action_resolve", data: { data: JSON.stringify({ target }) } }));
        }
      } else {
        // Strategy 2: intercept performAction — works for handlers that call it directly
        const origPerformAction = mockBga.actions.performAction;
        let captured: string | null = null;
        mockBga.actions.performAction = (endpoint: string, data?: any) => {
          captured = JSON.stringify({ endpoint, data: data ?? {} });
          return Promise.resolve({});
        };
        try {
          handler(new Event("click"));
        } catch (_) {}
        mockBga.actions.performAction = origPerformAction;
        if (captured) btn.setAttribute("data-action", captured);
      }
    }
    el.appendChild(btn);
    return btn;
  }
};

// ── states registry: stores handlers registered by Game constructor ────────────

const statesRegistry: Record<string, any> = {};
const states = {
  register(name: string, handler: any) {
    statesRegistry[name] = handler;
  },
  isOnClientState() {
    return false;
  }
};

const mockBga: any = {
  gameui: (global as any).gameui,
  statusBar,
  images: {},
  sounds: {},
  userPreferences: {},
  players: {
    isCurrentPlayerSpectator: () => false,
    isCurrentPlayerActive: () => true,
    isPlayerActive: () => true,
    getActivePlayerIds: () => [],
    getActivePlayerId: () => currentPlayerId
  },
  actions: {
    performAction(endpoint: string, data?: any) {
      const payload = JSON.stringify({ endpoint, data: data ?? {} });
      log("ACTION:", payload);
      return Promise.resolve({});
    }
  },
  notifications: { setupPromiseNotifications: () => {} },
  gameArea: { getElement: () => gameArea },
  playerPanels: {
    getElement: (playerId: number) => {
      return document.getElementById(`player_board_${playerId}`);
    }
  },
  dialogs: { showMessage: () => {}, showMoveUnauthorized: () => {} },
  states
};

// ── Import Game (after globals are set) ───────────────────────────────────────

import { Game } from "../../src/Game";

// ── Run setup and notification replay ─────────────────────────────────────────

async function main() {
  log("Instantiating Game...");
  const game = new Game(mockBga);
  // Wire bgaFormatText so format_string_recursive can call it
  (global as any).gameui.bgaFormatText = (str: string, args: any) => (game as any).bgaFormatText(str, args);

  log("Calling game.setup()...");
  game.setup(gamedatas);

  // Simulate framework calling onLeavingState + onEnteringState for a state transition
  let currentStateName: string | null = null;

  // gamestate: { name, active_player, args }
  // privateAlreadyUnwrapped: true when _private is already the current player's opInfo (gameStateChange notif)
  function enterState(gamestate: any, privateAlreadyUnwrapped = false) {
    const handler = statesRegistry[gamestate.name];
    const isActive = String(gamestate.active_player) === String(currentPlayerId);
    if (currentStateName) {
      const leavingHandler = statesRegistry[currentStateName];
      if (leavingHandler?.onLeavingState) {
        log(`Leaving state: ${currentStateName}`);
        leavingHandler.onLeavingState({}, isActive);
      }
    }
    currentStateName = gamestate.name;
    const titleEl = document.getElementById("pagemaintitletext");
    const actionsEl = document.getElementById("generalactions");
    if (titleEl) titleEl.innerHTML = "";
    if (actionsEl) actionsEl.innerHTML = "";
    if (handler?.onEnteringState) {
      log(`Entering state: ${gamestate.name} (active=${isActive})`);
      const args = { ...(gamestate.args ?? {}) };
      // Real BGA framework unwraps _private[playerId] before calling onEnteringState.
      // In gamedatas it's keyed by player_id; in gameStateChange notif it's already unwrapped.
      if (!privateAlreadyUnwrapped && args._private) {
        args._private = args._private[currentPlayerId] ?? args._private[String(currentPlayerId)] ?? args._private;
      }
      handler.onEnteringState(args, isActive);
    } else {
      log(`No onEnteringState handler for state: ${gamestate.name}`);
    }
  }

  // Simulate framework calling onEnteringState for the current game state (as BGA does after reload)
  if (gamedatas.gamestate) {
    enterState(gamedatas.gamestate);
  }

  const logsEl = document.getElementById("logs");
  let logCounter = 0;

  function appendLogEntry(text: string) {
    if (!logsEl || !text.trim()) return;
    const entry = document.createElement("div");
    entry.className = "log log_replayable";
    entry.id = `log_${++logCounter}`;
    const box = document.createElement("div");
    box.className = "roundedbox";
    box.innerHTML = text;
    entry.appendChild(box);
    logsEl.appendChild(entry);
  }

  log(`Replaying ${notifications.length} notification(s)...`);
  for (const notif of notifications) {
    // Append game log entry for any notification with a non-empty log string
    const logStr: string = notif.log ?? "";
    if (logStr.trim()) {
      const args = Array.isArray(notif.args) ? {} : (notif.args ?? {});
      const text = (global as any).gameui.format_string_recursive(logStr, args);
      appendLogEntry(text);
    }

    if (notif.type === "gameStateChange") {
      // Framework-level: call onLeavingState for old state, onEnteringState for new state.
      // notif.args = { id, name, active_player, type, args } — _private already unwrapped by PHP.
      enterState(notif.args, true);
      continue;
    }
    const handler = (game as any)[`notif_${notif.type}`];
    if (typeof handler === "function") {
      await handler.call(game, notif.args);
    } else {
      // Skip notifications with no handler (e.g. undoRestorePoint, tableWindow, etc.)
    }
  }

  // ── Inline CSS ───────────────────────────────────────────────────────────────

  // Harness layout CSS (minimal BGA structural rules, source-controlled)
  const harnessCommonCss = path.join(__dirname, "common.css");
  {
    const style = document.createElement("style");
    style.textContent = fs.readFileSync(harnessCommonCss, "utf8");
    document.head.appendChild(style);
  }

  // Game CSS
  const cssPath = path.join(repoRoot, "fate.css");
  if (fs.existsSync(cssPath)) {
    const style = document.createElement("style");
    style.textContent = fs.readFileSync(cssPath, "utf8");
    document.head.appendChild(style);
    log("Inlined harness/common.css + fate.css");
  } else {
    console.warn("fate.css not found — run npm run build:scss first");
  }

  // ── Inject harness script (survives serialization, runs in browser) ──────────

  const harnessScript = document.createElement("script");
  harnessScript.textContent = `
    // Harness: intercept clicks on action buttons and log the action payload to console
    (function() {
      document.addEventListener("click", function(e) {
        var btn = e.target.closest("[data-action]");
        if (!btn) return;
        var raw = btn.getAttribute("data-action");
        var display = raw;
        try {
          var parsed = JSON.parse(raw);
          if (parsed.data && typeof parsed.data.data === "string") {
            parsed.data = JSON.parse(parsed.data.data);
          }
          display = JSON.stringify(parsed);
        } catch(_) {}
        console.log("ACTION:", display);
      });
    })();
  `;
  document.body.appendChild(harnessScript);

  // ── Tooltip registry section ─────────────────────────────────────────────────

  // ── Click handler registry section ──────────────────────────────────────────

  const clickableEls = Array.from(document.querySelectorAll("[_lis], [data-action]")) as HTMLElement[];
  if (clickableEls.length > 0) {
    const section = document.createElement("div");
    section.id = "harness-click-registry";
    section.style.cssText = "margin:16px;font:12px monospace;";
    let inner = `<details><summary style="cursor:pointer;padding:4px;background:#ddd;border:1px solid #aaa;"><b>Click handlers (${clickableEls.length} elements)</b></summary><div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px;border:1px solid #aaa;background:#f9f9f9;">`;
    for (const el of clickableEls) {
      const id = el.id || "(no id)";
      const classes = Array.from(el.classList).join(" ") || "(no class)";
      const action = el.getAttribute("data-action");
      let actionLabel = "onToken";
      if (action) {
        try {
          const parsed = JSON.parse(action);
          if (parsed.data && typeof parsed.data.data === "string") parsed.data = JSON.parse(parsed.data.data);
          actionLabel = JSON.stringify(parsed);
        } catch (_) {
          actionLabel = action;
        }
      }
      inner += `<div style="width:fit-content;max-width:500px;padding:6px;border:1px solid #ccc;background:#fff;">`;
      inner += `<div style="color:#666;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${id}">${id}</div>`;
      inner += `<div style="color:#888;font-size:11px;margin-bottom:2px;">${classes}</div>`;
      inner += `<div>${actionLabel}</div>`;
      inner += `</div>`;
    }
    inner += `</div></details>`;
    section.innerHTML = inner;
    document.body.appendChild(section);
    log(`Click handler registry: ${clickableEls.length} elements`);
  }

  if (tooltipRegistry.size > 0) {
    const section = document.createElement("div");
    section.id = "harness-tooltip-registry";
    section.style.cssText = "margin:16px;font:12px monospace;";
    let inner = `<details><summary style="cursor:pointer;padding:4px;background:#ddd;border:1px solid #aaa;"><b>Tooltip registry (${tooltipRegistry.size} entries)</b></summary><div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px;border:1px solid #aaa;background:#f9f9f9;">`;
    for (const [nodeId, html] of tooltipRegistry) {
      inner += `<div style="width:fit-content;max-width:500px;padding:6px;border:1px solid #ccc;background:#fff;"><div style="color:#666;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${nodeId}">${nodeId}</div><div>${html}</div></div>`;
    }
    inner += `</div></details>`;
    section.innerHTML = inner;
    document.body.appendChild(section);
    log(`Tooltip registry: ${tooltipRegistry.size} entries`);
  }

  // ── Write snapshot ────────────────────────────────────────────────────────────

  const snapshotPath = path.join(stagingDir, "snapshot.html");
  fs.writeFileSync(snapshotPath, document.documentElement.outerHTML, "utf8");
  log(`Wrote staging/snapshot.html`);
}

main().catch((err) => {
  console.error("render.ts failed:", err);
  process.exit(1);
});
