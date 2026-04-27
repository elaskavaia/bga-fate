<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

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
 *  - Hard cap: at most 1 cleave hit per attack (2 enemies total). Damage left
 *    after the cleave kill is wasted — no chain.
 *  - Auto-resolves: cleave is mandatory once the card is in play and a target exists.
 *
 * Bails (ERR_NOT_APPLICABLE) when:
 *  - There's no current attack hex.
 *  - The killed monster wasn't adjacent to the hero.
 *  - There's no overkill damage left.
 *  - No live monster is found anywhere on the hero's adjacent ring.
 *
 * Used by: Sweeping Strike I (card_ability_4_5), Sweeping Strike II (card_ability_4_6).
 */
class Op_c_sweep extends Operation {
    function getPrompt() {
        return clienttranslate('Sweeping Strike deals ${overkill} damage to ${token_name}');
    }

    private function getOverkill(): int {
        return $this->game->tokens->getTokenState("marker_attack", 0);
    }

    private function getKilledHex(): ?string {
        return $this->game->getAttackHex();
    }

    function getPossibleMoves() {
        $killedHex = $this->getKilledHex();
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

        foreach ($this->game->hexMap->getAdjacentHexesClockwise($heroHex, $killedHex) as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex, "monster");
            if ($charId !== null) {
                return [$hex => ["q" => Material::RET_OK]];
            }
        }
        return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No monster to sweep into")];
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $overkill = $this->getOverkill();
        $attackerId = $this->game->getHeroTokenId($this->getOwner());

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:c_sweep:noMonsterOnHex:$targetHex", $defenderId !== null);

        $this->game->effect_moveCrystals($attackerId, "red", $overkill, $defenderId, [
            "message" => "",
        ]);

        $defender = $this->game->getCharacter($defenderId);
        $defender->applyDamageEffects($overkill, $attackerId);
    }

    function getExtraArgs() {
        return ["overkill" => $this->getOverkill()];
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
