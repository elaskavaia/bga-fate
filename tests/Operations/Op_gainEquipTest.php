<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;

/**
 * Tests for Op_gainEquip — placing equipment on tableau and Main Weapon replacement.
 */
final class Op_gainEquipTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "gainEquip";
    }

    private function pushGain(string $cardId): void {
        $this->createOp(null, ["target" => $cardId]);
        $this->call_resolve($cardId);
        $this->dispatchAll();
    }

    /** BGA #251077: the gain reveals the next quest, so it waits for an explicit click and anchors an undo barrier. */
    public function testGainWaitsForConfirmAndIsAnUndoBarrier(): void {
        $purse = "card_equip_1_19";
        $location = $this->game->tokens->getTokenLocation($purse);
        $this->game->machine->push("gainEquip", $this->owner, ["target" => $purse]);
        $this->dispatchAll();
        $this->assertEquals($location, $this->game->tokens->getTokenLocation($purse), "not gained before the click");

        $op = $this->game->machine->createTopOperationFromDbForOwner($this->owner);
        $this->assertEquals("gainEquip", $op->getType());
        $this->assertEquals("Complete quest", $op->getArgsInfo()[$purse]["name"]);
        $this->clearPendingUndoSavepoint();
        $op->action_resolve([Operation::ARG_TARGET => $purse]);

        $this->assertEquals($this->getPlayersTableau(), $this->game->tokens->getTokenLocation($purse));
        $this->assertEquals("quest", $this->pendingUndoSavepoint()["label"] ?? "", "the gain is an undo barrier");
    }

    public function testGainWithoutTargetTakesTheDeckTopAndRevealsTheNext(): void {
        $deck = "deck_equip_" . $this->owner;
        $this->game->tokens->moveToken("card_equip_1_17", $deck, 1);
        $this->game->tokens->moveToken("card_equip_1_19", $deck, 2);
        $this->createOp();
        $this->assertEquals(["card_equip_1_19"], $this->op->getArgsTarget());

        $this->call_resolve("card_equip_1_19");

        $this->assertEquals($this->getPlayersTableau(), $this->game->tokens->getTokenLocation("card_equip_1_19"));
        $this->assertEquals("card_equip_1_17", $this->game->tokens->getTokenOnTop($deck)["key"]);
    }

    public function testEmptyDeckResolvesWithoutPrompt(): void {
        $this->createOp();
        $this->assertTrue($this->op->canResolveAutomatically());
        $this->call_resolve();
        $this->assertEmpty($this->game->tokens->getTokensOfTypeInLocation("card_equip", "deck_equip_" . $this->owner));
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
        $this->pushGain("card_equip_4_21");
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_4_21"));

        // Park a crystal on Smiterbiter to simulate stored damage.
        $this->game->effect_moveCrystals("hero_4", "red", 2, "card_equip_4_21");
        $this->assertEquals(2, $this->countRedCrystals("card_equip_4_21"));

        // Replace Smiterbiter with Eitri's Pick (also a Main Weapon).
        $this->pushGain("card_equip_4_22");

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
        $this->pushGain("card_equip_3_22");

        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_3_15"), "Flimsy Blade untouched (not a MW)");
        $this->assertEquals($tableau, $this->game->tokens->getTokenLocation("card_equip_3_22"), "Raven's Claw on tableau");
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("card_equip", "limbo"), "no equip moved to limbo");
    }
}
