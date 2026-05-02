<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_killed — TMonsterKilled-handler gate that filters on which
 * monster just died. Reads the killed monster id by looking at marker_attack's
 * location (set by Op_applyDamage on kill) and passing the monster id to
 * evaluateExpression as context.
 *
 * Tests park the just-killed monster on a hex with marker_attack pointing at
 * it (mimicking the post-Op_applyDamage / pre-Op_finishKill state where the
 * trigger handlers run).
 */
final class Op_killedTest extends AbstractOpTestCase {
    private string $killedHex = "hex_12_8";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    /** Mimic post-applyDamage state: monster on $hex, marker_attack on same hex. */
    private function setKilledMonster(string $monsterId, string $hex): void {
        $this->game->tokens->moveToken($monsterId, $hex);
        $this->game->tokens->moveToken("marker_attack", $hex);
    }

    public function testGatePassesWhenFactionMatches(): void {
        // Goblin faction = trollkin
        $this->setKilledMonster("monster_goblin_1", $this->killedHex);
        $this->createOp("killed(trollkin)");
        $this->assertFalse($this->op->isVoid());
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsWhenFactionMismatches(): void {
        // Sprite faction = firehorde, gate expects trollkin
        $this->setKilledMonster("monster_sprite_1", $this->killedHex);
        $this->createOp("killed(trollkin)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGatePassesOnRankExpression(): void {
        // Troll rank = 3
        $this->setKilledMonster("monster_troll_1", $this->killedHex);
        $this->createOp("killed('rank>=3')");
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsOnRankMismatch(): void {
        // Goblin rank = 1
        $this->setKilledMonster("monster_goblin_1", $this->killedHex);
        $this->createOp("killed('rank>=3')");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGatePassesOnLegendKeyword(): void {
        $this->setKilledMonster("monster_legend_1_1", $this->killedHex);
        $this->createOp("killed(legend)");
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGatePassesOnDisjunction(): void {
        // 'rank==3 or legend' — troll matches via rank
        $this->setKilledMonster("monster_troll_1", $this->killedHex);
        $this->createOp("killed('rank==3 or legend')");
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testGateVoidsWhenMarkerHasNoMonster(): void {
        // marker_attack at a hex with no character on it
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        $this->createOp("killed(trollkin)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testGateVoidsWhenMarkerInLimbo(): void {
        // marker_attack starts in limbo (no active attack)
        $this->createOp("killed(trollkin)");
        $this->assertNoValidTargetsAndError(Material::ERR_PREREQ);
    }

    public function testAcceptAllWhenFilterMissing(): void {
        $this->setKilledMonster("monster_goblin_1", $this->killedHex);
        $this->createOp("killed");
        $this->assertFalse($this->op->noValidTargets());
    }

    public function testResolveIsNoOp(): void {
        $this->setKilledMonster("monster_goblin_1", $this->killedHex);
        $this->createOp("killed(trollkin)");
        $before = $this->countYellowCrystals($this->getPlayersTableau());
        $this->op->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $after = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($before, $after);
    }

    // -------------------------------------------------------------------------
    // Composition: chain with paygain
    // -------------------------------------------------------------------------

    public function testChainWithGatePassingRunsEffect(): void {
        // killed(trollkin):gainXp — when killed monster is trollkin, gain 1 XP.
        $this->setKilledMonster("monster_goblin_1", $this->killedHex);
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->game->machine->push("killed(trollkin):gainXp", $this->owner);
        $this->game->machine->dispatchAll();
        $this->assertEquals($xpBefore + 1, $this->countYellowCrystals($this->getPlayersTableau()));
    }

    public function testChainVoidsWhenGateFails(): void {
        // Sprite is firehorde — gate(trollkin) fails, gainXp must not run.
        $this->setKilledMonster("monster_sprite_1", $this->killedHex);
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->game->machine->push("killed(trollkin):gainXp", $this->owner);
        $this->game->machine->dispatchAll();
        $this->assertEquals($xpBefore, $this->countYellowCrystals($this->getPlayersTableau()));
    }
}
