<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #251284 / #251620: imperative equipment usable more than once per turn.
 * RULES.md "Free Actions": "Your equipment cards may only be used once per turn
 * (except cards that prevent damage...)". FORUM.md: Quiver "may only be used once per turn".
 */
class Campaign_Bug251284Test extends CampaignBaseTest {
    private string $heroId;

    private function setupHero(int $hero): string {
        $this->setupGame([$hero]);
        $color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($color);
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->clearMonstersFromMap();
        $this->clearHand($color);
        return $color;
    }

    public function testMiningEquipmentUsedTwiceInOneTurn(): void {
        $mining = "card_equip_4_17";
        $color = $this->setupHero(4);
        $this->game->tokens->moveToken($mining, "tableau_$color");
        $this->game->tokens->moveToken($this->heroId, "hex_5_8"); // adjacent to 1 mountain
        $this->game->hexMap->invalidateOccupancy();
        $before = $this->countXp();

        $this->respond($mining);
        $this->confirmCardEffect();
        $this->assertEquals($before + 2, $this->countXp());

        // Pins buggy behavior for BGA #251284 - flip the assertion when the fix lands
        $this->assertValidTarget($mining, "Mining Equipment is offered again in the same turn");
        $this->respond($mining);
        $this->confirmCardEffect();
        $this->assertEquals($before + 4, $this->countXp(), "second use in the same turn paid out again");
        $this->assertEquals(2, $this->countDamage($mining), "both durability spent in one turn");
    }

    private function attackTrollTwiceWithBracers(array $extraCards): string {
        $bracers = "card_equip_2_23";
        $color = $this->setupHero(2);
        foreach ($extraCards as $card) {
            $this->game->tokens->moveToken($card, "tableau_$color");
        }
        $this->game->tokens->moveToken($bracers, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, $bracers, ["message" => ""]);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_troll_1")->moveTo("hex_5_9", "");
        return $color;
    }

    public function testQuiverUsedInTwoAttackActionsInOneTurn(): void {
        $quiver = "card_equip_2_18";
        $troll = "monster_troll_1";
        $this->attackTrollTwiceWithBracers([$quiver]);

        // Strength = Alva I(2) + First Bow(1) + Quiver(1) = 4 dice, all misses
        $this->seedRand([1, 1, 1, 1, 1, 1, 1, 1]);
        $this->respond("hex_5_9");
        $this->assertOperation("useCard");
        $this->respond($quiver);
        $this->skipIfOp("useCard");
        $this->assertEquals(1, $this->countDamage($quiver));
        $this->assertEquals(1, $this->countDamage($troll));

        // Bracers' extra attack auto-targets the only monster in range
        $this->respond("card_equip_2_23");

        // Pins buggy behavior for BGA #251620 - flip the assertion when the fix lands
        $this->assertOperation("useCard");
        $this->assertValidTarget($quiver, "Quiver is offered again in the second attack action of the turn");
        $this->respond($quiver);
        $this->skipIfOp("useCard");
        $this->assertEquals(2, $this->countDamage($quiver), "Quiver durability spent twice in one turn");
        $this->assertEquals(2, $this->countDamage($troll), "Quiver added damage in both attacks");
    }

    public function testBloodlineCrystalAddDamageUsedInTwoAttackActionsInOneTurn(): void {
        $crystal = "card_equip_2_25";
        $troll = "monster_troll_1";
        $this->attackTrollTwiceWithBracers([$crystal]);
        $this->game->effect_moveCrystals($this->heroId, "green", 6, $crystal, ["message" => ""]);

        // Strength = Alva I(2) + First Bow(1) = 3 dice, all misses
        $this->seedRand([1, 1, 1, 1, 1, 1]);
        $this->respond("hex_5_9");
        $this->assertOperation("useCard");
        $this->respond($crystal);
        $this->respond("choice_0");
        $this->skipIfOp("useCard");
        $this->assertEquals(2, $this->countDamage($troll));

        $this->respond("card_equip_2_23");

        // Pins buggy behavior for BGA #251284 - flip the assertion when the fix lands
        $this->assertOperation("useCard");
        $this->assertValidTarget($crystal, "Bloodline Crystal is offered again in the second attack action of the turn");
        $this->respond($crystal);
        $this->respond("choice_0");
        $this->skipIfOp("useCard");
        $this->assertEquals(0, $this->countTokens("crystal_green", $crystal), "6 mana spent on two add-damage uses");
        $this->assertEquals(4, $this->countDamage($troll), "Bloodline Crystal added 2 damage in both attacks");
    }
}
