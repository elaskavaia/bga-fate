<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #235445: "Alva's racial not asking for target".
 *
 * Drives the REAL trigger path the reporter used: Move action ends in a forest,
 * CardHero_AlvaHeroI queues gainMana, and Alva has TWO mana cards on her tableau
 * (starting Hail of Arrows I plus Alva's Bracers, as in the report). The unit test
 * Op_gainManaBug235445Test proves the op in isolation prompts with two targets;
 * this test proves (or disproves) the same through the full turn dispatch chain,
 * which the reporter's log suggests never prompted.
 */
class Campaign_GainManaPromptBug235445Test extends CampaignBaseTest {
    private const BRACERS = "card_equip_2_23"; // mana gen 1
    private const HAIL_I = "card_ability_2_3"; // mana gen 1, on Alva's starting tableau

    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), ["card_event_2_31_1", "card_event_2_31_2"]);

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
        $this->clearEquipDecks();

        $this->game->tokens->moveToken(self::BRACERS, "tableau_" . $this->getActivePlayerColor());
    }

    public function testForestTriggerWithTwoManaCardsPromptsForTarget(): void {
        $hailBefore = $this->countTokens("crystal_green", self::HAIL_I);
        $bracersBefore = $this->countTokens("crystal_green", self::BRACERS);

        $this->game->tokens->moveToken($this->heroId, "hex_5_9"); // plains, forest neighbor hex_5_8
        $this->respond("hex_5_8");

        $this->assertEquals("hex_5_8", $this->tokenLocation($this->heroId));
        $this->assertOperation("gainMana");
        $this->assertValidTarget(self::HAIL_I);
        $this->assertValidTarget(self::BRACERS);
        $this->assertEquals($hailBefore, $this->countTokens("crystal_green", self::HAIL_I), "no mana auto-added before the choice");
        $this->assertEquals($bracersBefore, $this->countTokens("crystal_green", self::BRACERS));

        $this->respond(self::BRACERS);

        $this->assertEquals("PlayerTurn", $this->getStateArgs()["name"]);
        $this->assertEquals($bracersBefore + 1, $this->countTokens("crystal_green", self::BRACERS), "chosen card got the mana");
        $this->assertEquals($hailBefore, $this->countTokens("crystal_green", self::HAIL_I));
    }

    /**
     * The reporter's table had 4 players; solo passes (test above). The #235314 undo bug
     * also only reproduced in multiplayer, so drive the same scenario with a second seat.
     */
    public function testForestTriggerWithTwoManaCardsPromptsInMultiplayer(): void {
        $this->setupGame([2, 1]); // Alva first, Bjorn second
        $this->syncCurrentPlayerToActive();
        $color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($color);
        $this->clearMonstersFromMap();
        $this->clearHand($color);
        $this->clearEquipDecks();
        $this->game->tokens->moveToken(self::BRACERS, "tableau_$color");

        $hailBefore = $this->countTokens("crystal_green", self::HAIL_I);
        $bracersBefore = $this->countTokens("crystal_green", self::BRACERS);

        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $this->respond("hex_5_8");

        $this->assertEquals("hex_5_8", $this->tokenLocation($this->heroId));
        $this->assertOperation("gainMana");
        $this->assertValidTarget(self::HAIL_I);
        $this->assertValidTarget(self::BRACERS);
        $this->assertEquals($hailBefore, $this->countTokens("crystal_green", self::HAIL_I), "no mana auto-added before the choice");

        $this->respond(self::BRACERS);
        $this->assertEquals($bracersBefore + 1, $this->countTokens("crystal_green", self::BRACERS), "chosen card got the mana");
        $this->assertEquals($hailBefore, $this->countTokens("crystal_green", self::HAIL_I));
    }

    /** Harness has a sticky currentPlayerId; sync it to active so the action targets the right player. */
    private function syncCurrentPlayerToActive(): void {
        $this->game->_setCurrentPlayerId((int) $this->game->getActivePlayerId());
    }
}
