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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_wrecking: Wrecking Ball push phase. Boldur has just stepped into the occupied
 * hex (Op_move Wrecking Ball branch); this op asks where the displaced character goes,
 * moves it there, then deals 1 damage through the proper pipeline (cover, armor,
 * damage effects, kill trigger). The move loop itself continues in Op_move,
 * which is queued right after this op.
 *
 * Used by:
 *   - card_ability_4_7 Wrecking Ball I
 *   - card_ability_4_8 Wrecking Ball II (also passive +1 move)
 *
 * Designer rule clarifications (DESIGN.md §"Wrecking Ball"):
 *   - May push the displaced character into the hex Boldur just came from
 *     (swap places — the "pendulum").
 *   - "Character" includes both monsters and heroes.
 *   - Cannot push a character out of Grimheim (Grimheim isn't an "occupied area");
 *     Grimheim hexes are never offered as Wrecking Ball targets.
 *
 * Data:
 * - displaced: token id of the character to push (required).
 */
class Op_c_wrecking extends Operation {
    private function getDisplaced(): string {
        return (string) $this->getDataField("displaced", "");
    }

    function getPrompt() {
        return clienttranslate('Wrecking Ball: Choose where to push ${char_name}');
    }

    function getExtraArgs() {
        return ["char_name" => $this->getDisplaced()];
    }

    function getPossibleMoves() {
        $displaced = $this->getDisplaced();
        if ($displaced === "") {
            return []; // bare instantiation (op smoke test); real queueing always sets displaced
        }
        $hero = $this->game->getHero($this->getOwner());
        $boldurHex = $hero->getHex();
        $this->game->systemAssert("ERR:c_wrecking:noHeroHex:" . $this->getOwner(), $boldurHex !== null);
        $displacedChar = $this->game->getCharacter($displaced);

        $targets = [];
        foreach ($this->game->hexMap->getAdjacentHexes($boldurHex) as $hex) {
            if ($this->game->hexMap->canStopOn($hex, $displacedChar)) {
                $targets[] = $hex;
            }
        }
        return $targets;
    }

    function resolve(): void {
        $displaced = $this->getDisplaced();
        $this->game->systemAssert("ERR:c_wrecking:noDisplaced", $displaced !== "");
        $pushHex = $this->getCheckedArg();
        $attackerId = $this->game->getHeroTokenId($this->getOwner());
        $character = $this->game->getCharacter($displaced);

        // Push first so the damage pipeline sees a single-occupant hex.
        $character->moveTo($pushHex, clienttranslate('${char_name} pushes ${char_name2} with Wrecking Ball'), [
            "char_name" => $attackerId,
            "char_name2" => $displaced,
        ]);

        $this->queue("dealDamage", null, [
            "attacker" => $attackerId,
            "target" => $pushHex,
            "count" => 1,
        ]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
