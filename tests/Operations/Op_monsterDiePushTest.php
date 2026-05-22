<?php

declare(strict_types=1);

/**
 * Tests for Op_monsterDiePush — Phase D3.
 * Heroes adjacent to a monster get pushed one hex toward Grimheim via the
 * per-hex `dir` tag (HexMap::getMonsterNextHex). Heroes in Grimheim, or
 * with the next hex null/occupied, stay put.
 */
final class Op_monsterDiePushTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_12_7");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testHeroAdjacentToMonsterIsPushedTowardGrimheim(): void {
        // hex_12_7 has dir=7 (SW) and no Grimheim neighbour, so getMonsterNextHex → hex_11_8.
        $this->call_resolve();
        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("hero_1"));
    }

    public function testHeroNotAdjacentToMonsterIsNotPushed(): void {
        // Pull the only monster off the map → hero has no adjacent monster.
        $this->game->tokens->moveToken("monster_goblin_1", "supply_monster");
        $this->game->hexMap->invalidateOccupancy();
        $this->call_resolve();
        $this->assertEquals("hex_12_7", $this->game->tokens->getTokenLocation("hero_1"), "no adjacent monster → no push");
    }

    public function testHeroInGrimheimIsSkipped(): void {
        // Even if a monster is somehow adjacent, Grimheim hexes never get pushed.
        $this->game->tokens->moveToken("hero_1", "hex_10_9"); // Grimheim
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_9");
        $this->game->hexMap->invalidateOccupancy();
        $this->call_resolve();
        $this->assertEquals("hex_10_9", $this->game->tokens->getTokenLocation("hero_1"));
    }

    public function testHeroStaysWhenPushTargetIsOccupied(): void {
        // Drop a second monster on the destination so the push has nowhere to land.
        $this->game->tokens->moveToken("monster_goblin_2", "hex_11_8");
        $this->game->hexMap->invalidateOccupancy();
        $this->call_resolve();
        $this->assertEquals("hex_12_7", $this->game->tokens->getTokenLocation("hero_1"), "occupied destination → stay");
    }

    public function testHeroStaysWhenPushTargetIsMountain(): void {
        // hex_16_7 (MarshOfSorrow plains) has dir=9 → hex_15_7 (pure mountain, no named location).
        // Heroes can't stand on a mountain (FORUM #4 — push blocked by mountain).
        $this->game->tokens->moveToken("hero_1", "hex_16_7");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_17_7");
        $this->game->hexMap->invalidateOccupancy();
        $this->call_resolve();
        $this->assertEquals("hex_16_7", $this->game->tokens->getTokenLocation("hero_1"), "mountain blocks push");
    }

    public function testPushIntoGrimheimIsAllowed(): void {
        // hex_11_7 is one step from Grimheim — getMonsterNextHex returns the Grimheim
        // neighbour directly (gates rule). Push lands the hero on a Grimheim hex with
        // no town-piece destruction (monster-only rule).
        $this->game->tokens->moveToken("hero_1", "hex_11_7");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_7");
        $this->game->hexMap->invalidateOccupancy();
        $housesBefore = count($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->call_resolve();
        $heroHex = $this->game->tokens->getTokenLocation("hero_1");
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex), "hero ends inside Grimheim");
        $housesAfter = count($this->game->tokens->getTokensOfTypeInLocation("house", "hex%"));
        $this->assertEquals($housesBefore, $housesAfter, "no town pieces destroyed when a hero enters Grimheim");
    }
}
