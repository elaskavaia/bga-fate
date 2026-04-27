<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_heal;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_healTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR, hero 2 (Alva) to BCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("card_hero_2_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
    }

    private function addDamage(string $heroId, int $amount): void {
        $this->game->effect_moveCrystals($heroId, "red", $amount, $heroId, ["message" => ""]);
    }

    private function getDamage(string $heroId): int {
        return $this->countRedCrystals($heroId);
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testHealSelfRemovesDamage(): void {
        $this->addDamage("hero_1", 4);
        $this->createOp("2heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealSelfCapsAtCurrentDamage(): void {
        $this->addDamage("hero_1", 1);
        $this->createOp("2heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(0, $this->getDamage("hero_1"));
    }

    public function testHealSelfNotApplicableWhenNoDamage(): void {
        $this->createOp("2heal(self)");
        $this->assertTargetError("hex_11_8", Material::ERR_NOT_APPLICABLE);
    }

    public function testHealSelfTargetsWhenDamaged(): void {
        $this->addDamage("hero_1", 3);
        $this->createOp("2heal(self)");
        $this->assertValidTarget("hex_11_8");
    }

    public function testHealAdjIncludesSelf(): void {
        $this->addDamage("hero_1", 2);
        $this->createOp("1heal(adj)");
        $this->assertValidTarget("hex_11_8");
    }

    public function testHealAdjIncludesAdjacentHero(): void {
        // hex_11_8 and hex_12_8 are adjacent
        $this->addDamage("hero_2", 3);
        $this->createOp("1heal(adj)");
        $this->assertValidTarget("hex_12_8");
    }

    public function testHealAdjExcludesDistantHero(): void {
        // Move hero 2 far away
        $this->game->tokens->moveToken("hero_2", "hex_8_5");
        $this->addDamage("hero_2", 3);
        $this->createOp("1heal(adj)");
        $this->assertNotValidTarget("hex_8_5");
    }

    public function testHealAdjResolvesOnTarget(): void {
        $this->addDamage("hero_2", 4);
        $this->createOp("2heal(adj)");
        $this->call_resolve("hex_12_8");
        $this->assertEquals(2, $this->getDamage("hero_2"));
    }

    public function testHealCountFromExpression(): void {
        $this->addDamage("hero_1", 5);
        $this->createOp("3heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealAdjSplitsAcrossDamagedHeroes(): void {
        // Both heroes damaged — 2heal(adj) should remove 1 from the picked hero
        // and re-queue heal(adj) for the remaining unit so the player may switch heroes.
        $this->addDamage("hero_1", 2);
        $this->addDamage("hero_2", 2);
        $this->createOp("2heal(adj)");
        $this->call_resolve("hex_11_8");

        $this->assertEquals(1, $this->getDamage("hero_1"));
        $this->assertEquals(2, $this->getDamage("hero_2"));

        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("heal", $queued["type"]);

        /** @var Op_heal $requeued */
        $requeued = $this->game->machine->instantiateOperationFromDbRow($queued);
        $this->assertEquals("adj", $requeued->getParam(0), "re-queue must preserve (adj) mode");
        $this->assertEquals(1, (int) $requeued->getCount(), "re-queue must carry the remaining count");
    }

    public function testHealPresetTargetUsesHexId(): void {
        $this->addDamage("hero_1", 3);
        $this->createOp("2heal", ["target" => "hex_11_8"]);
        $this->assertValidTargetCount(1);
        $this->assertValidTarget("hex_11_8");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(1, $this->getDamage("hero_1"));
    }
}
