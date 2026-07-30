<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Regression repro for BGA #234247 / #234242 - multi-kill XP/gold under-award.
 *
 * When one Boldur attack action kills more than one monster (Sweeping Strike
 * cleave, Nailed Together pierce), the player should gain XP/gold for EVERY
 * monster killed. Reports say only ONE monster's reward is granted.
 *
 * Root cause: Game::countMonsterXp() ignores the killed-monster $context it is
 * passed by Monster::finalizeDamage() whenever getAttackHex() is non-null; it
 * instead reads the monster currently on the shared marker_attack hex. In a
 * multi-kill, marker_attack has already advanced to the LAST monster hit, so the
 * earlier kill's finishKill reads the wrong (already-removed) monster and awards 0.
 *
 * These tests are GREEN and pin the CURRENT buggy behavior on purpose.
 * Flip the assertions (1 -> 2) once BGA #234247/#234242 is fixed.
 */
class Campaign_MultiKillXpBugTest extends CampaignBaseTest {
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

    /**
     * Sweeping Strike I kills the primary goblin AND cleaves the second goblin
     * dead in one attack action. Each goblin is worth 1 XP, so a correct
     * implementation awards 2 XP. The bug awards only 1.
     *
     * documents buggy behavior for BGA #234247/#234242; flip once fixed
     */
    public function testSweepMultiKillAwardsXpForOnlyOneMonster(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $cleave = "monster_goblin_2";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($cleave)->moveTo("hex_5_8", "");

        $goblinXp = $this->game->getMonster($primary)->getXpReward();
        $this->assertEquals(1, $goblinXp, "sanity: goblin is worth 1 XP");
        $xpBefore = $this->countXp();

        // 3 hits + 1 Sweeping Strike passive = 4 damage on primary (health 2),
        // overkill 2 kills the cleave goblin (health 2).
        $this->seedRand([5, 5, 5, 1]);

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId);
        $this->respond("choice_0"); // add damage branch
        $this->respond($cardId); // use card for sweep
        $this->respond("choice_1"); // confirm sweep -> kills cleave goblin

        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "primary goblin dead");
        $this->assertEquals("supply_monster", $this->tokenLocation($cleave), "cleave goblin dead");

        // BUG: two goblins killed (2 XP expected) but only 1 XP is awarded.
        // Flip to assertEquals(2, ...) once BGA #234247/#234242 is fixed.
        $xpGained = $this->countXp() - $xpBefore;
        $this->assertEquals(1, $xpGained, "buggy: only 1 of 2 kills awards XP (BGA #234247)");
    }
}
