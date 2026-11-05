<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #253990: Dwarf Mail (card_equip_4_18) "Prevent the first damage dealt to Boldur
 * each monster turn" is offered on every monster attack in the same monster turn.
 */
class Campaign_Bug253990Test extends CampaignBaseTest {
    private const DWARF_MAIL = "card_equip_4_18";

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testDwarfMailOfferedAgainOnSecondAttackInOneMonsterTurn(): void {
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->game->tokens->moveToken(self::DWARF_MAIL, "tableau_$color");

        $this->game->tokens->moveToken($heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_7_8", "");
        $this->game->getMonster("monster_brute_2")->moveTo("hex_6_9", "");
        // 4 dice per brute: 2 hits, armor eats 1, 1 left for Dwarf Mail
        $this->seedRand([5, 5, 2, 2, 5, 5, 2, 2]);

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        $this->assertOperation("useCard");
        $this->assertValidTarget(self::DWARF_MAIL);
        $this->respond(self::DWARF_MAIL);
        $this->confirmCardEffect();
        $this->assertEquals(0, $this->countDamage($heroId));

        // documents buggy behavior for BGA #253990; flip assertion when fixed
        $this->assertOperation("useCard");
        $this->assertValidTarget(self::DWARF_MAIL, "Dwarf Mail offered again on the second attack of the same monster turn");
        $this->respond(self::DWARF_MAIL);
        $this->confirmCardEffect();
        $this->assertEquals(0, $this->countDamage($heroId));
        $this->assertEmpty($this->game->randQueue);
    }
}
