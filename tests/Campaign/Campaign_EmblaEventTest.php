<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Embla's event cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_EmblaEventTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([3]); // Solo Embla
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Sophisticated (card_event_3_29) ---
    // r=in(Grimheim):(actionMend/actionFocus/actionPrepare/actionPractice)
    // Play in Grimheim to perform a mend, focus, prepare, or practice action.

    public function testSophisticatedMendBranchHealsHero(): void {
        $color = $this->getActivePlayerColor();
        $sophisticated = "card_event_3_29_1";
        $this->seedHand($sophisticated, $color);

        // Hero starts in Grimheim; seed damage so mend has something to heal.
        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)));
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);

        $this->assertValidTarget($sophisticated);
        $this->respond($sophisticated);
        $this->confirmCardEffect();
        $this->respond("choice_0"); // actionMend — auto-resolves to the only damaged heal target

        $this->assertEquals(0, $this->countDamage($this->heroId));
    }

    public function testSophisticatedFocusBranchAddsManaToCard(): void {
        $color = $this->getActivePlayerColor();
        $sophisticated = "card_event_3_29_1";
        $this->seedHand($sophisticated, $color);

        // Count mana across the whole tableau — Op_gainMana auto-resolves when only one
        // card is mana-eligible, so we assert on the aggregate rather than a specific card id.
        // Embla starts with Riposte I (the only mana-holding card on her tableau) →
        // gainMana auto-resolves onto it.
        $manaCard = "card_ability_3_3"; // Riposte I
        $manaBefore = $this->countTokens("crystal_green", $manaCard);

        $this->assertValidTarget($sophisticated);
        $this->respond($sophisticated);
        $this->confirmCardEffect();
        $this->respond("choice_1"); // actionFocus → gainMana (auto-resolves, single target)

        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $manaCard));
    }

    public function testSophisticatedPrepareBranchDrawsEvent(): void {
        $color = $this->getActivePlayerColor();
        $sophisticated = "card_event_3_29_1";
        $this->seedHand($sophisticated, $color);

        // Seed a known card on top of the event deck so we can assert it was drawn.
        $drawnCard = "card_event_3_33_1"; // Speedy Attack
        $this->seedDeck("deck_event_$color", [$drawnCard]);

        $this->assertValidTarget($sophisticated);
        $this->respond($sophisticated);
        $this->confirmCardEffect();
        $this->respond("choice_2"); // actionPrepare → drawEvent
        $this->respond("confirm"); // drawEvent prompts for confirm before drawing

        $this->assertEquals("hand_$color", $this->tokenLocation($drawnCard));
    }

    public function testSophisticatedPracticeBranchGainsXp(): void {
        $color = $this->getActivePlayerColor();
        $sophisticated = "card_event_3_29_1";
        $this->seedHand($sophisticated, $color);

        $xpBefore = $this->countXp();

        $this->assertValidTarget($sophisticated);
        $this->respond($sophisticated);
        $this->confirmCardEffect();
        $this->respond("choice_3"); // actionPractice → gainXp (auto-resolves, no prompt)

        $this->assertEquals($xpBefore + 1, $this->countXp());
    }

    // --- Kick (card_event_3_28) ---
    // r=dealDamage(adj),moveMonster(marked)
    // Deal 1 damage to an adjacent monster and move it 1 area.
    public function testKickDamagesAdjacentMonsterAndMovesIt(): void {
        $color = $this->getActivePlayerColor();
        $kick = "card_event_3_28_1";
        $this->seedHand($kick, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1"; // hp=6 so it survives the 1 damage
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $this->assertValidTarget($kick);
        $this->respond($kick);
        // dealDamage(adj) auto-resolves (single adjacent monster); marker_attack set on hex_7_8.
        // moveMonster(marked) Phase 1 auto-resolves to that hex; Phase 2 prompts for destination.
        $this->assertOperation("moveMonster");
        $destinations = $this->getOpArgs()["target"] ?? [];
        $this->assertNotEmpty($destinations, "Kick should offer hexes to push the troll to");
        $newHex = $destinations[0];
        $this->respond($newHex);

        $this->assertEquals(1, $this->countDamage($troll), "Troll takes 1 damage from Kick");
        $this->assertEquals($newHex, $this->tokenLocation($troll), "Troll was moved by Kick");
        $this->assertNotEquals("hex_7_8", $newHex, "Troll moved off its original hex");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($kick), "Kick discarded after use");
    }

    // --- Courage (card_event_3_30) ---
    // r=2dealDamage(adj,'(rank==3 or legend)')
    // Choose an adjacent legend or rank 3 monster. Deal 2 damage to it.
    public function testCourageDeals2DamageToAdjacentRank3Monster(): void {
        $color = $this->getActivePlayerColor();
        $courage = "card_event_3_30_1";
        $this->seedHand($courage, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1"; // rank=3, health=7 — survives 2 damage
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $this->assertValidTarget($courage);
        $this->respond($courage);
        // dealDamage prompts for the target hex (target-picking op).
        $this->assertOperation("dealDamage");
        $this->respond("hex_7_8");

        $this->assertEquals(2, $this->countDamage($troll), "Troll takes 2 damage from Courage");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($courage), "Courage discarded after use");
    }

    // --- Retaliation (card_event_3_31) ---
    // r=2dealDamage(adj), on=TResolveHits
    // Play after an adjacent monster attacked you to deal 2 damage to it.
    public function testRetaliationDeals2DamageToAdjacentAttacker(): void {
        $color = $this->getActivePlayerColor();
        $retaliation = "card_event_3_31_1";
        $this->seedHand($retaliation, $color);

        // Park Embla on plains with a troll (hp=6) adjacent — survives 2 damage so we can read it.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");
        $this->seedRand([5, 5, 5]); // troll str=3 → all hits land

        // Burn both actions → monster turn → troll attacks Embla → TResolveHits fires.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        $this->assertOperation("useCard");
        $this->assertValidTarget($retaliation);
        $this->respond($retaliation);
        // dealDamage prompts for the adjacent monster hex (target-picking op).
        $this->assertOperation("dealDamage");
        $this->respond("hex_7_8");

        $this->assertEquals(2, $this->countDamage($troll), "Troll takes 2 damage from Retaliation");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($retaliation), "Retaliation discarded after use");
    }

    // --- Vigilance (card_event_3_32) ---
    // r=dealDamage(adj), on=TMonsterMove
    // Play around the Monsters Move step. Deal 1 damage to an adjacent monster.
    public function testVigilanceDeals1DamageToAdjacentMonster(): void {
        $color = $this->getActivePlayerColor();
        $vigilance = "card_event_3_32_1";
        $this->seedHand($vigilance, $color);

        // Park Embla on plains; place a troll (hp=6) adjacent so the 1 damage sticks.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // Burn the player turn → monster turn → TMonsterMove trigger fires (pre-movement).
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        // Seed troll attack dice as misses so the troll survives and its 1 Vigilance damage
        // is still readable on the token.
        $this->seedRand([1, 1, 1, 1, 1, 1]);
        $this->skip();
        $this->skipIfOp("drawEvent");

        $this->assertOperation("useCard");
        $this->assertValidTarget($vigilance);
        $this->respond($vigilance);
        // dealDamage prompts for the adjacent monster hex (target-picking op).
        $this->assertOperation("dealDamage");
        $this->respond("hex_7_8");

        $this->assertEquals(1, $this->countDamage($troll), "Troll takes 1 damage from Vigilance");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($vigilance), "Vigilance discarded after use");
    }

    // --- Magic Runes (card_event_3_34) ---
    // r=counter('3 * (countRunes>0)'):addDamage, on=TRoll
    // After a roll that produced at least one [RUNE], add 3 damage to the attack.
    public function testMagicRunesAddsThreeDamageWhenRuneRolled(): void {
        $color = $this->getActivePlayerColor();
        $magicRunes = "card_event_3_34_1";
        $this->seedHand($magicRunes, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // Embla strength = 3 dice. Seed 1 hit + 1 rune + 1 miss → 1 base damage, rune count > 0.
        $this->seedRand([5, 3, 1]);
        $this->respond("actionAttack");

        $this->assertOperation("useCard");
        $this->assertValidTarget($magicRunes);
        $this->respond($magicRunes);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // 1 hit + 3 added damage = 4 total damage on the troll.
        $this->assertEquals(4, $this->countDamage($troll), "Troll takes 1 base hit + 3 from Magic Runes");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($magicRunes));
    }

    // --- Durability (card_event_3_36) ---
    // r=repairCard(max) — "Remove all damage from an equipment card."
    public function testDurabilityRemovesAllDamageFromChosenEquipCard(): void {
        $color = $this->getActivePlayerColor();
        $durability = "card_event_3_36_1";
        $this->seedHand($durability, $color);

        $picked = "card_equip_3_15"; // Flimsy Blade
        $other = "card_equip_3_19"; // Blade Decorations
        $this->game->tokens->moveToken($picked, "tableau_$color");
        $this->game->tokens->moveToken($other, "tableau_$color");

        // Damage both cards so the player has a real choice (multi-target prompt).
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $picked, ["message" => ""]);
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $other, ["message" => ""]);

        $this->assertValidTarget($durability);
        $this->respond($durability);
        // repairCard(max) prompts for the target card; pick Flimsy Blade.
        $this->assertOperation("repairCard");
        $this->respond($picked);

        $this->assertEquals(0, $this->countDamage($picked), "All damage stripped from the chosen card");
        $this->assertEquals(2, $this->countDamage($other), "Other card untouched");
        $this->assertNotEquals("hand_$color", $this->tokenLocation($durability), "Durability discarded after use");
    }

    // --- Preparations (card_event_3_37) ---
    // r=spendAction(actionPrepare):drawEvent(max)
    // Spend a prepare action to draw cards until you have 4.
    public function testPreparationsSpendsPrepareActionAndDrawsToHandLimit(): void {
        $color = $this->getActivePlayerColor();
        $preparations = "card_event_3_37_1";
        $this->seedHand($preparations, $color);

        // Seed 4 known cards on top of the event deck (handLimit=4, hand will be empty after
        // Preparations discards itself → drawEvent(max) draws 4).
        $topOfDeck = ["card_event_3_35_1", "card_event_3_33_1", "card_event_3_32_1", "card_event_3_30_1"];
        $this->seedDeck("deck_event_$color", $topOfDeck);

        $hero = $this->game->getHero($color);
        $this->assertNotContains("actionPrepare", $hero->getActionsTaken());

        $this->assertValidTarget($preparations);
        $this->respond($preparations);
        $this->confirmCardEffect(); // spendAction(actionPrepare) confirm — drawEvent(max) auto-resolves

        // Prepare action consumed.
        $this->assertContains("actionPrepare", $hero->getActionsTaken());
        // Hand filled to limit (4).
        $this->assertEquals($hero->getHandLimit(), $hero->getHandSize());
        foreach ($topOfDeck as $cardId) {
            $this->assertEquals("hand_$color", $this->tokenLocation($cardId), "$cardId drawn into hand");
        }
        // Preparations itself discarded after use.
        $this->assertNotEquals("hand_$color", $this->tokenLocation($preparations));
    }
}
