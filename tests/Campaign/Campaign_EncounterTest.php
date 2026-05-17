<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: Map encounters — hero stepping onto a bonus hex prompts
 * for pickup count, then queues the matching gain op (gainXp/gainMana/heal).
 * Setup places 3 yellow on hex_5_17 (Wyrm Lair), 3 green on hex_17_5 (Nailfare),
 * 3 red on hex_5_7 (Troll Caves).
 */
class Campaign_EncounterTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();

        $color = $this->getActivePlayerColor();
        $this->seedDeck("deck_event_$color", ["card_event_1_27_1", "card_event_1_27_2"]);
        foreach ($this->game->tokens->getTokensOfTypeInLocation("card_event", "hand_$color") as $card) {
            $this->game->tokens->moveToken($card["key"], "limbo");
        }
    }

    public function testYellowEncounterQueuesGainXp(): void {
        // Park hero adjacent to Wyrm Lair so a single Move step lands on the bonus hex.
        // hex_5_17 (Wyrm Lair, plains) is the bonus hex; hex_5_16 is a passable adjacent WyrmLair hex.
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_5_16");

        // Confirm the bonus crystals are present (placed by setup)
        $this->assertEquals(3, $this->countTokens("crystal_yellow", "hex_5_17"));

        // First action: Move — pick the Wyrm Lair hex
        $this->respond("hex_5_17");

        // Hero arrives, encounter prompt fires
        $args = $this->getOpArgs();
        $this->assertEquals("encounter", $args["type"] ?? "", "encounter prompt should be active");

        // Pick all 3 — encounter queues gainXp which auto-resolves
        $this->respond(3);

        // Hex should be empty; XP should land on the player's tableau
        $this->assertEquals(0, $this->countTokens("crystal_yellow", "hex_5_17"));
        $this->assertGreaterThanOrEqual(5, $this->countXp(), "should have setup 2 + encounter 3 = 5 XP");
    }

    public function testYellowEncounterSkip(): void {
        $this->game->tokens->dbSetTokenLocation($this->heroId, "hex_5_16");
        $this->assertEquals(3, $this->countTokens("crystal_yellow", "hex_5_17"));

        $this->respond("hex_5_17");

        $args = $this->getOpArgs();
        $this->assertEquals("encounter", $args["type"] ?? "");

        // Skip — crystals stay on the hex for a teammate
        $this->skip();

        $this->assertEquals(3, $this->countTokens("crystal_yellow", "hex_5_17"), "skipped crystals stay on hex");
        // No gainXp queued
        $args = $this->getOpArgs();
        $this->assertNotEquals("gainXp", $args["type"] ?? "");
    }
}
