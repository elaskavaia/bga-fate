<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_sweep: Sweeping Strike — carry overkill damage to the next monster clockwise
 * around the hero after killing an adjacent monster in an attack action.
 *
 * Reads from marker_attack: location = killed hex, state = overkill amount.
 *
 * Behaviour (designer rule clarification, DESIGN.md §"Sweeping Strike"):
 *  - Hero is the centre of the "clock"; sweep walks clockwise around the hero's
 *    own ring of 6 adjacent hexes, starting just past the killed hex.
 *  - Hits the first live monster encountered, dealing the overkill amount.
 *  - Hard cap: at most 1 sweep hit per attack (2 enemies total). Damage left
 *    after the sweep kill is wasted — no chain.
 *  - Clockwise order picks the victim, so the player only answers yes/no; the card
 *    says "may", and declining leaves the damage to Smiterbiter as excess.
 *
 * Bails (ERR_NOT_APPLICABLE) when:
 *  - There's no current attack hex.
 *  - The killed monster wasn't adjacent to the hero.
 *  - There's no overkill damage left.
 *  - No live monster is found anywhere on the hero's adjacent ring.
 *
 * Queues its damage with `spendsExcess` (see Op_applyDamage), which does two things: the
 * damage is taken back out of Smiterbiter's pending pool once it lands, and the kill it
 * causes does not offer this card again.
 *
 * Used by: Sweeping Strike I (card_ability_4_5), Sweeping Strike II (card_ability_4_6).
 */
class Op_c_sweep extends Operation {
    function getPrompt() {
        return clienttranslate('Sweeping Strike deals ${overkill} damage to the next monster clockwise');
    }

    private function getOverkill(): int {
        return $this->game->tokens->getTokenState("marker_attack", 0);
    }

    private function getAttackHex(): ?string {
        return $this->game->getAttackHex();
    }

    /** The monster the sweep would hit - clockwise order picks it, the player does not. */
    private function getSweepHex(): ?string {
        $killedHex = $this->getAttackHex();
        $heroHex = $this->game->getHero($this->getOwner())->getHex();
        foreach ($this->game->hexMap->getAdjacentHexesClockwise($heroHex, $killedHex) as $hex) {
            if ($this->game->hexMap->getCharacterOnHex($hex, "monster") !== null) {
                return $hex;
            }
        }
        return null;
    }

    function getPossibleMoves() {
        $killedHex = $this->getAttackHex();
        if ($killedHex === null) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No attack target")];
        }

        $heroHex = $this->game->getHero($this->getOwner())->getHex();
        if (!in_array($killedHex, $this->game->hexMap->getAdjacentHexes($heroHex), true)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Killed monster was not adjacent")];
        }

        if ($this->getOverkill() <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No overkill damage")];
        }

        if ($this->getSweepHex() === null) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No monster adjacent to hero")];
        }
        return parent::getPossibleMoves(); // yes/no - there is nothing to pick
    }

    function resolve(): void {
        $targetHex = $this->getSweepHex();
        $this->game->systemAssert("ERR:c_sweep:noSweepTarget", $targetHex !== null);

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex, "monster");
        $this->game->systemAssert("ERR:c_sweep:noMonsterOnHex:$targetHex", $defenderId !== null);

        $this->queue("applyDamage", null, [
            "attacker" => $this->game->getHeroTokenId($this->getOwner()),
            "target" => $defenderId,
            "amount" => $this->getOverkill(),
            "spendsExcess" => true,
        ]);
    }

    public function canSkip() {
        return true;
    }

    function getExtraArgs() {
        return ["overkill" => $this->getOverkill()];
    }
}
