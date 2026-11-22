<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #257127: a hero inside Grimheim cannot interact outside it, so Wrecking Ball out of Grimheim
 * always steps out to an empty adjacent hex first (2 steps). Boldur starts on hex_10_9; Boldur I move = 3.
 */
class Campaign_BoldurWreckingGrimheimTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]);
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
        $this->game->tokens->moveToken("card_ability_4_7", "tableau_" . $this->getActivePlayerColor());
        $this->game->hexMap->invalidateOccupancy();
        $this->assertEquals("hex_10_9", $this->tokenLocation($this->heroId));
    }

    /** Click the occupied hex, push the goblin to the first non-Grimheim hex, return the follow-up move op data. */
    private function wreckFromGrimheim(string $target): array {
        $this->game->getMonster("monster_goblin_1")->moveTo($target, "");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertValidTarget($target);
        $this->respond($target);
        $this->assertOperation("c_wrecking");
        $push = array_values(array_filter(
            $this->getOpArgs()["target"],
            fn($hex) => !$this->game->hexMap->isInGrimheim($hex)
        ))[0];
        $this->respond($push);
        $this->assertOperation("move");
        $this->assertEquals($target, $this->tokenLocation($this->heroId));
        return $this->getOpArgs()["data"];
    }

    public function testWreckingFromGrimheimStepsOutFirstForTwoSteps(): void {
        $this->assertNotEquals(1, $this->game->hexMap->getHexDistance("hex_10_9", "hex_8_8"));
        $data = $this->wreckFromGrimheim("hex_8_8");
        $this->assertEquals(2, $data["moved"], "auto-routes out via an empty ring hex, then wrecks in");
        $this->assertEquals(1, $data["count"]);
    }

    public function testWreckingFromGrimheimStepsOutFirstEvenWhenTargetTouchesHeroHex(): void {
        $this->assertEquals(1, $this->game->hexMap->getHexDistance("hex_10_9", "hex_11_8"));
        $data = $this->wreckFromGrimheim("hex_11_8");
        $this->assertEquals(2, $data["moved"], "cannot wreck from inside Grimheim, steps out first");
        $this->assertEquals(1, $data["count"]);
    }

    public function testWreckingFromGrimheimNotOfferedWithOneStep(): void {
        $this->game->getMonster("monster_goblin_1")->moveTo("hex_11_8", "");
        $this->game->hexMap->invalidateOccupancy();
        $this->game->machine->push("move", $this->getActivePlayerColor(), ["count" => 1]);
        $this->game->machine->dispatchAll();
        $this->assertOperation("move");
        $this->assertNotValidTarget("hex_11_8", "wrecking out of Grimheim needs 2 steps");
    }
}
