<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's equipment cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurEquipTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Dvalin's Pick (card_equip_4_20) ---
    // r=spendAction(actionAttack):gainXp,gainMana,drawEvent — spend attack action for 1 XP, 1 mana, 1 card.

    public function testDvalinsPickSpendsAttackActionForXpManaAndCardDraw(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_equip_4_20";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Boldur's Rapid Strike I (mana=1) is the sole mana-holding card → gainMana auto-resolves onto it.
        $manaCard = "card_ability_4_3";
        $manaBefore = $this->countTokens("crystal_green", $manaCard);

        // Seed one event card so drawEvent has something to pull.
        $drawnCard = "card_event_4_35_1"; // Dodge
        $this->seedDeck("deck_event_$color", [$drawnCard]);

        $xpBefore = $this->countXp();

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect(); // spendAction(actionAttack) confirm

        // Attack action slot consumed — can't re-take it this turn.
        $hero = $this->game->getHero($color);
        $this->assertContains("actionAttack", $hero->getActionsTaken());
        // Resources gained.
        $this->assertEquals($xpBefore + 1, $this->countXp());
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $manaCard));
        // Event drawn into hand.
        $this->assertEquals("hand_$color", $this->tokenLocation($drawnCard));
    }

    // --- Dwarf Pick (card_equip_4_25) ---
    // Passive +1 strength, no r, no on. Pure stat boost.
    // Base Boldur I = 3 + Boldur's First Pick(1) = 4 → with Dwarf Pick = 5 dice.

    public function testDwarfPickAddsOneStrengthDie(): void {
        $cardId = "card_equip_4_25";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // Recompute strength tracker — calcBaseStrength only runs on setup/turnEnd/upgrade.
        $this->game->getHero($color)->recalcTrackers();

        $this->assertEquals(5, $this->game->getHero($color)->getAttackStrength());

        // Boldur outside Grimheim, troll (health=6) adjacent — survives 5 hits.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // Seed exactly 5 dice — confirms Dwarf Pick added the 5th die.
        $this->seedRand([5, 5, 5, 5, 5]);
        $this->respond("hex_7_8");

        // Troll took 5 damage (survives, health=6).
        $this->assertEquals(5, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        // Rand queue exhausted — confirms exactly 5 dice were rolled.
        $this->assertEmpty($this->game->randQueue, "Strength should consume exactly 5 dice");
    }

    // --- Orebiter (card_equip_4_19) ---
    // No r/on — Op_actionAttack scans for Orebiter on tableau and adds it to the attack
    // target list. Picking the card dispatches to Op_c_orebiter, which prompts for an
    // adjacent mountain hex, places monster_goldvein there, and runs the standard pipeline
    // (roll → resolveHits → dealDamage). GoldVein converts each damage point into 1 XP and
    // despawns. Full pipeline keeps amplifying cards (Berserk, Quiver) reactive.

    public function testOrebiterMinesGoldFromAdjacentMountain(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_equip_4_19";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Boldur on hex_5_8 (forest) — adjacent mountain at hex_5_7.
        $this->game->tokens->moveToken($this->heroId, "hex_5_8");

        $xpBefore = $this->countXp();

        // Boldur I(3) + Boldur's First Pick(1) = 4 dice. Seed 3 hits, 1 miss → 3 XP.
        $this->seedRand([5, 5, 5, 1]);

        // No monsters in range → Op_actionAttack auto-resolves on the sole target (the
        // Orebiter card), then prompts for the mountain hex via Op_c_orebiter.
        $this->respond("actionAttack");
        $this->respond("hex_5_7"); // pick mountain hex

        $this->assertEquals($xpBefore + 3, $this->countXp());
        // Vein returned to supply after the attack regardless of hits.
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goldvein"));
        // Attack action slot consumed.
        $this->assertContains("actionAttack", $this->game->getHero($color)->getActionsTaken());
    }

    public function testOrebiterAwardsZeroXpOnFullMiss(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_equip_4_19";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->tokens->moveToken($this->heroId, "hex_5_8");

        $xpBefore = $this->countXp();

        // 4 dice all missing.
        $this->seedRand([1, 1, 1, 1]);

        $this->respond("actionAttack");
        $this->respond("hex_5_7");

        $this->assertEquals($xpBefore, $this->countXp());
        // Even on a full miss, the gold vein despawns (zero-hit dealDamage still fires).
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goldvein"));
    }

    // --- Smiterbiter (card_equip_4_21) ---
    // r=c_smiter, on=TActionAttack — useCard prompt to spend stored reds for added damage.
    // Storage path: onMonsterKilled hook auto-pulls min(overkill, 3 - stored) reds onto the
    // card directly (bypasses useCard / r entirely).

    public function testSmiterbiterStoresOverkillThenSpendsOnNextAttack(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_equip_4_21";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // Smiterbiter grants +1 strength; recalc since calcBaseStrength only runs at setup/turnEnd/upgrade.
        $this->game->getHero($color)->recalcTrackers();

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_6_9", "");

        // Turn 1 attack — Boldur I(3) + First Pick(1) + Smiterbiter(1) = 5 dice. 4 hits vs goblin (h=2) → overkill 2.
        $this->seedRand([5, 5, 5, 5, 1]);
        $this->respond("actionAttack");
        $this->respond("hex_7_8");

        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        $this->assertEquals(2, $this->countTokens("crystal_red", $cardId), "Smiterbiter should hold 2 reds after overkill 2");

        // Burn the remaining 2 actions to advance the turn loop.
        $this->respond("actionPractice");
        $this->skip();
        $this->skipIfOp("drawEvent");

        // Turn 2 attack — Smiterbiter offers a useCard prompt to spend stored reds.
        $this->seedRand([5, 5, 5, 5, 1]); // 4 hits = 4 base damage on troll (h=6)
        $this->respond("actionAttack");
        $this->respond("hex_6_9");

        $this->assertOperation("c_smiter");

        // c_smiter prompts for spend amount (1..2). Spend both.
        $this->respond("2");

        $this->assertEquals(0, $this->countTokens("crystal_red", $cardId), "All reds spent off card");
        $this->assertEquals(6, $this->countDamage($troll));
    }
}
