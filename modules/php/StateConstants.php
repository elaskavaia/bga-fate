<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate;

class StateConstants {
    // states for operaton stack games
    const STATE_PLAYER_TURN = 12;
    const STATE_GAME_DISPATCH = 13;
    const STATE_MULTI_PLAYER_TURN_PRIVATE = 14;
    const STATE_PLAYER_TURN_CONF = 15;
    const STATE_GAME_DISPATCH_FORCED = 17;
    const STATE_MULTI_PLAYER_MASTER = 16;
    const STATE_MULTI_PLAYER_WAIT_PRIVATE = 18;

    // special state to indicate that stack machine is empty
    const STATE_MACHINE_HALTED = 42;

    // last state
    const STATE_END_GAME = 99;
}
