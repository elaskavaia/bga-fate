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

use function Bga\Games\Fate\getPart;

/**
 * Monster attack: a single monster attacks an adjacent hero.
 * Queued from Op_turnMonster with data ["char" => $monsterId].
 */
class Op_monsterAttack extends Operation {
    function resolve(): void {
        $monsterId = $this->getDataField("char", "");
        $this->game->systemAssert("Missing monster ID in monsterAttack", $monsterId);

        // Check monster is still alive (on the map)
        $monster = $this->game->getMonster($monsterId);
        $monsterHex = $monster->getHex();
        if ($monsterHex === null) {
            return; // Monster was killed or removed
        }

        // Seer of Odin (II) — special attack that bypasses dice + cover + range.
        if ($monsterId === "monster_legend_2_2") {
            $this->resolveSeerAttack($monsterId);
            return;
        }

        // Target may be pinned by Op_monsterAttackAll's grouping pass. Re-evaluate
        // if the pinned hex is no longer occupied by a hero (earlier attack in the
        // batch may have KO'd or relocated the defender).
        $heroHex = $this->getDataField("target", "");
        if (!$heroHex || $this->game->hexMap->isOccupiedByCharacterType($heroHex, "hero") === null) {
            $heroHex = $this->findHeroHex();
        }
        if (!$heroHex) {
            return; // No heroes to attack
        }

        $strength = $this->getMonsterStrength($monsterHex);

        $this->game->tokens->dbSetTokenLocation("marker_attack", $heroHex, 0, "");
        $this->game->tokens->dbSetTokenLocation("marker_instigator", $monsterId, 0, "");
        $this->queue("roll", null, [
            "attacker" => $monsterId,
            "target" => $heroHex,
            "count" => $strength,
        ]);
        $this->queueDreadnoughtIIReflect($heroHex, $monsterHex);
        $this->queue("endOfAttack");
    }

    /**
     * Dreadnought II: "Each adjacent monster that attacks you is dealt 1 damage after its attack."
     * Hardcoded here because there's no player interaction; the reflect just fires automatically.
     */
    private function queueDreadnoughtIIReflect(string $heroHex, string $monsterHex): void {
        $defenderId = $this->game->hexMap->isOccupiedByCharacterType($heroHex, "hero");
        if ($defenderId === null) {
            return;
        }
        $defenderOwner = $this->game->getHeroOwner($defenderId);
        if (!$defenderOwner) {
            return;
        }
        $defender = $this->game->getHero($defenderOwner);
        if (!$defender->heroHasCardsOnTableau("card_ability_4_12")) {
            return;
        }
        if (!in_array($monsterHex, $this->game->hexMap->getAdjacentHexes($heroHex), true)) {
            return;
        }
        $this->queue("dealDamage", $defenderOwner, [
            "attacker" => $defenderId,
            "target" => $monsterHex,
            "count" => 1,
            "reason" => "card_ability_4_12",
        ]);
    }

    /**
     * Hero token id this monster would attack, or null if none in range.
     * Used by Op_monsterAttackAll to group attacks by defender.
     */
    public function findHeroTarget(): ?string {
        $monsterId = $this->getDataField("char", "");
        if (!$monsterId) {
            return null;
        }
        if ($this->game->getMonster($monsterId)->getHex() === null) {
            return null;
        }
        $heroHex = $this->findHeroHex();
        if (!$heroHex) {
            return null;
        }
        return $this->game->hexMap->isOccupiedByCharacterType($heroHex, "hero");
    }

    /**
     * Pick a hero hex in attack range (delegates target enumeration to Op_roll).
     */
    private function findHeroHex(): ?string {
        $monsterId = $this->getDataField("char", "");
        $rollOp = $this->instantiateOperation("roll", null, ["attacker" => $monsterId]);
        $hexes = $rollOp->getArgs()["target"];
        if (empty($hexes)) {
            return null;
        }
        // TODO: Hero selection — currently picks first.
        // Rules may require different targeting logic (e.g. closest, random, player choice).
        return $this->pickTarget($hexes);
    }

    /**
     * Seer of Odin (II) special attack: 1 unpreventable damage to every hero still on the map.
     */
    private function resolveSeerAttack(string $attackerId): void {
        $this->game->notifyMessage(clienttranslate('${token_name} foresees doom - every hero takes 1 unpreventable [DAMAGE]'), [
            "token_name" => $attackerId,
        ]);
        foreach ($this->game->getPlayerColors() as $owner) {
            // Knocked-out heroes sit in Grimheim and are out of Seer's reach.
            if ($this->game->hexMap->isInGrimheim($this->game->getHero($owner)->getHex())) {
                continue;
            }
            $this->queue("applyDamage", null, [
                "attacker" => $attackerId,
                "target" => $this->game->getHeroTokenId($owner),
                "amount" => 1,
            ]);
        }
    }

    /**
     * Pick first
     */
    private function pickTarget(array $hexes): string {
        return $hexes[0];
    }

    /**
     * Get monster attack strength including Trollkin faction bonus.
     * Trollkin monsters get +1 for each other adjacent Trollkin monster near the target hero.
     */
    private function getMonsterStrength(string $monsterHex): int {
        $monsterId = $this->game->hexMap->getCharacterOnHex($monsterHex);
        $strength = (int) $this->game->getRulesFor($monsterId, "strength", 1);
        $faction = $this->game->getRulesFor($monsterId, "faction", "");

        if ($faction === "trollkin") {
            //**Trollkin Effect:** All trollkin get +1 attack strength for each other trollkin adjacent to them.

            $adjacentHexes = $this->game->hexMap->getAdjacentHexes($monsterHex);
            foreach ($adjacentHexes as $hex) {
                $char = $this->game->hexMap->getCharacterOnHex($hex);
                if ($char !== null && $char !== $monsterId && getPart($char, 0) === "monster") {
                    $otherFaction = $this->game->getRulesFor($char, "faction", "");
                    if ($otherFaction === $faction) {
                        $strength++;
                    }
                }
            }
        }

        // Monster Die `attack` side: +1 strength to every monster attack this turn (RULES.md §2).
        if ($this->game->getMonsterDieSide() === "attack") {
            $strength++;
        }

        return $strength;
    }
}
