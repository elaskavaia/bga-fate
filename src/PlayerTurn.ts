import type { Game } from "./Game";

export class PlayerTurn {
  private game: Game;
  private bga: Bga;

  constructor(game: Game, bga: Bga) {
    this.game = game;
    this.bga = bga;
  }

  onEnteringState(args: any, isCurrentPlayerActive: boolean) {
    if (args._private) this.game.onEnteringState_PlayerTurn(args._private);
    else this.game.onEnteringState_PlayerTurn(args);
  }

  onLeavingState(args: any, isCurrentPlayerActive: boolean) {
    this.game.onLeavingState("PlayerTurn", args);
  }

  onPlayerActivationChange(args: any, isCurrentPlayerActive: boolean) {}
}
