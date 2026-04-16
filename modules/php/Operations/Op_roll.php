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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\CountableOperation;

use function Bga\Games\Fate\getPart;

/**
 * roll: Roll X attack dice against a target monster.
 * Count = number of dice to roll.
 * Parameter: range filter — "adj" (adjacent), "inRange" (hero attack range), "inRangeN" (fixed range N).
 * After rolling, queues resolveHits to convert dice into damage.
 * Used by: Snipe, Hard Rock, Fire Spark, Throwing Axes/Darts/Knives, etc.
 */
class Op_roll extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Choose a monster to attack with ${count} dice');
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "inRange"));
    }

    /**
     * Resolve the attacker token id: the `attacker` data field if set, otherwise
     * the owner's hero. Asserts non-empty — a roll without an attacker is a bug.
     */
    private function getAttackerId(): string {
        $attackerId = $this->getDataField("attacker") ?? $this->game->getHeroTokenId($this->getOwner());
        $this->game->systemAssert("ERR:roll:noAttacker", $attackerId);
        return $attackerId;
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }

        if ($this->isAddition()) {
            $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
            if (count($diceOnDisplay) == 0) {
                return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Not possible at this moment")];
            }
        }

        $attackerId = $this->getAttackerId();

        // Monster attacking: find hero hexes in range (excluding Grimheim)
        if (getPart($attackerId, 0) === "monster") {
            $monster = $this->game->getMonster($attackerId);
            $monsterHex = $monster->getHex();
            $this->game->systemAssert("ERR:roll:monsterNotOnMap:$attackerId", $monsterHex !== null);
            $hexesInRange = $this->game->hexMap->getHexesInRange($monsterHex, $monster->getAttackRange());
            $targets = [];
            foreach ($hexesInRange as $hex) {
                if ($this->game->hexMap->isInGrimheim($hex)) {
                    continue;
                }
                if ($this->game->hexMap->isOccupiedByCharacterType($hex, "hero") !== null) {
                    $targets[$hex] = ["q" => Material::RET_OK];
                }
            }
            return $targets;
        }

        // Hero attacking: find monster hexes in range
        $hero = $this->game->getHeroById($attackerId);
        if ($this->game->hexMap->isInGrimheim($hero->getHex())) {
            return [];
        }
        return $hero->getMonsterHexesInRange($this->getRange());
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:roll:noCharOnHex:$targetHex", $defenderId !== null);

        $attackerId = $this->getAttackerId();
        $diceCount = (int) $this->getCount();

        // Roll dice onto display_battle
        $add = $this->isAddition();
        $this->game->effect_rollAttackDice($attackerId, $defenderId, $diceCount, $add);

        // Only trigger on player rolls (hero is attacker), not monster rolls.
        // Emit the most specific trigger; ActionAttack chains through Roll so cards
        // listening on TRoll are still offered during attack rolls (Trigger::chain).
        if (str_starts_with($attackerId, "hero_")) {
            $trigger = $this->getReason() == "Op_actionAttack" ? Trigger::ActionAttack : Trigger::Roll;
            $this->queueTrigger($trigger);
        }

        // Queue resolveHits to convert dice into dealDamage
        $this->queue("resolveHits", null, [
            "attacker" => $attackerId,
            "target" => $targetHex,
        ]);
    }
    function isAddition() {
        return false;
    }
    public function getUiArgs() {
        return ["buttons" => false];
    }
}
