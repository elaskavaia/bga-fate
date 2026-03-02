/**
 * Test setup: stub BGA framework globals so source files can be imported.
 */
import { JSDOM } from "jsdom";
import Module from "module";
import path from "path";

// Intercept require("./libs") from src/ to use our stub instead (libs.ts has top-level await which breaks CommonJS)
const originalResolve = (Module as any)._resolveFilename;
const libsStubPath = path.resolve(__dirname, "libs.stub.ts");
const libsSrcPath = path.resolve(__dirname, "..", "src", "libs.ts");
(Module as any)._resolveFilename = function (request: string, parent: any, ...args: any[]) {
  const resolved = originalResolve.call(this, request, parent, ...args);
  if (resolved === libsSrcPath) return libsStubPath;
  return resolved;
};

const dom = new JSDOM("<!doctype html><html><body><div id='ebd-body'></div></body></html>");

// Expose DOM globals
(global as any).window = dom.window;
(global as any).document = dom.window.document;
(global as any).HTMLElement = dom.window.HTMLElement;
(global as any).Element = dom.window.Element;
(global as any).DOMMatrix = dom.window.DOMMatrix;

// BGA framework global: $(id) returns element by id
(global as any).$ = function $(id: any): any {
  if (typeof id === "string") return dom.window.document.getElementById(id);
  return id;
};

// BGA framework global: _(str) translation passthrough
(global as any)._ = function _(str: string) {
  return str;
};

// BGA framework global: gameui stub
(global as any).gameui = {
  player_id: 1,
  on_client_state: false,
  format_string_recursive: (log: string, args: any) => log,
  addTooltipHtml: () => {},
  removeTooltip: () => {},
  bgaAnimationsActive: () => false,
  restoreServerGameState: () => {},
  updatePageTitle: () => {},
  wait: (ms: number) => Promise.resolve(),
  clienttranslate_string: (s: string) => s,
  tooltips: {}
};

// BGA framework global: ebg stub
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

// BGA framework global: define stub (AMD)
(global as any).define = function () {};
