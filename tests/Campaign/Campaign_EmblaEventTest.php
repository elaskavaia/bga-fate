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
}
