<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_repairCard;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_repairCardTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Place some equipment on tableau
        $this->game->tokens->moveToken("card_equip_1_21", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_23", "tableau_" . PCOLOR);
    }

    private function createOp(string $expr = "99repairCard"): Op_repairCard {
        /** @var Op_repairCard */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function addDamageToCard(string $cardId, int $amount): void {
        $this->game->effect_moveCrystals($cardId, "red", $amount, $cardId, ["message" => ""]);
    }

    private function getCardDamage(string $cardId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
    }

    public function testNoDamagedCardsAllNotApplicable(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        // Equipment cards should be listed but not applicable
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["card_equip_1_21"]["q"]);
    }

    public function testDamagedCardIsValid(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEquals(Material::RET_OK, $moves["card_equip_1_21"]["q"]);
    }

    public function testUndamagedCardNotApplicable(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        // card_equip_1_23 has no damage
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["card_equip_1_23"]["q"]);
    }

    public function testResolveRemovesAllDamage(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $this->assertEquals(3, $this->getCardDamage("card_equip_1_21"));
        $op = $this->createOp();
        $op->action_resolve(["target" => "card_equip_1_21"]);
        $this->assertEquals(0, $this->getCardDamage("card_equip_1_21"));
    }

    public function testResolveDoesNotAffectOtherCards(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        $op = $this->createOp();
        $op->action_resolve(["target" => "card_equip_1_21"]);
        // Other card should still have damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_23"));
    }

    public function testLimitedCountRemovesOnlyUpToCount(): void {
        $this->addDamageToCard("card_equip_1_21", 3);
        $op = $this->createOp("2repairCard");
        $op->action_resolve(["target" => "card_equip_1_21"]);
        // Should remove only 2 of 3 damage
        $this->assertEquals(1, $this->getCardDamage("card_equip_1_21"));
    }

    public function testPresetTargetReturnsOnlyThatTarget(): void {
        $this->addDamageToCard("card_equip_1_21", 2);
        $this->addDamageToCard("card_equip_1_23", 1);
        /** @var Op_repairCard */
        $op = $this->game->machine->instanciateOperation("5repairCard", PCOLOR, ["target" => "card_equip_1_21"]);
        $moves = $op->getPossibleMoves();
        $this->assertCount(1, $moves);
        $this->assertArrayHasKey("card_equip_1_21", $moves);
    }
}
