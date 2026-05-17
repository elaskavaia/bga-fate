import { expect } from "chai";
import sinon from "sinon";
import { Game } from "../Game";

/** Create a minimal mock Bga object sufficient to construct a Game instance. */
function createMockBga(): any {
  return {
    gameui: gameui,
    statusBar: { setTitle: sinon.stub(), addActionButton: sinon.stub() },
    images: {},
    sounds: {},
    userPreferences: {},
    players: {
      isCurrentPlayerSpectator: sinon.stub().returns(false),
      isCurrentPlayerActive: sinon.stub().returns(true),
      isPlayerActive: sinon.stub().returns(true),
      getActivePlayerIds: sinon.stub().returns([]),
      getActivePlayerId: sinon.stub().returns(1)
    },
    actions: { performAction: sinon.stub().resolves({}) },
    notifications: { setupPromiseNotifications: sinon.stub() },
    gameArea: { getElement: sinon.stub().returns(document.createElement("div")) },
    playerPanels: {},
    dialogs: { showMessage: sinon.stub(), showMoveUnauthorized: sinon.stub() },
    states: { register: sinon.stub() }
  };
}

describe("Game.getPlaceRedirect", () => {
  let game: Game;

  beforeEach(() => {
    // Clean body, add root containers
    document.body.innerHTML = '<div id="ebd-body"><div id="map_wrapper"><div id="supply_monster"></div></div></div>';
    game = new Game(createMockBga());
    // Stub animationLa so onEnd callbacks don't blow up
    (game as any).animationLa = {
      pulse: sinon.stub(),
      evaporate: sinon.stub()
    };
    // Pile-stats overlay reads strength/xp/health via getRulesFor on the monster
    // type — seed the minimum so getPlaceRedirect doesn't crash on undefined token_types.
    (game as any).gamedatas = { token_types: { monster_goblin: { strength: 1, xp: 1, health: 2 } } };
  });

  it("should redirect monsters to a supply sub-container by type", () => {
    const result = game.getPlaceRedirect({ key: "monster_goblin_1", location: "supply_monster", state: 0 });
    expect(result.location).to.equal("supply_monster_goblin");
    // The sub-container div should have been created in the DOM
    expect($("supply_monster_goblin")).to.not.be.null;
  });

  it("should reuse existing supply sub-container on second monster of same type", () => {
    game.getPlaceRedirect({ key: "monster_goblin_1", location: "supply_monster", state: 0 });
    game.getPlaceRedirect({ key: "monster_goblin_2", location: "supply_monster", state: 0 });
    // Should still be exactly one sub-container
    const containers = document.querySelectorAll("#supply_monster_goblin");
    expect(containers.length).to.equal(1);
  });

  it("should redirect crystals to a bucket on non-supply locations", () => {
    // Create the target location in DOM
    const target = document.createElement("div");
    target.id = "monster_goblin_1";
    document.body.appendChild(target);

    const result = game.getPlaceRedirect({ key: "crystal_red_1", location: "monster_goblin_1", state: 0 });
    expect(result.location).to.equal("bucket_crystal_red_monster_goblin_1");
    expect($("bucket_crystal_red_monster_goblin_1")).to.not.be.null;
  });

  it("should not redirect crystals going to supply", () => {
    const result = game.getPlaceRedirect({ key: "crystal_red_1", location: "supply_crystal_red", state: 0 });
    // location unchanged — no bucket redirect for supply
    expect(result.location).to.equal("supply_crystal_red");
  });

  it("should set onEnd for dice landing on display_battle with anim_target", () => {
    const result = game.getPlaceRedirect(
      { key: "die_attack_1", location: "display_battle", state: 3 },
      { anim_target: "monster_goblin_1" }
    );
    expect(result.location).to.equal("display_battle");
    expect(result.onEnd).to.be.a("function");
  });

  it("should pass through tokens that match no redirect rule", () => {
    const result = game.getPlaceRedirect({ key: "hero_1", location: "hex_5_5", state: 0 });
    expect(result.location).to.equal("hex_5_5");
    expect(result.onEnd).to.be.undefined;
  });
});

describe("Game.updateTokenDisplayInfo", () => {
  let game: Game;

  beforeEach(() => {
    document.body.innerHTML = '<div id="ebd-body"></div>';
    game = new Game(createMockBga());
    // Set up minimal token_types material for monsters
    (game as any).gamedatas = {
      tokens: {},
      token_types: {
        monster: { name: "Monster", type: "monster", create: 2 },
        monster_goblin: { name: "Goblin", type: "monster trollkin rank1", faction: "trollkin", rank: 1, strength: 1, health: 2, xp: 1 },
        monster_legend: { name: "Legend", type: "monster legend", create: 1 },
        monster_legend_1: { name: "Queen of the Dead", type: "monster legend", faction: "dead" },
        monster_legend_1_1: {
          name: "Queen of the Dead (I)",
          type: "monster legend",
          faction: "dead",
          create: 1,
          location: "supply_monster",
          strength: 7,
          health: 11,
          xp: 6
        },
        monster_legend_3: { name: "Grendel", type: "monster legend", faction: "trollkin" },
        monster_legend_3_1: {
          name: "Grendel (I)",
          type: "monster legend",
          faction: "trollkin",
          create: 1,
          location: "supply_monster",
          strength: 7,
          health: 12,
          xp: 6
        },
        trollkin: { name: "Trollkin" },
        firehorde: { name: "Fire Horde" },
        dead: { name: "The Dead" }
      }
    };
  });

  it("should show legend flavor text for legend monsters", () => {
    const info = game.getTokenDisplayInfo("monster_legend_1_1");
    expect(info.tooltip).to.include("chilling sight to behold");
  });

  it("should show correct flavor text per legend number", () => {
    const info = game.getTokenDisplayInfo("monster_legend_3_1");
    expect(info.tooltip).to.include("colossal beast");
    expect(info.tooltip).to.not.include("chilling sight");
  });

  it("should show faction flavor text for regular monsters", () => {
    const info = game.getTokenDisplayInfo("monster_goblin_1");
    expect(info.tooltip).to.include("Trollkin");
    expect(info.tooltip).to.include("savage clan");
  });

  it("should show faction name in tooltip", () => {
    const info = game.getTokenDisplayInfo("monster_legend_1_1");
    expect(info.tooltip).to.include("The Dead");
  });

  it("should show stats for regular monsters", () => {
    const info = game.getTokenDisplayInfo("monster_goblin_1");
    expect(info.tooltip).to.include("Strength");
    expect(info.tooltip).to.include("Health");
  });

  it("should show stats for legends with stats", () => {
    const info = game.getTokenDisplayInfo("monster_legend_1_1");
    expect(info.tooltip).to.include("Strength");
    expect(info.tooltip).to.include("Health");
    expect(info.tooltip).to.include("Legend");
  });
});

describe("Game.updateTokenDisplayInfo hero tooltip", () => {
  let game: Game;

  beforeEach(() => {
    document.body.innerHTML = '<div id="ebd-body"></div>';
    game = new Game(createMockBga());
    (game as any).gamedatas = {
      tokens: {
        tracker_strength_ff0000: { key: "tracker_strength_ff0000", location: "tableau_ff0000", state: 3 },
        tracker_health_ff0000: { key: "tracker_health_ff0000", location: "tableau_ff0000", state: 9 },
        tracker_range_ff0000: { key: "tracker_range_ff0000", location: "tableau_ff0000", state: 2 },
        tracker_move_ff0000: { key: "tracker_move_ff0000", location: "tableau_ff0000", state: 3 },
        tracker_hand_ff0000: { key: "tracker_hand_ff0000", location: "tableau_ff0000", state: 4 },
      },
      token_types: {
        hero: { name: "Hero", type: "hero", create: 1 },
      },
      players: {
        1: { id: 1, color: "ff0000", heroNo: 1 },
      },
    };
  });

  it("should show all attributes in hero tooltip", () => {
    const info = game.getTokenDisplayInfo("hero_1");
    expect(info.tooltip).to.include("Strength");
    expect(info.tooltip).to.include("3");
    expect(info.tooltip).to.include("Health");
    expect(info.tooltip).to.include("9");
    expect(info.tooltip).to.include("Range");
    expect(info.tooltip).to.include("2");
    expect(info.tooltip).to.include("Move");
    expect(info.tooltip).to.include("Hand Limit");
    expect(info.tooltip).to.include("4");
  });

  it("should show zero values when trackers are missing", () => {
    (game as any).gamedatas.tokens = {};
    const info = game.getTokenDisplayInfo("hero_1");
    expect(info.tooltip).to.include("Strength");
    expect(info.tooltip).to.include("0");
  });

  it("should return empty tooltip when no matching player", () => {
    (game as any).gamedatas.players = {};
    const info = game.getTokenDisplayInfo("hero_1");
    expect(info.tooltip).to.equal("");
  });
});

describe("Game.onClickSanity", () => {
  let game: Game;

  let consoleStub: sinon.SinonStub;

  beforeEach(() => {
    document.body.innerHTML = '<div id="ebd-body"><div id="thething"></div></div>';
    game = new Game(createMockBga());
    consoleStub = sinon.stub(console, "log");
  });

  afterEach(() => {
    consoleStub.restore();
  });

  function makeClickEvent(currentTarget: HTMLElement, target?: HTMLElement): Event {
    return { currentTarget, target: target ?? currentTarget } as unknown as Event;
  }

  it("should return blocked=true and active=false when element has no active_slot class", () => {
    const el = document.createElement("div");
    el.id = "some_token";
    document.body.appendChild(el);

    const result = game.onClickSanity(makeClickEvent(el));
    expect(result.targetId).to.equal("some_token");
    expect(result.blocked).to.be.false;
    expect(result.active).to.be.false;
  });

  it("should return active=true when element has active_slot class", () => {
    const el = document.createElement("div");
    el.id = "some_token";
    el.classList.add("active_slot");
    document.body.appendChild(el);

    const result = game.onClickSanity(makeClickEvent(el));
    expect(result.targetId).to.equal("some_token");
    expect(result.blocked).to.be.true;
    expect(result.active).to.be.true;
  });

  it("should return active=true for button_ prefixed ids without active_slot class", () => {
    const el = document.createElement("div");
    el.id = "button_confirm";
    document.body.appendChild(el);

    const result = game.onClickSanity(makeClickEvent(el));
    expect(result.targetId).to.equal("button_confirm");
    expect(result.active).to.be.true;
  });

  it("should use data-targetId when present on active element", () => {
    const el = document.createElement("div");
    el.id = "some_token";
    el.classList.add("active_slot");
    el.dataset.targetId = "real_target";
    document.body.appendChild(el);

    const result = game.onClickSanity(makeClickEvent(el));
    expect(result.targetId).to.equal("real_target");
    expect(result.active).to.be.true;
  });

  it("should return blocked=true with no id when clicking thething with no active parent", () => {
    const thething = $("thething");
    const child = document.createElement("span");
    thething.appendChild(child);

    const result = game.onClickSanity(makeClickEvent(thething, child));
    expect(result.targetId).to.be.undefined;
    expect(result.blocked).to.be.true;
    expect(result.active).to.be.false;
  });

  it("should walk up to active parent when clicking inside thething", () => {
    const thething = $("thething");
    const parent = document.createElement("div");
    parent.id = "hex_5_5";
    parent.classList.add("active_slot");
    thething.appendChild(parent);
    const child = document.createElement("span");
    parent.appendChild(child);

    const result = game.onClickSanity(makeClickEvent(thething, child));
    expect(result.targetId).to.equal("hex_5_5");
    expect(result.active).to.be.true;
  });

  it("should return blocked=true when showHelp intercepts", () => {
    const el = document.createElement("div");
    el.id = "some_token";
    el.classList.add("active_slot");
    document.body.appendChild(el);

    (game as any).showHelp = sinon.stub().returns(true);
    const result = game.onClickSanity(makeClickEvent(el));
    expect(result.blocked).to.be.true;
    expect(result.active).to.be.false;
  });
});

describe("Game.handleStackedTooltips", () => {
  let game: Game;
  let addTooltipHtml: sinon.SinonStub;

  beforeEach(() => {
    document.body.innerHTML = '<div id="ebd-body"></div>';
    game = new Game(createMockBga());

    addTooltipHtml = sinon.stub();
    (gameui as any).addTooltipHtml = addTooltipHtml;

    // Stub the per-token tooltip builder so assertions can compare on a recognizable shape
    (game as any).getTooltipHtmlForToken = (id: string) => `<tt-${id}>`;

    // Level I card sibling lookup needs minimal token_types
    (game as any).gamedatas = {
      tokens: {},
      token_types: {
        card_hero_1_1: { name: "Bjorn I" },
        card_hero_1_2: { name: "Bjorn II" }
      }
    };
  });

  function makeHex(id: string): HTMLElement {
    const hex = document.createElement("div");
    hex.id = id;
    hex.classList.add("hex");
    document.body.appendChild(hex);
    return hex;
  }

  function addChild(parent: HTMLElement, id: string, dataTt?: string): HTMLElement {
    const child = document.createElement("div");
    child.id = id;
    if (dataTt) child.dataset.tt = dataTt;
    parent.appendChild(child);
    return child;
  }

  it("hex with no children: registers no tooltips", () => {
    const hex = makeHex("hex_5_5");
    game.handleStackedTooltips(hex);
    expect(addTooltipHtml.called).to.be.false;
  });

  it("hex with a token child: writes combined tooltip on both child and hex", () => {
    const hex = makeHex("hex_5_5");
    addChild(hex, "hero_1");

    game.handleStackedTooltips(hex);

    expect(addTooltipHtml.callCount).to.equal(2);
    const combined = "<tt-hero_1><tt-hex_5_5>";
    expect(addTooltipHtml.calledWith("hero_1", combined)).to.be.true;
    expect(addTooltipHtml.calledWith("hex_5_5", combined)).to.be.true;
  });

  it("hex with a bucket child: bucket is excluded from stacking", () => {
    const hex = makeHex("hex_5_5");
    addChild(hex, "bucket_crystal_red_hex_5_5");

    game.handleStackedTooltips(hex);

    expect(addTooltipHtml.called).to.be.false;
  });

  it("hex with multiple children: parent ends up with the last child's combined tooltip", () => {
    const hex = makeHex("hex_5_5");
    addChild(hex, "hero_1");
    addChild(hex, "monster_goblin_1");

    game.handleStackedTooltips(hex);

    const lastCombined = "<tt-monster_goblin_1><tt-hex_5_5>";
    const onHex = addTooltipHtml.getCalls().filter((c) => c.args[0] === "hex_5_5");
    expect(onHex).to.have.length(2);
    expect(onHex[onHex.length - 1].args[1]).to.equal(lastCombined);
  });

  it("token whose parent is a hex: writes combined tooltip on both token and hex", () => {
    const hex = makeHex("hex_5_5");
    const child = addChild(hex, "monster_goblin_1");

    game.handleStackedTooltips(child);

    expect(addTooltipHtml.callCount).to.equal(2);
    const combined = "<tt-monster_goblin_1><tt-hex_5_5>";
    expect(addTooltipHtml.calledWith("monster_goblin_1", combined)).to.be.true;
    expect(addTooltipHtml.calledWith("hex_5_5", combined)).to.be.true;
  });

  it("token not on a hex with no Level II sibling: no tooltip registered", () => {
    const parent = document.createElement("div");
    parent.id = "supply_monster";
    document.body.appendChild(parent);
    const child = addChild(parent, "monster_goblin_1");

    game.handleStackedTooltips(child);

    expect(addTooltipHtml.called).to.be.false;
  });

  it("Level I hero card: registers combined base + sibling Level II tooltip", () => {
    const card = document.createElement("div");
    card.id = "card_hero_1_1";
    document.body.appendChild(card);

    game.handleStackedTooltips(card);

    expect(addTooltipHtml.callCount).to.equal(1);
    expect(addTooltipHtml.firstCall.args[0]).to.equal("card_hero_1_1");
    expect(addTooltipHtml.firstCall.args[1]).to.equal("<tt-card_hero_1_1><tt-card_hero_1_2>");
  });

  it("uses data-tt over id when resolving the child token id", () => {
    const hex = makeHex("hex_5_5");
    // DOM id is a CSS-safe alias; dataset.tt carries the real token id
    addChild(hex, "tt_card_1", "card_hero_1_1");

    game.handleStackedTooltips(hex);

    const combined = "<tt-card_hero_1_1><tt-hex_5_5>";
    expect(addTooltipHtml.calledWith("tt_card_1", combined)).to.be.true;
    expect(addTooltipHtml.calledWith("hex_5_5", combined)).to.be.true;
  });
});
