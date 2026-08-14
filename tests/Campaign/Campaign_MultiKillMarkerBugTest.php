<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * The two attack-path reports filed within a day of tag v260806-2001:
 * BGA #236791 (Sweeping Strike scores the same kills repeatedly) and
 * BGA #236913 ("[Error: No killed monster on attack hex] killed?").
 *
 * Both were suspected to share one root cause - the kill trigger runs before
 * Op_finishKill clears the corpse, while marker_attack has already advanced to
 * the next victim. Only #236913 reproduces; see each test.
 */
class Campaign_MultiKillMarkerBugTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    private function boot(int $hero): void {
        $this->setupGame([$hero]);
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
        $this->clearEquipDecks();
    }

    /**
     * BGA #236791 attempt - NOT reproduced, kept as a regression guard.
     *
     * The sweep kill leaves 1 damage over and both corpses are still on their hexes
     * (Op_finishKill for either is still queued), so if the spendsExcess guard in
     * CardAbility_SweepingStrikeI::onMonsterKilled did not fire the card would offer
     * itself again and land back on the first corpse. It does fire: each goblin is
     * killed once and scores 1 XP once.
     */
    public function testSweepMultiKillScoresEachMonsterOnce(): void {
        $this->boot(4); // solo Boldur
        $sweep = "card_ability_4_5";
        $this->game->tokens->moveToken($sweep, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");

        $first = "monster_goblin_1"; // W of Boldur, the attack target
        $second = "monster_goblin_2"; // NW = next hex clockwise
        $this->game->getMonster($first)->moveTo("hex_6_9", "");
        $this->game->getMonster($second)->moveTo("hex_7_8", "");
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $second, ["message" => ""]);

        $xpBefore = $this->countXp();
        $this->seedRand([5, 5, 5]); // 3 hits + 1 passive = 4 vs health 2 -> 2 overkill

        $this->respond("actionAttack");
        $this->respond("hex_6_9");
        $this->respond($sweep);
        $this->confirmCardEffect(); // clockwise order picks the NW goblin

        $this->assertEquals("supply_monster", $this->tokenLocation($first));
        $this->assertEquals("supply_monster", $this->tokenLocation($second));
        $this->assertEquals(2, $this->countXp() - $xpBefore, "1 XP per goblin, scored once each");
        $this->assertEquals("turn", $this->getOpArgs()["type"] ?? "", "attack settles, no re-offered sweep");
    }

    /**
     * BGA #236913 - VERIFIES THE FIX. Bjorn kills two brutes in one attack action via
     * Nailed Together I while Helmet (quest_r = killed('brute or skeleton'):
     * ?(blockXp:gainEquip)) sits on top of the equip deck.
     *
     * Before the fix, the first kill's quest chain was queued BEHIND the pierce effect,
     * so the pierce's whole subtree - including finishKill for the second brute - ran
     * first, and the stale chain's killed() gate then found an empty hex and dead-ended
     * the turn on "[Error: No killed monster on attack hex] killed?" with no skip button.
     * Op_trigger now dispatches the quest before card effects, and the trigger carries
     * the dying monster's id for the gate to read.
     */
    private function killTwoBrutesWithNailedTogether(): void {
        $this->boot(1); // solo Bjorn
        $this->nailed = "card_ability_1_13";
        $this->game->tokens->moveToken($this->nailed, "tableau_" . $this->color);
        $this->seedDeck("deck_equip_" . $this->color, ["card_equip_1_21"]); // Helmet

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_6_9", "");
        $this->game->getMonster("monster_brute_2")->moveTo("hex_5_9", "");
        $this->game->effect_moveCrystals($this->heroId, "red", 2, "monster_brute_1", ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, "monster_brute_2", ["message" => ""]);

        $this->seedRand([5, 5]); // 2 hits on a health-3 brute already at 2 -> kill, 1 overkill

        $this->respond("actionAttack");
        $this->respond("hex_6_9");
        $this->skipIfOp("useCard"); // Bjorn Hero I offers its TRoll ability first
        // Helmet quest, offered for the FIRST kill - the brute is still on its hex, so the
        // killed() gate identifies it from the attack hex.
        $this->assertOperation("paygain");
    }

    private function pierceSecondBrute(): void {
        $this->assertOperation("useCard");
        $this->respond($this->nailed);
        $this->respond("hex_5_9"); // pierce the brute behind, exact kill
    }

    private string $nailed;

    public function testHelmetTakenOnFirstKillIsNotOfferedAgain(): void {
        $this->killTwoBrutesWithNailedTogether();
        $xpBefore = $this->countXp();
        $this->respond("1"); // forfeit XP, take the Helmet

        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_equip_1_21"), "Helmet claimed");
        $this->pierceSecondBrute();

        $this->assertEquals("turn", $this->getOpArgs()["type"] ?? "", "no stale quest, no dead end (BGA #236913)");
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"));
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_2"));
        $this->assertEquals(2, $this->countXp() - $xpBefore, "first kill's XP forfeited for the Helmet, second awarded");
    }

    public function testHelmetDeclinedOnFirstKillIsOfferedAgainForSecond(): void {
        $this->killTwoBrutesWithNailedTogether();
        $xpBefore = $this->countXp();
        $this->skip(); // decline for the first kill

        $this->pierceSecondBrute();

        $this->assertOperation("paygain"); // Helmet still on top: fresh offer for the second kill
        $this->respond("1");

        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_equip_1_21"), "Helmet claimed");
        $this->assertEquals("turn", $this->getOpArgs()["type"] ?? "");
        $this->assertEquals(2, $this->countXp() - $xpBefore, "first kill's XP awarded, second forfeited for the Helmet");
    }

    /**
     * Answers the standing question about the sweep cap: an arbitrary data field does
     * survive queue -> db row -> re-instantiate, so the spendsExcess flag that
     * Op_applyDamage carries into the kill trigger is readable by the card handlers.
     */
    public function testTriggerDataFieldSurvivesTheQueue(): void {
        $this->boot(4);
        $this->game->machine->queue("trigger(TMonsterKilled)", $this->color, ["spendsExcess" => true]);
        $op = $this->game->machine->findOperation($this->color, "trigger(TMonsterKilled)");
        $this->assertNotNull($op, "trigger op is in the machine");
        $this->assertTrue((bool) $op->getDataField("spendsExcess"), "spendsExcess survives the machine round trip");
        $op->destroy();
    }
}
