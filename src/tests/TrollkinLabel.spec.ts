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

/** BGA #251273 / #240269: trollkin attack label after an adjacent trollkin dies. */
describe("Trollkin attack label after kill", () => {
  let game: Game;

  function addHex(hexId: string): void {
    const hex = document.createElement("div");
    hex.id = hexId;
    $("map_wrapper")!.appendChild(hex);
  }

  function shownAttack(monsterId: string): string | undefined {
    return $(`counter_attack_${monsterId}`)?.dataset.state;
  }

  beforeEach(async () => {
    document.body.innerHTML = '<div id="ebd-body"><div id="map_wrapper"><div id="supply_monster"></div></div></div>';
    game = new Game(createMockBga());
    (game as any).animationLa = {
      shrinkAndFade: sinon.stub().resolves(),
      pulse: sinon.stub(),
      phantomMove: (token: string, place: string, _d: number, _s: any, onEnd?: (node?: HTMLElement) => void) => {
        $(place)!.appendChild($(token)!);
        onEnd?.($(token)!);
      }
    };
    (game as any).gamedatas = {
      tokens: {},
      token_types: {
        monster_goblin: { strength: 1, health: 2, faction: "trollkin" },
        monster_brute: { strength: 2, health: 3, faction: "trollkin" }
      }
    };
    sinon.stub(game, "updateTooltip");
    addHex("hex_5_9");
    addHex("hex_5_8");
    await game.placeTokenServer("monster_goblin_1", "hex_5_9", 0);
    await game.placeTokenServer("monster_brute_1", "hex_5_8", 0);
  });

  it("shows the support bonus while the brute is adjacent", () => {
    expect(shownAttack("monster_goblin_1")).to.equal("2");
  });

  it("keeps the stale bonus after the brute is killed", async () => {
    await game.placeTokenServer("monster_brute_1", "supply_monster", 0);
    expect(game.getCharHex("monster_brute_1")).to.be.null;
    expect(game.getEffectiveMonsterAttack("monster_goblin_1", 1)).to.equal(1);
    // Pins buggy behavior for BGA #251273 - flip the assertion when the fix lands
    expect(shownAttack("monster_goblin_1")).to.equal("2");
  });
});
