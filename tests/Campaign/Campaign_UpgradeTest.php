<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: Upgrade operation during end of turn.
 */
class Campaign_UpgradeTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->color = $this->playerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);

        // Seed monster deck
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",
            "card_monster_8",
            "card_monster_9",
            "card_monster_10",
        ]);
        // Seed event deck with non-custom cards
        $this->seedDeck("deck_event_" . $this->color, [
            "card_event_1_27_1",
            "card_event_1_27_2",
        ]);
        // Clear hand to avoid flaky triggers
        $this->clearHand($this->color);
        $this->clearMonstersFromMap();
    }

    public function testUpgradeHeroCardAtEndOfTurn(): void {
        // Give player enough XP to afford upgrade (cost starts at 5, setup gives 2)
        $this->game->effect_moveCrystals($this->heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);
        $xpBefore = $this->countXp();
        $this->assertGreaterThanOrEqual(5, $xpBefore);

        // Seed ability deck with a known card so gain-ability target is deterministic
        $this->seedDeck("deck_ability_" . $this->color, ["card_ability_1_9"]);

        // Verify hero card Level I is on tableau
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_hero_1_1"));

        // === Do two focus actions to end the turn ===
        $this->respond("hex_7_9"); // move action
        $this->respond("actionFocus");

        // Skip free actions → triggers end of turn
        $this->skip();

        // Skip turnEnd trigger if present
        $this->skipIfOp("trigger");

        // Upgrade operation should appear
        $args = $this->getOpArgs();
        $this->assertEquals("upgrade", $args["type"] ?? "", "Upgrade op should be offered at end of turn");

        // Hero card (Level I) should be a valid target
        $this->assertValidTarget("card_hero_1_1");
        // Top of ability deck should also be a valid target
        $this->assertValidTarget("card_ability_1_9");

        // Choose to upgrade hero card
        $this->respond("card_hero_1_1");

        // Hero card should now be Level II
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_hero_1_2"));
        $this->assertNotEquals("tableau_" . $this->color, $this->tokenLocation("card_hero_1_1"));

        // XP should have been spent (cost=5)
        $xpAfter = $this->countXp();
        $this->assertEquals($xpBefore - 5, $xpAfter);

        // Upgrade cost marker should advance to 6
        $newCost = (int) $this->game->tokens->getTokenState("marker_" . $this->color . "_3");
        $this->assertEquals(6, $newCost);
    }

    public function testUpgradeSkippedWhenInsufficientXp(): void {
        // Don't add extra XP — player has only 2 from setup, cost is 5
        $this->assertEquals(2, $this->countXp());

        // Two actions + skip free actions
        $this->respond("hex_7_9"); // move action
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("trigger");

        // Upgrade should auto-skip (insufficient XP), next op should be drawEvent or turn state
        $args = $this->getOpArgs();
        $this->assertNotEquals("upgrade", $args["type"] ?? "", "Upgrade should auto-skip when XP insufficient");
    }

    public function testUpgradeGainNewAbility(): void {
        // Give player enough XP
        $this->game->effect_moveCrystals($this->heroId, "yellow", 5, "tableau_" . $this->color, ["message" => ""]);

        // Seed ability deck with Eagle Eye I on top, Nailed Together I beneath it.
        // Order: first element = top of deck.
        $this->seedDeck("deck_ability_" . $this->color, ["card_ability_1_9", "card_ability_1_13"]);

        // Two actions + skip
        $this->respond("hex_7_9"); // move action
        $this->respond("actionFocus");
        $this->skip();
        $this->skipIfOp("trigger");

        // Upgrade op
        $args = $this->getOpArgs();
        $this->assertEquals("upgrade", $args["type"] ?? "");

        // Snapshot notification count before gain so we only inspect new notifs.
        $notifCountBefore = count($this->game->notify->_getNotifications());

        // Choose to gain new ability from deck (top card)
        $this->respond("card_ability_1_9");

        // Card should now be on tableau
        $this->assertEquals("tableau_" . $this->color, $this->tokenLocation("card_ability_1_9"));
        // The card beneath it is now the new top of the deck
        $this->assertEquals("deck_ability_" . $this->color, $this->tokenLocation("card_ability_1_13"));

        // A tokenMoved notification should have been emitted for the new top card,
        // so the client can reveal it in the deck slot.
        $notifsAfter = array_slice($this->game->notify->_getNotifications(), $notifCountBefore);
        $revealed = false;
        foreach ($notifsAfter as $notif) {
            if (($notif["type"] ?? "") !== "tokenMoved") {
                continue;
            }
            $tokenId = $notif["args"]["token_id"] ?? null;
            $placeId = $notif["args"]["place_id"] ?? null;
            if ($tokenId === "card_ability_1_13" && $placeId === "deck_ability_" . $this->color) {
                $revealed = true;
                break;
            }
        }
        $this->assertTrue($revealed, "Expected tokenMoved notification revealing new top of ability deck");
    }
}
