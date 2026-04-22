<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's event cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurEventTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Berserk (card_event_4_32) ---
    // r=spendHealth:3addDamage, on=TActionAttack
    // "Take 1 unpreventable damage to add 3 damage to this attack."

    public function testBerserkTakesUnpreventableDamageAndAddsAttackDamage(): void {
        $color = $this->getActivePlayerColor();
        $berserk = "card_event_4_32_1";
        $this->seedHand($berserk, $color);

        // Move Boldur out of Grimheim, place a troll (health=6) adjacent to survive 3 damage
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $heroDamageBefore = $this->countDamage($this->heroId);
        // Seed 3 miss dice → base attack deals 0 damage; Berserk adds 3.
        $this->seedRand([1, 1, 1]);
        $this->respond("actionAttack");

        // TActionAttack trigger offers Berserk
        $this->assertOperation("useCard");
        $this->assertValidTarget($berserk);
        $this->respond($berserk);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // Boldur took 1 unpreventable damage (armor is bypassed)
        $this->assertEquals($heroDamageBefore + 1, $this->countDamage($this->heroId));
        // Troll took 3 damage from addDamage (base roll = 0 hits)
        $this->assertEquals(3, $this->countDamage($troll));
    }

    // --- Boldur's Gate (card_event_4_36) ---
    // r=in(Grimheim):2spendXp:addTownPiece — spend 2 XP in Grimheim to restore a town piece.

    public function testBoldursGateSpendsTwoXpToRestoreTownPiece(): void {
        $color = $this->getActivePlayerColor();
        $gate = "card_event_4_36_1";
        $this->seedHand($gate, $color);

        // Boldur starts in Grimheim — confirm.
        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)));

        // Seed 2 gold/XP on the tableau.
        $this->game->effect_moveCrystals($this->heroId, "yellow", 2, "tableau_$color", ["message" => ""]);
        $xpBefore = $this->countXp();

        // Destroy a house so addTownPiece has something to restore.
        $house = array_key_first($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->game->tokens->moveToken($house, "limbo");

        $this->assertValidTarget($gate);
        $this->respond($gate);
        $this->confirmCardEffect();

        $this->assertEquals($xpBefore - 2, $this->countXp());
        $this->assertNotEquals("limbo", $this->tokenLocation($house));
    }
}
