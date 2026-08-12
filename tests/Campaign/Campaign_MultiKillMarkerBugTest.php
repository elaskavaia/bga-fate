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
     * BGA #236913 - DOCUMENTS BUGGY BEHAVIOR. Flip the assertions marked below when
     * the fix lands.
     *
     * Bjorn kills two brutes in one attack action via Nailed Together I while Helmet
     * (quest_r = killed('brute or skeleton'):?(blockXp:gainEquip)) sits on top of the
     * equip deck.
     *
     * Op_trigger queues one Helmet quest chain per kill, but the chain is only probed
     * (Card::canResolveQuest) at queue time - it resolves much later, and by then
     * Op_finishKill has swept the second brute off the hex that marker_attack now
     * points at. The stale first chain's leading killed() gate then finds nothing.
     *
     * Because a quest chain is queued without l_skip (unlike a card effect, which gets
     * it from Card::createOperationForCardEffect), the void gate is a mandatory,
     * unsatisfiable prompt: no targets, no skip button. The client renders it as
     * "[Error: No killed monster on attack hex] killed?" - verbatim what the reporter saw.
     */
    public function testStaleKillQuestChainDeadEndsAfterNailedTogetherDoubleKill(): void {
        $this->boot(1); // solo Bjorn
        $nailed = "card_ability_1_13";
        $this->game->tokens->moveToken($nailed, "tableau_" . $this->color);
        $this->seedDeck("deck_equip_" . $this->color, ["card_equip_1_21"]); // Helmet

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $front = "monster_brute_1";
        $behind = "monster_brute_2";
        $this->game->getMonster($front)->moveTo("hex_6_9", "");
        $this->game->getMonster($behind)->moveTo("hex_5_9", "");
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $front, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $behind, ["message" => ""]);

        $this->seedRand([5, 5]); // 2 hits on a health-3 brute already at 2 -> kill, 1 overkill

        $this->respond("actionAttack");
        $this->respond("hex_6_9");
        $this->skipIfOp("useCard"); // Bjorn Hero I offers its TRoll ability first
        $this->respond($nailed);
        $this->respond("hex_5_9"); // pierce the brute behind, exact kill
        $this->respond("1"); // Helmet claimed for the second kill

        $args = $this->getOpArgs();

        // BUGGY BEHAVIOR (BGA #236913): flip these three when the fix lands - the stale
        // chain should void silently and the machine should be back on "turn".
        $this->assertEquals("killed", $args["type"] ?? "", "stale quest gate becomes the active prompt");
        $this->assertEquals("No killed monster on attack hex", $args["err"] ?? "", "the string the reporter saw");
        $this->assertEmpty($args["info"] ?? [], "no targets and no skip button - the player is stuck");

        // Supporting facts, true either way.
        $this->assertEquals("supply_monster", $this->tokenLocation($behind), "second brute already cleared");
        $this->assertEquals("hex_5_9", $this->tokenLocation("marker_attack"), "marker left on the cleared hex");
        $this->assertNull($this->game->hexMap->getCharacterOnHex("hex_5_9"), "nothing left for killed() to find");
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
