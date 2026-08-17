<?php

declare(strict_types=1);

/**
 * Unit tests for Op_c_orebiter — sub-op of Op_actionAttack triggered when a hero with
 * Orebiter (card_equip_4_19) equipped picks the card from the attack target list.
 * Prompts for an adjacent mountain hex, places monster_goldvein there, queues a roll.
 */
final class Op_c_orebiterTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->init(4);

        // Park Boldur on hex_5_8 (forest) which is adjacent to hex_5_7 (mountain).
        $this->game->tokens->moveToken("hero_4", "hex_5_8");
        // reason mirrors how Op_actionAttack would queue this sub-op (auto-injected by framework).
        $this->createOp(null, ["reason" => "Op_actionAttack"]);
    }

    public function testAdjacentMountainsAreValidTargets(): void {
        $this->assertValidTarget("hex_5_7");
        $this->assertValidTarget("hex_6_7"); // also adjacent and a mountain
    }

    public function testNonMountainHexesAreNotTargets(): void {
        $this->assertNotValidTarget("hex_5_9"); // plains
        $this->assertNotValidTarget("hex_4_8"); // forest
    }

    public function testNoAdjacentMountainNoValidTargets(): void {
        // Plains hex_7_9 has no mountain on it or next to it - and is outside Grimheim,
        // so this exercises the empty target walk, not the Grimheim short-circuit.
        $this->game->tokens->moveToken("hero_4", "hex_7_9");
        $this->createOp();

        $this->assertNoValidTargets();
    }

    public function testOwnMountainHexIsValidTarget(): void {
        // BGA #238473 adjacency: standing ON a Troll Caves mountain, the own hex is minable.
        $this->game->tokens->moveToken("hero_4", "hex_6_6");
        $this->createOp(null, ["reason" => "Op_actionAttack"]);

        $this->assertValidTarget("hex_6_6");
        $this->assertValidTarget("hex_5_7"); // neighbor mountains still offered
        $this->assertValidTarget("hex_6_7");
    }

    public function testGrimheimBorderMountainIsNotTarget(): void {
        // Grimheim isolation (RULES.md): no terrain interaction from inside town,
        // even though mountain hex_9_11 is axially adjacent to border hex_9_10.
        $this->game->tokens->moveToken("hero_4", "hex_9_10");
        $this->createOp(null, ["reason" => "Op_actionAttack"]);

        $this->assertNoValidTargets();
    }

    public function testResolvePlacesGoldVeinAndQueuesRoll(): void {
        $this->call_resolve("hex_5_7");

        $this->assertEquals("hex_5_7", $this->game->tokens->getTokenLocation("monster_goldvein"));
        $this->assertEquals("hex_5_7", $this->game->tokens->getTokenLocation("marker_attack"));

        // A roll op should be queued, with target=mountain hex and reason=Op_actionAttack
        // so attack-trigger cards (Quiver, Trollbane, etc.) react.
        $ops = $this->game->machine->getAllOperations($this->owner);
        $opTypes = array_map(fn($o) => $o["type"], $ops);
        foreach ($ops as $o) {
            if (str_contains($o["type"], "roll")) {
                $data = is_string($o["data"]) ? json_decode($o["data"], true) : $o["data"] ?? [];
                $this->assertEquals("hex_5_7", $data["target"]);
                $this->assertEquals("Op_actionAttack", $data["reason"]);
                return;
            }
        }
        $this->fail("roll operation not queued; types found: " . implode(",", $opTypes));
    }
}
