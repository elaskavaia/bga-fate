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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * dealDamage: Deal X direct damage (red crystals, no dice) to target character.
 *
 * Params:
 * - param(0): range specifier — hero-centered via getRangeFromParam() ("adj", "inRange", "inRange3"),
 *             or "adj_attack" meaning "monsters adjacent to the current attack target hex"
 *             (excluding the attack hex itself). Default "adj".
 * - param(1): optional filter expression evaluated per monster — e.g. "'rank<=2'", "'rank==3 or legend'" (default "true")
 *
 * Data Fields:
 * - target: preset hex target (skips getPossibleMoves() selection)
 * - attacker: token id of the attacker (defaults to the owner's hero)
 *
 * Behaviour:
 * - Normal case: player selects a monster hex in range matching the filter; deal count damage; if killed, hero gains XP.
 * - Can target heroes too (attacker field set by caller).
 *
 * Used by: Kick, Courage, Lightning Bolt, Retaliation, Vigilance, Heels,
 *          Bone Bane Bow (adj_attack), Fireball II (adj_attack), etc.
 */
class Op_dealDamage extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Choose a monster to deal ${count} damage to');
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "adj"));
    }

    private function matchesFilter(string $monsterId): bool {
        $filter = $this->getParam(1, "true");
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $monsterId);
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        if ($this->getParam(0, "adj") === "adj_attack") {
            return $this->getAdjacentToAttackHexes();
        }
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($this->getRange(), fn($mId) => $this->matchesFilter($mId));
        return $hexes;
    }

    /**
     * Hexes with a monster adjacent to the current attack target hex,
     * excluding the attack hex itself, filtered by param(1).
     */
    private function getAdjacentToAttackHexes(): array {
        $attackHex = $this->game->getAttackHex();
        if ($attackHex === null) {
            return [];
        }
        $targets = [];
        foreach ($this->game->hexMap->getAdjacentHexes($attackHex) as $hexId) {
            if ($hexId === $attackHex) {
                continue;
            }
            $monsterId = $this->game->hexMap->isOccupiedByCharacterType($hexId, "monster");
            if ($monsterId !== null && $this->matchesFilter($monsterId)) {
                $targets[] = $hexId;
            }
        }
        return $targets;
    }

    protected function getDamageAmount(string $defenderId): int {
        return (int) $this->getCount();
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $attackerId = $this->getDataField("attacker");
        if ($attackerId === null) {
            $attackerId = $this->game->getHeroTokenId($this->getOwner());
        }

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex, null);
        $this->game->systemAssert("ERR:dealDamage:noCharacterOnHex:$targetHex", $defenderId !== null);

        $amount = $this->getDamageAmount($defenderId);

        $this->queue("applyDamage", null, ["attacker" => $attackerId, "target" => $defenderId, "amount" => $amount]);
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false; // mandatory if possible
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
