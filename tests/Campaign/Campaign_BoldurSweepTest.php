<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Sweeping Strike I/II (card_ability_4_5, card_ability_4_6).
 *
 * Bespoke card classes (CardAbility_SweepingStrikeI/II) wire the card to two
 * trigger families that the generic CSV-only `on=` field cannot express.
 * onActionAttack queues the damage effect outright - that bullet has no "may" -
 * while onMonsterKilled prompts, since the sweep does.
 *
 * Hard cap: at most 2 enemies hit per attack (no chain after the sweep kill).
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
        // The damage bullet is passive - no prompt to answer.

        $this->assertEquals(1, $this->countDamage($troll), "Sweeping Strike I should add 1 damage to attack");
        $this->assertEquals("hex_4_9", $this->tokenLocation($troll), "Troll should still be alive");
    }

    public function testSweepingStrikeISweepsAfterKill(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $second = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_8", "");

        $this->seedRand([5, 5, 1, 1]);

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId); // use card for sweep
        $this->confirmCardEffect(); // the sweep target is fixed by clockwise order

        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "Primary goblin should be dead");
        $this->assertEquals(1, $this->countDamage($second), "Second goblin should take 1 overkill damage");
    }

    public function testSweepingStrikeDoesNotReofferAfterBothMonstersDead(): void {
        // BGA #233927: two goblins (health=2) adjacent. Primary dies with enough overkill
        // that the sweep also kills the second goblin. The sweep kill re-fires
        // Trigger::MonsterKilled, but the sweep is spent (no overkill left) so c_sweep is
        // void; CardAbility_SweepingStrikeI::onMonsterKilled must NOT re-prompt useCard.
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $second = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_8", "");

        $this->seedRand([5, 5, 5, 1]); // 3 hits + 1 passive = 4 on primary; overkill 2 kills the second goblin

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId); // use card for sweep
        $this->confirmCardEffect(); // sweep -> kills the second goblin

        // No spurious re-offer: the sweep kill re-fires MonsterKilled but c_sweep is void,
        // so the trigger chain settles cleanly (finishKill sweeps both to supply) instead of
        // leaving a dead-end useCard prompt the player must Cancel out of.
        $args = $this->getOpArgs();
        $this->assertNotEquals("useCard", $args["type"] ?? "", "no spurious useCard re-offer (bug #233927)");
        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "Primary goblin (3 hits + 1 passive) should be dead");
        $this->assertEquals("supply_monster", $this->tokenLocation($second), "Second goblin (2 overkill) should be dead");
    }

    /**
     * One sweep per attack action. The sweep's own kill re-fires TMonsterKilled, so with
     * damage still left over and a third monster on the ring the card used to offer itself
     * again - and again, each time hitting a corpse that was already dead.
     */
    public function testSweepingStrikeDoesNotChainOntoAThirdMonster(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1"; // health 2, W of Boldur
        $second = "monster_goblin_2"; // health 2, next clockwise
        $third = "monster_goblin_3"; // clockwise again - must be left alone
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_8", "");
        $this->game->getMonster($third)->moveTo("hex_6_8", "");

        // 4 hits + 1 passive = 5 on a 2-health goblin -> 3 over, sweep kills with 1 to spare.
        $this->seedRand([5, 5, 5, 5]);

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId);
        $this->confirmCardEffect();

        $args = $this->getOpArgs();
        $this->assertNotEquals("useCard", $args["type"] ?? "", "the sweep must not offer itself again");
        $this->assertEquals("supply_monster", $this->tokenLocation($primary));
        $this->assertEquals("supply_monster", $this->tokenLocation($second));
        $this->assertEquals(0, $this->countDamage($third), "the third monster is beyond the 2-enemy cap");
    }

    /** Find a log entry whose template contains $needle. */
    private function findLog(string $needle): ?string {
        foreach ($this->game->notify->_getNotifications() as $notif) {
            $log = $notif["log"] ?? "";
            if (is_string($log) && str_contains($log, $needle)) {
                return $log;
            }
        }
        return null;
    }

    /**
     * Smiterbiter's stored damage is what lands the kill here. It is spent from the card, not
     * drawn from the pending-excess pool, so it must not carry `spendsExcess` and must not
     * suppress the sweep the way the sweep's own kill does.
     */
    public function testSweepOfferedWhenSmiterbiterDamageLandsTheKill(): void {
        $sweep = "card_ability_4_5";
        $smiter = "card_equip_4_21";
        $rapid = "card_ability_4_4";
        $this->clearEquipDecks();
        $this->game->tokens->moveToken($sweep, "tableau_" . $this->color);
        $this->game->tokens->moveToken($smiter, "tableau_" . $this->color);
        $this->game->tokens->moveToken($rapid, "tableau_" . $this->color);
        $this->game->effect_moveCrystals($this->heroId, "green", 2, $rapid, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $smiter, ["message" => ""]);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $primary = "monster_skeleton_1"; // health 3, NW of Boldur
        $second = "monster_skeleton_2"; // NE = next clockwise
        $this->game->getMonster($primary)->moveTo("hex_6_9", "");
        $this->game->getMonster($second)->moveTo("hex_7_8", "");

        $this->seedRand([5, 5, 1, 1]); // 2 hits + 1 passive = 3, exactly lethal before Smiterbiter

        $this->respond($rapid);
        $this->respond("hex_6_9");
        $this->respond($smiter);
        $this->respond("3"); // 3 stored damage -> 6 total, 3 overkill

        $this->assertOperation("useCard");
        $this->assertValidTarget($sweep, "Smiterbiter's damage must not suppress the sweep");
    }

    /**
     * The clock is centred on Boldur, not on the monster he killed - a monster that only
     * touches the corpse is out of reach. Silently skipping that reads as a broken card,
     * so the void sweep names its missing precondition in the log.
     */
    public function testVoidSweepIsExplainedInTheLog(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $primary = "monster_goblin_1"; // NW of Boldur
        $second = "monster_goblin_2"; // touches the goblin, not Boldur
        $this->game->getMonster($primary)->moveTo("hex_6_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_9", "");

        $this->seedRand([5, 5, 5, 5]); // 4 hits + 1 passive = 5 vs health 2 -> 3 overkill wasted

        $this->respond("actionAttack");

        $this->assertEquals("supply_monster", $this->tokenLocation($primary));
        $this->assertEquals(0, $this->countDamage($second), "out of reach - the sweep ring is Boldur's own");
        $this->assertNotNull($this->findLog("not used"), "the void sweep must say why it did nothing");
    }

    /** Kills outside an attack action never involved the card - no explanation to give. */
    public function testNoExplanationForKillsOutsideAnAttack(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_6_9", "");

        $this->seedRand([5, 5, 5, 5]);
        $this->respond("actionAttack");
        $this->skipIfOp("useCard");
        $notifsBefore = count($this->game->notify->_getNotifications());

        // A free-action kill: marker_attack is back in limbo, so the card never applied.
        $op = $this->game->machine->instantiateOperation("applyDamage", $this->color, [
            "target" => "monster_goblin_2",
            "amount" => 5,
        ]);
        $this->game->getMonster("monster_goblin_2")->moveTo("hex_7_8", "");
        $op->action_resolve(["target" => "monster_goblin_2"]);

        $logs = array_slice($this->game->notify->_getNotifications(), $notifsBefore);
        foreach ($logs as $notif) {
            $this->assertStringNotContainsString("not used", (string) ($notif["log"] ?? ""));
        }
    }

    public function testSweepingStrikeIDoesNotSweepWithoutOverkill(): void {
        // Goblin health=2, armor=0. 1 hit + 1 sweep die = 2 damage → exact kill, 0 overkill.
        // c_sweep should bail on ERR_NOT_APPLICABLE; the second goblin takes nothing.
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $second = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_8", "");

        $this->seedRand([5, 1, 1, 1]); // 1 hit + sweep(1) = 2 damage → kill with 0 overkill

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        // No overkill -> c_sweep is void, so onMonsterKilled does not prompt useCard
        // (BGA #233927). skipIfOp is a defensive no-op should any prompt appear.
        $this->skipIfOp("useCard");

        $this->assertEquals("supply_monster", $this->tokenLocation($primary));
        $this->assertEquals(0, $this->countDamage($second), "No overkill → no sweep damage");
        $this->assertEquals("hex_5_8", $this->tokenLocation($second), "Second goblin should still be alive");
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

        // 2 base hits + 3 sweep = 5 damage. Troll health=7 → survives.
        $this->assertEquals(5, $this->countDamage($troll), "Sweeping Strike II should add +3 (countAdjMonsters)");
        $this->assertEquals("hex_4_9", $this->tokenLocation($troll), "Troll should still be alive");
    }

    // -------------------------------------------------------------------------
    // BGA #235866 "sweeping Strike Not allowing DMG to second target".
    //
    // The reporter attached the TActionAttack prompt with the sweep bullet greyed out -
    // expected, nothing has died yet - and concluded the sweep was unreachable. It is not:
    // the sweep is offered again on TMonsterKilled. These pin the reported combos.
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
        $this->respond("card_event_4_32"); // Berserk: 1 unpreventable health for +3 damage

        // 2 hits + 1 (Sweeping Strike) + 3 (Berserk) = 6 on a 3-health brute -> 3 overkill.
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_ability_4_5", "sweep must be re-offered on TMonsterKilled");
        $this->respond("card_ability_4_5");
        $this->confirmCardEffect(); // sweep onto the troll

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"), "brute killed");
        $this->assertEquals(3, $this->countDamage("monster_troll_1"), "3 overkill swept clockwise onto the troll");
    }

    /**
     * Exact reported board: both monsters are 3-health brutes adjacent to Boldur. Target brute
     * is W of him, second brute is NW = the very next hex clockwise from the target.
     *
     * The passive damage does NOT lock the card out of the later sweep. Sweeping Strike has no
     * `spendUse` in its `r` expression, so the once-per-turn ability lock (RULES.md "Abilities
     * (free actions vs. ongoing effects)") never engages - correct, since both bullets are
     * `on(...)` triggers.
     */
    public function testPassiveDamageDoesNotBlockSweep(): void {
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

        $this->assertEquals(0, $this->game->tokens->getTokenState($sweep, 0), "card must not be marked used");
        $this->assertOperation("useCard");
        $this->assertValidTarget($sweep, "sweep must still be offered on TMonsterKilled");
        $this->respond($sweep);
        $this->confirmCardEffect(); // sweep onto the NW brute

        $this->assertEquals("supply_monster", $this->tokenLocation($target), "target brute killed");
        $this->assertEquals(2, $this->countDamage($second), "2 overkill swept clockwise onto the NW brute");
    }

    public function testPassiveDamageAppliesWhenTheAttackPromptIsSkipped(): void {
        // The reporter dismissed the pre-attack prompt because the sweep bullet was greyed.
        // The damage bullet is passive now, so it lands whether or not anything is answered -
        // the only card still offered at attack time here is Berserk.
        $this->setupRapidStrikeBoard();

        $this->respond("card_ability_4_4");
        $this->respond("hex_6_10");
        $this->skip(); // decline Berserk

        // 2 hits + 1 passive = 3 on a 3-health brute: exact kill, no overkill to sweep with.
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"), "brute killed by the passive +1");
        $this->assertEquals(0, $this->countDamage("monster_troll_1"), "no overkill, so no sweep");
    }
}
