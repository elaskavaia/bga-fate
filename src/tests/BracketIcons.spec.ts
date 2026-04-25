import { expect } from "chai";
import sinon from "sinon";
import { Game } from "../Game";

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

describe("Game.replaceSimpleIconsInLog", () => {
  let game: Game;

  beforeEach(() => {
    document.body.innerHTML = '<div id="ebd-body"></div>';
    game = new Game(createMockBga());
    (game as any).gamedatas = {
      token_types: {
        XP: { type: "wicon wicon_xp", name: "Gold/XP" },
        MANA: { type: "wicon wicon_mana", name: "Mana" },
        DAMAGE: { type: "wicon wicon_damage", name: "Damage" },
        RUNE: { type: "wicon wicon_rune", name: "Rune" },
        DIE_ATTACK: { type: "wicon wicon_die_attack", name: "Attack Die" }
      }
    };
  });

  it("returns log unchanged when no brackets present", () => {
    const out = game.replaceSimpleIconsInLog("no brackets here");
    expect(out).to.equal("no brackets here");
  });

  it("substitutes [XP] with a wicon_xp div", () => {
    const out = game.replaceSimpleIconsInLog("Gain 2 [XP].");
    expect(out).to.include("wicon_xp");
    expect(out).to.include("<div");
    expect(out).to.not.include("[XP]");
  });

  it("substitutes [MANA] and [XP] in the same string", () => {
    const out = game.replaceSimpleIconsInLog("[MANA] then [XP]");
    expect(out).to.include("wicon_mana");
    expect(out).to.include("wicon_xp");
    expect(out).to.not.include("[MANA]");
    expect(out).to.not.include("[XP]");
  });

  it("substitutes [DAMAGE], [RUNE], [DIE_ATTACK]", () => {
    const out = game.replaceSimpleIconsInLog("[DAMAGE] [RUNE] [DIE_ATTACK]");
    expect(out).to.include("wicon_damage");
    expect(out).to.include("wicon_rune");
    expect(out).to.include("wicon_die_attack");
  });

  it("leaves unknown bracket tokens untouched", () => {
    const out = game.replaceSimpleIconsInLog("[totally_made_up_thing]");
    expect(out).to.equal("[totally_made_up_thing]");
  });

  it("[XXX] stays [XXX] when not a defined token", () => {
    const out = game.replaceSimpleIconsInLog("hello [XXX] world");
    expect(out).to.equal("hello [XXX] world");
  });
});
