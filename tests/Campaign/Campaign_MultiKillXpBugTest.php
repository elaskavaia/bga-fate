<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Regression test for BGA #234247 / #234242 - multi-kill XP/gold under-award.
 *
 * When one Boldur attack action kills more than one monster (Sweeping Strike
 * sweep, Nailed Together pierce), the player gains XP/gold for EVERY monster
 * killed. Game::countMonsterXp() used to ignore the killed-monster $context it
 * is passed by Monster::finalizeDamage() and read the monster on the shared
 * marker_attack hex instead; in a multi-kill that hex has already advanced to
 * the next target, so the earlier kill scored 0.
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
     * Sweeping Strike I kills the primary goblin AND sweeps a second monster dead in one
     * attack action. The victims are worth different amounts on purpose, so the total also
     * pins WHICH monster each kill scored - the old bug was misattribution, not a lost kill.
     */
    public function testSweepMultiKillAwardsXpForEveryMonster(): void {
        $cardId = "card_ability_4_5";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");

        $primary = "monster_goblin_1";
        $second = "monster_brute_1";
        $this->game->getMonster($primary)->moveTo("hex_4_9", "");
        $this->game->getMonster($second)->moveTo("hex_5_8", "");
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $second, ["message" => ""]);

        $this->assertEquals(1, $this->game->getMonster($primary)->getXpReward(), "sanity: goblin is worth 1 XP");
        $this->assertEquals(2, $this->game->getMonster($second)->getXpReward(), "sanity: brute is worth 2 XP");
        $xpBefore = $this->countXp();

        // 3 hits + 1 Sweeping Strike passive = 4 damage on primary (health 2),
        // overkill 2 kills the brute behind it (health 3, already down 1).
        $this->seedRand([5, 5, 5, 1]);

        $this->respond("actionAttack");
        $this->respond("hex_4_9");
        $this->respond($cardId); // use card for sweep
        $this->confirmCardEffect(); // sweep -> kills the brute

        $this->assertEquals("supply_monster", $this->tokenLocation($primary), "primary goblin dead");
        $this->assertEquals("supply_monster", $this->tokenLocation($second), "second monster dead");

        $xpGained = $this->countXp() - $xpBefore;
        $this->assertEquals(3, $xpGained, "each kill scores its own monster: goblin 1 + brute 2 (BGA #234247)");
    }
}
