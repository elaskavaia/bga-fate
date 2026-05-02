<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Sweeping Strike I/II (card_ability_4_5, card_ability_4_6).
 *
 * Bespoke card classes (CardAbility_SweepingStrikeI/II) wire the card to two
 * trigger families that the generic CSV-only `on=` field cannot express:
 * onActionAttack (for the passive +damage branch) and onMonsterKilled (for the
 * cleave). Each hook just calls promptUseCard($event); the OR-split inside the
 * card's `r` expression then routes to the right branch via on(...) gates.
 *
 * Hard cap: at most 2 enemies hit per attack (no chain after the cleave kill).
 * Designer rule clarification: DESIGN.md §"Sweeping Strike".
 */
class Campaign_BoldurSweepTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    public function testSweepingStrikeIAddsOneDamagePassive(): void {
        // Boldur on plains, troll (health=7) adjacent. All 4 dice miss, but +1 from
        // Sweeping Strike I → 1 damage on the troll.
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);

        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_4_9", "");
        $this->seedRand([1, 1, 1, 1]); // all miss
        $this->respond("actionAttack");
        // With only the troll on the map, Op_actionAttack's target is auto-picked.
        $this->respond($cardId); // useCard prompt for TActionAttack
        $this->respond("choice_0"); // Op_or → addDamage branch

        $this->assertEquals(1, $this->countDamage($troll), "Sweeping Strike I should add 1 damage to attack");
        $this->assertEquals("hex_4_9", $this->tokenLocation($troll), "Troll should still be alive");
    }

    public function testSweepingStrikeICleavesAfterKill(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $cleave = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($cleave)->moveTo("hex_5_8", "");

        $this->seedRand([5, 5, 1, 1]);

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId);
        $this->respond("choice_0"); // confirm add damage
        $this->respond($cardId); // use card for sweep
        $this->respond("choice_1"); // confirm sweep

        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "Primary goblin should be dead");
        $this->assertEquals(1, $this->countDamage($cleave), "Cleave goblin should take 1 overkill damage");
    }

    public function testSweepingStrikeIDoesNotCleaveWithoutOverkill(): void {
        // Goblin health=2, armor=0. 1 hit + 1 sweep die = 2 damage → exact kill, 0 overkill.
        // c_sweep should bail on ERR_NOT_APPLICABLE; cleave goblin takes nothing.
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $cleave = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($cleave)->moveTo("hex_5_8", "");

        $this->seedRand([5, 1, 1, 1]); // 1 hit + sweep(1) = 2 damage → kill with 0 overkill

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId); // useCard for TActionAttack
        $this->respond("choice_0"); // addDamage branch
        // After my-applyDamage refactor, TMonsterKilled fires *before* the kill is
        // finalised, so the goblin sits on its hex while the trigger walks the tableau.
        // CardAbility_SweepingStrikeI prompts useCard unconditionally on TMonsterKilled;
        // skip it — there's no overkill and Op_c_sweep would bail anyway.
        $this->skipIfOp("useCard");

        $this->assertEquals("supply_monster", $this->tokenLocation($primary));
        $this->assertEquals(0, $this->countDamage($cleave), "No overkill → no cleave damage");
        $this->assertEquals("hex_5_8", $this->tokenLocation($cleave), "Cleave goblin should still be alive");
    }

    public function testSweepingStrikeIIScalesWithAdjacentMonsterCount(): void {
        // countAdjMonsters = 3 (target troll + 2 goblins) → sweep adds 3 hit dice.
        // Troll: health=7, armor=0. 2 base hits + 3 sweep = 5 damage. Troll survives.
        $cardId = "card_ability_4_6";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_4_9", "");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_5_8", "");
        $this->game->getMonster("monster_goblin_2")->moveTo("hex_6_8", "");

        $this->seedRand([5, 5, 1, 1]); // 2 hits, 2 misses
        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId); // useCard for TActionAttack
        $this->respond("choice_0"); // counter(countAdjMonsters):addDamage branch

        // 2 base hits + 3 sweep = 5 damage. Troll health=7 → survives.
        $this->assertEquals(5, $this->countDamage($troll), "Sweeping Strike II should add +3 (countAdjMonsters)");
        $this->assertEquals("hex_4_9", $this->tokenLocation($troll), "Troll should still be alive");
    }
}
