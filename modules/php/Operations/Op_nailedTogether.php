<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * nailedTogether: Pierce remaining damage through to a monster behind the killed one.
 *
 * Reads from marker_attack: location = killed hex, state = overkill amount.
 *
 * Params:
 * - param(0): "chain" for Level II (re-queues on subsequent kills), empty for Level I (one pierce)
 *
 * Behaviour:
 * - Finds monsters on hexes adjacent to killedHex that are farther from the hero
 * - Player picks a target (or auto if only one)
 * - Deals overkill damage to chosen monster
 * - Level II: if that monster also dies, updates marker_attack and re-queues
 *
 * Used by: Nailed Together I (nailedTogether), Nailed Together II (nailedTogether(chain))
 */
class Op_nailedTogether extends Operation {
    function getPrompt() {
        return clienttranslate('Choose a monster to deal ${overkill} piercing damage to');
    }

    private function isChain(): bool {
        return $this->getParam(0, "") === "chain";
    }

    private function getOverkill(): int {
        return (int) ($this->game->tokens->getTokenInfo("marker_attack")["state"] ?? 0);
    }

    private function getKilledHex(): ?string {
        return $this->game->getAttackHex();
    }

    function getPossibleMoves() {
        $killedHex = $this->getKilledHex();
        if ($killedHex === null) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No attack target")];
        }
        $overkill = $this->getOverkill();
        if ($overkill <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No overkill damage")];
        }

        $heroHex = $this->game->getHero($this->getOwner())->getHex();
        $behindHexes = $this->game->hexMap->getHexesBehind($heroHex, $killedHex);

        $targets = [];
        foreach ($behindHexes as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null && getPart($charId, 0) === "monster") {
                $targets[$hex] = ["q" => Material::RET_OK];
            }
        }

        if (empty($targets)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No monster behind target")];
        }
        return $targets;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $overkill = $this->getOverkill();
        $attackerId = $this->game->getHeroTokenId($this->getOwner());

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:nailedTogether:noMonsterOnHex:$targetHex", $defenderId !== null);

        // Deal overkill damage
        $this->game->effect_moveCrystals($attackerId, "red", $overkill, $defenderId, [
            "message" => "",
        ]);

        $defender = $this->game->getCharacter($defenderId);
        $remaining = $defender->applyDamageEffects($overkill, $attackerId);

        // Level II: chain — update marker_attack to new killed hex and overkill
        if ($this->isChain() && $remaining <= 0) {
            $newOverkill = abs($remaining);
            $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, $newOverkill, "");
            $this->queue("nailedTogether(chain)");
        }
    }

    function getExtraArgs() {
        return ["overkill" => $this->getOverkill()];
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
