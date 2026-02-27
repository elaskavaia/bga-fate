<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_reinforcement;
use Bga\Games\Fate\Operations\Op_turnMonster;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_reinforcementTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createTokens();
    }

    private function createReinforcementOp(array $data = []): Op_reinforcement {
        if (!isset($data["deck"])) {
            $data["deck"] = "deck_monster_yellow";
        }
        /** @var Op_reinforcement */
        $op = $this->game->machine->instanciateOperation("reinforcement", ACOLOR, $data);
        return $op;
    }

    private function createTurnMonsterOp(array $data = []): Op_turnMonster {
        /** @var Op_turnMonster */
        $op = $this->game->machine->instanciateOperation("turnMonster", ACOLOR, $data);
        return $op;
    }

    // -------------------------------------------------------------------------
    // parseSpawnString / monster placement
    // -------------------------------------------------------------------------

    public function testPlacesMonstersFromYellowCard(): void {
        // Card 32 "Younglings" in DarkForest: B,G,G,G,G,G,G,G (1 brute + 7 goblins)
        // Move card 32 to top of yellow deck
        $this->game->tokens->db->moveToken("card_monster_32", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_32", 999); // highest = top

        $this->game->setPlayersNumber(1);
        $op = $this->createReinforcementOp(["deck" => "deck_monster_yellow"]);
        $op->resolve();

        // DarkForest hexes should now have monsters
        $darkForestHexes = $this->game->getHexesInLocation("DarkForest");
        $monstersPlaced = 0;
        foreach ($darkForestHexes as $hex) {
            if ($this->game->isOccupied($hex)) {
                $monstersPlaced++;
            }
        }
        // 8 monsters: 1 brute + 7 goblins
        $this->assertEquals(8, $monstersPlaced);
    }

    public function testCorrectMonsterTypesPlaced(): void {
        // Card 31 "Strolling" in OgreValley: T,T,B (2 trolls + 1 brute)
        $this->game->tokens->db->moveToken("card_monster_31", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_31", 999);

        $this->game->setPlayersNumber(1);
        $op = $this->createReinforcementOp(["deck" => "deck_monster_yellow"]);
        $op->resolve();

        // Check OgreValley hexes for correct monster types
        $ogreValleyHexes = $this->game->getHexesInLocation("OgreValley");
        $trollCount = 0;
        $bruteCount = 0;
        foreach ($ogreValleyHexes as $hex) {
            $tokens = $this->game->tokens->getTokensOfTypeInLocation("monster", $hex);
            foreach ($tokens as $token) {
                if (str_starts_with($token["key"], "monster_troll")) {
                    $trollCount++;
                } elseif (str_starts_with($token["key"], "monster_brute")) {
                    $bruteCount++;
                }
            }
        }
        $this->assertEquals(2, $trollCount);
        $this->assertEquals(1, $bruteCount);
    }

    public function testSkipsCardWhenHexOccupied(): void {
        // Card 36 "Viral Trolls" in DarkForest: T,T,T (monsters at hex indices 0,1,2)
        // Card 31 "Strolling" in OgreValley: T,T,B (no occupied hexes there)
        $this->game->tokens->db->moveToken("card_monster_36", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_36", 999); // top
        $this->game->tokens->db->moveToken("card_monster_31", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_31", 998); // second

        // Occupy the first hex in DarkForest — card 36 can't place at index 0
        $darkForestHexes = $this->game->getHexesInLocation("DarkForest");
        $this->game->tokens->db->moveToken("hero_1", $darkForestHexes[0]);

        $this->game->setPlayersNumber(1);
        $op = $this->createReinforcementOp(["deck" => "deck_monster_yellow"]);
        $op->resolve();

        // Card 36 should be skipped (on display at state=1), card 31 placed in OgreValley
        $card36State = $this->game->tokens->db->getTokenState("card_monster_36");
        $this->assertEquals(1, $card36State); // skipped

        $ogreValleyHexes = $this->game->getHexesInLocation("OgreValley");
        $monsterCount = 0;
        foreach ($ogreValleyHexes as $hex) {
            $tokens = $this->game->tokens->getTokensOfTypeInLocation("monster", $hex);
            $monsterCount += count($tokens);
        }
        $this->assertEquals(3, $monsterCount);
    }

    public function testRetryOnUnplaceableCard(): void {
        // Card 22 "Imp-ressive Swarm" in DeadPlains: I,I,I,I,I,I,I,S,S (9 monsters at indices 0-8)
        // Card 31 "Strolling" in OgreValley: T,T,B (different location, no blocked hexes)
        // Occupy hex[0] in DeadPlains so card 22 fails, then card 31 should be placed

        $this->game->tokens->db->moveToken("card_monster_22", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_22", 999); // top
        $this->game->tokens->db->moveToken("card_monster_31", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_31", 998); // second

        // Occupy first hex of DeadPlains
        $deadPlainsHexes = $this->game->getHexesInLocation("DeadPlains");
        $this->game->tokens->db->moveToken("hero_1", $deadPlainsHexes[0]);

        $this->game->setPlayersNumber(1);
        $op = $this->createReinforcementOp(["deck" => "deck_monster_yellow"]);
        $op->resolve();

        // Card 22 skipped, card 31's monsters placed in OgreValley
        $card22State = $this->game->tokens->db->getTokenState("card_monster_22");
        $this->assertEquals(1, $card22State); // skipped

        $ogreValleyHexes = $this->game->getHexesInLocation("OgreValley");
        $monsterCount = 0;
        foreach ($ogreValleyHexes as $hex) {
            $tokens = $this->game->tokens->getTokensOfTypeInLocation("monster", $hex);
            $monsterCount += count($tokens);
        }
        $this->assertEquals(3, $monsterCount);
    }

    public function testCardGoesToDisplay(): void {
        // Card 36 "Viral Trolls": T,T,T
        $this->game->tokens->db->moveToken("card_monster_36", "deck_monster_yellow");
        $this->game->tokens->db->setTokenState("card_monster_36", 999);

        $this->game->setPlayersNumber(1);
        $op = $this->createReinforcementOp(["deck" => "deck_monster_yellow"]);
        $op->resolve();

        // Card should now be on display_monsterturn
        $loc = $this->game->tokens->db->getTokenLocation("card_monster_36");
        $this->assertEquals("display_monsterturn", $loc);
    }

    public function testCleanupMovesCardToBottomOfDeck(): void {
        // Place a card on display as if reinforcement just happened
        $this->game->tokens->db->moveToken("card_monster_36", "display_monsterturn");

        // Run turnMonster — cleanup should move card back to bottom of deck
        $this->game->tokens->db->setTokenState("rune_stone", 0);
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $loc = $this->game->tokens->db->getTokenLocation("card_monster_36");
        $this->assertEquals("deck_monster_yellow", $loc);
        // Should be at the bottom (below min of other cards)
        $state = $this->game->tokens->db->getTokenState("card_monster_36");
        $this->assertLessThan(0, $state);
    }

    // -------------------------------------------------------------------------
    // Time track triggers
    // -------------------------------------------------------------------------

    public function testReinforcementTriggeredOnYellowAxesStep(): void {
        // Step 1 = tm_yellow_axes → should queue reinforcement
        $this->game->tokens->db->setTokenState("rune_stone", 0); // will advance to 1
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // Check that reinforcement was queued
        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("reinforcement", $top->getType());
    }

    public function testReinforcementTriggeredOnRedAxesStep(): void {
        // Step 7 = tm_red_axes → should queue reinforcement with red deck
        $this->game->tokens->db->setTokenState("rune_stone", 6); // will advance to 7
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertEquals("reinforcement", $top->getType());
        $data = $top->getData();
        $this->assertEquals("deck_monster_red", $data["deck"]);
    }

    public function testNoReinforcementOnShieldStep(): void {
        // Step 2 = tm_yellow_shield → no reinforcement
        $this->game->tokens->db->setTokenState("rune_stone", 1); // will advance to 2
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        // Top op should be "turn" (next round), not "reinforcement"
        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertNotEquals("reinforcement", $top->getType());
    }

    public function testNoReinforcementOnSkullStep(): void {
        // Step 9 = tm_red_skull → no reinforcement (charge only, not implemented yet)
        $this->game->tokens->db->setTokenState("rune_stone", 8); // will advance to 9
        $op = $this->createTurnMonsterOp();
        $op->resolve();

        $top = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertNotNull($top);
        $this->assertNotEquals("reinforcement", $top->getType());
    }

    // -------------------------------------------------------------------------
    // getHexesInLocation
    // -------------------------------------------------------------------------

    public function testGetHexesInLocationDarkForest(): void {
        $hexes = $this->game->getHexesInLocation("DarkForest");
        $this->assertNotEmpty($hexes);
        // All returned hexes should have loc=DarkForest
        foreach ($hexes as $hex) {
            $this->assertEquals("DarkForest", $this->game->getHexNamedLocation($hex));
        }
        // Should be sorted top-to-bottom, left-to-right
        for ($i = 1; $i < count($hexes); $i++) {
            [$ax, $ay] = $this->game->getHexCoords($hexes[$i - 1]);
            [$bx, $by] = $this->game->getHexCoords($hexes[$i]);
            $this->assertTrue($ay < $by || ($ay === $by && $ax <= $bx), "Hexes not sorted: {$hexes[$i - 1]} vs {$hexes[$i]}");
        }
    }

    public function testGetHexesInLocationEmpty(): void {
        $hexes = $this->game->getHexesInLocation("NonExistentLocation");
        $this->assertEmpty($hexes);
    }
}
