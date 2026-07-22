<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Legend special abilities (see misc/docs/TODO.md "Legend special abilities").
 * Each test places a legend on the map and drives one monster turn, then asserts the outcome.
 */
class Campaign_LegendAbilityTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    private function driveOneMonsterTurn(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
    }

    /** Strength value from each "${char} attacks ${target} with strength ${strength}" notify line. */
    private function attackStrengths(): array {
        return array_map(
            fn($n) => $n["args"]["strength"] ?? null,
            array_values(
                array_filter(
                    $this->game->notify->_getNotifications(),
                    fn($n) => str_contains($n["log"] ?? "", "attacks") && str_contains($n["log"] ?? "", "strength")
                )
            )
        );
    }

    public function testGrendelIIAttacksTwice(): void {
        // Grendel II (red) makes two attacks in the monster turn.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_legend_3_2")->moveTo("hex_7_8", ""); // adjacent to hero
        $this->game->randQueue = array_fill(0, 20, 1); // all misses so the hero survives both attacks

        $this->driveOneMonsterTurn();

        $this->assertCount(2, $this->attackStrengths(), "Grendel II makes two attacks");
    }

    public function testNidhuggrAttacksWithRemainingHealth(): void {
        // Wyrm: Nidhuggr's attack strength equals its remaining health, not the fixed
        // material strength (0). Health 13 minus 10 damage => attacks at strength 3.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $nidhuggr = "monster_legend_6_1";
        $this->game->getMonster($nidhuggr)->moveTo("hex_7_8", ""); // adjacent to hero
        $this->game->effect_moveCrystals($nidhuggr, "red", 10, $nidhuggr, ["message" => ""]);

        $this->driveOneMonsterTurn();

        $this->assertEquals([3], $this->attackStrengths(), "Nidhuggr attacks at remaining health (13 - 10 = 3)");
    }
}
