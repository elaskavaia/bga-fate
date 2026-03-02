import { expect } from "chai";
import sinon from "sinon";
import { Game } from "../src/Game";

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
    // Clean body, add a root container for supply_monster
    document.body.innerHTML = '<div id="ebd-body"><div id="supply_monster"></div></div>';
    game = new Game(createMockBga());
    // Stub animationLa so onEnd callbacks don't blow up
    (game as any).animationLa = {
      pulse: sinon.stub(),
      evaporate: sinon.stub()
    };
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

  it("should redirect cards on tableau to cardsarea", () => {
    const result = game.getPlaceRedirect({ key: "card_hero_1_1", location: "tableau_6cd0f6", state: 0 });
    expect(result.location).to.equal("cardsarea_6cd0f6");
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
