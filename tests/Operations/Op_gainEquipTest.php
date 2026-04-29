<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;

/**
 * Tests for Op_gainEquip — placing equipment on tableau and Main Weapon replacement.
 */
final class Op_gainEquipTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "gainEquip";
    }

    private function pushGain(string $cardId): void {
        $this->game->machine->push("gainEquip", $this->owner, ["target" => $cardId]);
        $this->game->machine->dispatchAll();
    }

    public function testGainNonMainWeaponDoesNotMoveExisting(): void {
        // Bjorn starts with First Bow (Main Weapon) on tableau via setup.
        $tableau = $this->getPlayersTableau();
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_1_15"));

        // Gain Leather Purse (non-MW).
        $this->pushGain("card_equip_1_19");

        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_1_15"), "First Bow stays on tableau");
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_1_19"), "Leather Purse landed on tableau");
    }

    public function testGainMainWeaponReplacesExisting(): void {
        $tableau = $this->getPlayersTableau();
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_1_15"));

        // Gain Trollbane (Main Weapon).
        $this->pushGain("card_equip_1_22");

        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("card_equip_1_15"), "First Bow displaced to limbo");
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_1_22"), "Trollbane on tableau");
    }

    public function testReplacingMainWeaponSweepsAttachedTokens(): void {
        // Boldur's Smiterbiter stores damage on its own card (state-tracked).
        $this->game = new GameUT();
        $this->game->initWithHero(4);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $tableau = "tableau_" . $this->owner;
        // Place Smiterbiter on tableau (replaces First Pick which was placed at setup).
        $this->game->machine->push("gainEquip", $this->owner, ["target" => "card_equip_4_21"]);
        $this->game->machine->dispatchAll();
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_4_21"));

        // Park a crystal on Smiterbiter to simulate stored damage.
        $this->game->effect_moveCrystals("hero_4", "red", 2, "card_equip_4_21");
        $this->assertEquals(2, $this->countRedCrystals("card_equip_4_21"));

        // Replace Smiterbiter with Eitri's Pick (also a Main Weapon).
        $this->game->machine->push("gainEquip", $this->owner, ["target" => "card_equip_4_22"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("card_equip_4_21"), "Smiterbiter to limbo");
        $this->assertEquals(0, $this->countRedCrystals("card_equip_4_21"), "stored damage swept off Smiterbiter");
    }

    public function testQuestProgressCrystalsSweptOnGain(): void {
        // Belt of Youth (card_equip_2_22) on Alva (hero 2). Park 8 quest-progress
        // crystals on it (as if it sat on deck-top accumulating progress), then gain it.
        // The crystals must return to supply, not linger on the card on tableau.
        $this->game = new GameUT();
        $this->game->initWithHero(2);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $belt = "card_equip_2_22";
        // Park 8 progress crystals on Belt of Youth while it sits in deck.
        $this->game->effect_moveCrystals("hero_2", "red", 8, $belt);
        $this->assertEquals(8, $this->countRedCrystals($belt));
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");

        $this->pushGain($belt);

        $tableau = "tableau_" . $this->owner;
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation($belt), "Belt of Youth landed on tableau");
        $this->assertEquals(0, $this->countRedCrystals($belt), "progress crystals swept off");
        $this->assertEquals($supplyBefore + 8, $this->countRedCrystals("supply_crystal_red"), "8 crystals returned to supply");
    }

    public function testGainMainWeaponWithNoExistingNoError(): void {
        // Switch to Embla (hero 3) — starts with Flimsy Blade (3_15), which is NOT a Main Weapon.
        $this->game = new GameUT();
        $this->game->initWithHero(3);
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());

        $tableau = "tableau_" . $this->owner;
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_3_15"), "Flimsy Blade on tableau");
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("card_equip", "limbo"), "no equip in limbo at start");

        // Gain Raven's Claw — Embla's first Main Weapon.
        $this->game->machine->push("gainEquip", $this->owner, ["target" => "card_equip_3_22"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_3_15"), "Flimsy Blade untouched (not a MW)");
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_3_22"), "Raven's Claw on tableau");
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("card_equip", "limbo"), "no equip moved to limbo");
    }
}
