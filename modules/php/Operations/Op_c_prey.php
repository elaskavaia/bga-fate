<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_prey: Prey — Mark an undamaged rank 3 monster or an undamaged Legend
 * with 2 gold [XP] markers. It is worth +2 gold [XP].
 *
 * Rules: "Mark an undamaged rank 3 monster or an undamaged Legend with 2 gold
 * [XP] markers. It is worth +2 gold [XP]."
 *
 * Behaviour:
 * - Player selects any monster on the map (no range restriction) matching
 *   (rank==3 or legend) AND having zero red crystals (undamaged).
 * - Two yellow crystals are placed on the monster token as bonus XP.
 * - When the monster is later killed, Monster::evaluateDamage awards
 *   those crystals to the killer in addition to the base XP reward.
 * - Auto-skips if no valid target exists.
 *
 * Used by: Prey (card_event_1_25, card_event_2_36).
 */
class Op_c_prey extends Operation {
    function getPrompt() {
        return clienttranslate("Choose an undamaged rank 3 monster or Legend to mark");
    }

    function canSkip() {
        return true;
    }

    function getPossibleMoves() {
        $targets = [];
        foreach ($this->game->hexMap->getMonstersOnMap() as $entry) {
            $monsterId = $entry["key"];
            if (!$this->isValidTarget($monsterId)) {
                continue;
            }
            $targets[] = $entry["hex"];
        }
        if (count($targets) === 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }
        return $targets;
    }

    private function isValidTarget(string $monsterId): bool {
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId));
        if ($damage > 0) {
            return false;
        }
        return !!$this->game->evaluateExpression("rank==3 or legend", $this->getOwner(), $monsterId);
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $monsterId = $this->game->hexMap->getCharacterOnHex($targetHex, "monster");
        $this->game->systemAssert("ERR:c_prey:noMonsterOnHex:$targetHex", $monsterId !== null);

        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_moveCrystals($heroId, "yellow", 2, $monsterId, [
            "message" => clienttranslate('${char_name} marks ${place_name} as prey'),
        ]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
