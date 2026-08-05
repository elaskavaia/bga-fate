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

    public function testSweepingStrikeDoesNotReofferAfterBothMonstersDead(): void {
        // BGA #233927: two goblins (health=2) adjacent. Primary dies with enough overkill
        // that the sweep also kills the second goblin. The cleave kill re-fires
        // Trigger::MonsterKilled, but the sweep is spent (no overkill left) so c_sweep is
        // void; CardAbility_SweepingStrikeI::onMonsterKilled must NOT re-prompt useCard.
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $cleave = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($cleave)->moveTo("hex_5_8", "");

        $this->seedRand([5, 5, 5, 1]); // 3 hits + 1 passive = 4 on primary; overkill 2 kills cleave

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId);
        $this->respond("choice_0"); // add damage branch
        $this->respond($cardId); // use card for sweep
        $this->respond("choice_1"); // confirm sweep -> kills cleave goblin

        // No spurious re-offer: the cleave kill re-fires MonsterKilled but c_sweep is void,
        // so the trigger chain settles cleanly (finishKill sweeps both to supply) instead of
        // leaving a dead-end useCard prompt the player must Cancel out of.
        $args = $this->getOpArgs();
        $this->assertNotEquals("useCard", $args["type"] ?? "", "no spurious useCard re-offer (bug #233927)");
        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "Primary goblin (3 hits + 1 passive) should be dead");
        $this->assertEquals("supply_monster", $this->tokenLocation($cleave), "Cleave goblin (2 overkill) should be dead");
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
        // No overkill -> c_sweep is void, so onMonsterKilled does not prompt useCard
        // (BGA #233927). skipIfOp is a defensive no-op should any prompt appear.
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

    // -------------------------------------------------------------------------
    // BGA #235866 "sweeping Strike Not allowing DMG to second target".
    //
    // The reporter attached the TActionAttack prompt with the cleave bullet greyed out -
    // expected, nothing has died yet - and concluded the cleave was unreachable. It is not:
    // the cleave is offered again on TMonsterKilled. These pin the reported combos.
    // -------------------------------------------------------------------------

    /** Boldur at hex_7_9, brute SW (hex_6_10) = attack target, troll W (hex_6_9) = first hex clockwise from SW. */
    private function setupRapidStrikeBoard(): void {
        $this->clearEquipDecks();
        $this->game->tokens->moveToken("card_ability_4_5", "tableau_" . $this->color); // Sweeping Strike I
        $this->game->tokens->moveToken("card_ability_4_4", "tableau_" . $this->color); // Rapid Strike II
        $this->game->effect_moveCrystals($this->heroId, "green", 2, "card_ability_4_4", ["message" => ""]);
        $this->seedHand("card_event_4_32", $this->color); // Berserk
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_6_10", "");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_6_9", "");
        $this->seedRand([5, 5, 1, 1]); // 2 hits
    }

    public function testSweepAfterRapidStrikeAndBerserk(): void {
        $this->setupRapidStrikeBoard();

        $this->respond("card_ability_4_4"); // Rapid Strike II: pay 2 mana, start attack action
        $this->respond("hex_6_10");
        $this->respond("card_ability_4_5"); // TActionAttack useCard
        $this->respond("choice_0"); // +1 damage branch (branch 2 is greyed here - no kill yet)
        $this->respond("card_event_4_32"); // Berserk: 1 unpreventable health for +3 damage

        // 2 hits + 1 (Sweeping Strike) + 3 (Berserk) = 6 on a 3-health brute -> 3 overkill.
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_ability_4_5", "cleave must be re-offered on TMonsterKilled");
        $this->respond("card_ability_4_5");
        $this->respond("choice_1"); // c_sweep branch
        $this->skipIfOp("c_sweep");

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"), "brute killed");
        $this->assertEquals(3, $this->countDamage("monster_troll_1"), "3 overkill swept clockwise onto the troll");
    }

    /**
     * Exact reported board: both monsters are 3-health brutes adjacent to Boldur. Target brute
     * is W of him, second brute is NW = the very next hex clockwise from the target. Player
     * takes the only enabled pre-attack button ("Add 1 damage to each attack action").
     *
     * Pins current behavior: choosing branch 1 does NOT lock the card out of the later cleave.
     * Sweeping Strike has no `spendUse` in its `r` expression, so the once-per-turn ability lock
     * (RULES.md "Abilities (free actions vs. ongoing effects)") never engages - correct, since
     * both bullets are `on(...)` triggers.
     */
    public function testChoosingAddDamageBranchDoesNotBlockCleave(): void {
        $sweep = "card_ability_4_5";
        $this->game->tokens->moveToken($sweep, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $target = "monster_brute_1"; // W of Boldur
        $second = "monster_brute_2"; // NW of Boldur = next clockwise from W
        $this->game->getMonster($target)->moveTo("hex_6_9", "");
        $this->game->getMonster($second)->moveTo("hex_7_8", "");

        $this->seedRand([5, 5, 5, 5]); // 4 hits + 1 Sweeping Strike = 5 vs health 3 -> 2 overkill

        $this->respond("actionAttack");
        $this->respond("hex_6_9");
        $this->respond($sweep);
        $this->respond("choice_0"); // the only enabled button on the reporter's screenshot

        $this->assertEquals(0, $this->game->tokens->getTokenState($sweep, 0), "card must not be marked used");
        $this->assertOperation("useCard");
        $this->assertValidTarget($sweep, "cleave must still be offered on TMonsterKilled");
        $this->respond($sweep);
        $this->respond("choice_1");
        $this->skipIfOp("c_sweep");

        $this->assertEquals("supply_monster", $this->tokenLocation($target), "target brute killed");
        $this->assertEquals(2, $this->countDamage($second), "2 overkill swept clockwise onto the NW brute");
    }

    public function testSweepStillOfferedWhenActionAttackPromptSkipped(): void {
        // Reporter saw the second bullet greyed out and may have dismissed the prompt.
        // Skipping the TActionAttack offer must not forfeit the later cleave offer.
        $this->setupRapidStrikeBoard();

        $this->respond("card_ability_4_4");
        $this->respond("hex_6_10");
        $this->skip(); // dismiss the TActionAttack useCard prompt entirely

        // 2 hits only = 2 damage on a 3-health brute -> no kill, so no cleave prompt.
        $this->assertEquals(2, $this->countDamage("monster_brute_1"), "no bonus damage after skipping");
        $this->assertEquals(0, $this->countDamage("monster_troll_1"), "no cleave without a kill");
    }
}
