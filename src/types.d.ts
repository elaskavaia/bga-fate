/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

interface CustomPlayer extends Player {
  heroNo?: number;
}

interface CustomGamedatas extends Gamedatas<CustomPlayer> {
  tokens: { [key: string]: Token };
  token_types: { [key: string]: any };
  counters: { [key: string]: { value: number } };
}
