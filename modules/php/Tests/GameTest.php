<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Tests;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\Notify;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Common\PGameTokens;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\States\GameDispatch;
use PHPUnit\Framework\TestCase;

use function Bga\Games\Fate\array_get;
use function Bga\Games\Fate\getPart;
use function Bga\Games\Fate\startsWith;
use function Bga\Games\Fate\toJson;

//       "player_colors" => ["ff0000", "ffcc02", "6cd0f6", "982fff"],
define("PCOLOR", "6cd0f6");
define("BCOLOR", "982fff");
define("CCOLOR", "ff0000");
define("ACOLOR", "ffffff"); // automa
define("PCOLOR_ID", 10);

class FakeNotify extends Notify {
    public function all(string $notifName, string|NotificationMessage $message = "", array $args = []): void {
        //echo "Notify all: $notifName : $message\n";
    }
    public function player(int $playerId, string $notifName, string|NotificationMessage $message = "", array $args = []): void {
        //echo "Notify player $playerId: $notifName : $message\n";
    }
}

class GameUT extends Game {
    var $multimachine;
    var $xtable;
    var $gameap_number = 0;
    var $var_colonies = 0;
    var $_colors = [];

    function __construct() {
        parent::__construct();
        //$this->gamestate = new GameStateInMem();

        //$this->tokens = new TokensInMem($this);
        $this->xtable = [];
        $this->machine = new OpMachine(new MachineInMem($this, $this->xtable));
        $this->curid = 1;
        $this->_colors = [PCOLOR, BCOLOR];
        $this->notify = new FakeNotify();

        $tokens = new TokensInMem($this);
        $this->tokens = new PGameTokens($this, $tokens);
    }

    function setPlayersNumber(int $num) {
        switch ($num) {
            case 1:
                $this->_colors = [PCOLOR];
                break;
            case 2:
                $this->_colors = [PCOLOR, BCOLOR];
                break;
            case 3:
                $this->_colors = [PCOLOR, BCOLOR, CCOLOR];
                break;
            case 4:
                $this->_colors = [PCOLOR, BCOLOR, CCOLOR, "ef58a2"];
                break;
            default:
                throw new UserException("Invalid number of players");
        }
    }

    function getUserPreference(int $player_id, int $code): int {
        return 0;
    }

    function init(int $x = 0) {
        //$this->adjustedMaterial(true);
        //$this->createTokens();
        $this->gamestate->changeActivePlayer(10);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        return $this;
    }

    function clean_cache() {}

    function getMultiMachine() {
        return $this->multimachine;
    }

    public $curid;

    function _getCurrentPlayerId() {
        return $this->curid;
    }

    function _getColors() {
        return $this->_colors;
    }

    function fakeUserAction(Operation $op, $target = null) {
        return $op->action_resolve([Operation::ARG_TARGET => $target]);
    }

    public function setupGameTables() {
        return parent::setupGameTables();
    }

    // override/stub methods here that access db and stuff
}

final class GameTest extends TestCase {
    private GameUT $game;
    function dispatchOneStep($done = null) {
        $game = $this->game;
        $state = $game->machine->dispatchOne();
        if ($state === null) {
            $state = GameDispatch::class;
        }
        if ($done !== null) {
            $this->assertEquals($done, $state);
        }
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        return $op;
    }
    function dispatch($done = null) {
        $game = $this->game;
        $state = $game->machine->dispatchAll();
        if ($state === null) {
            $state = GameDispatch::class;
        }
        if ($done !== null) {
            $this->assertEquals($done, $state);
        }
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        return $op;
    }
    function game(int $x = 0) {
        $game = new GameUT();
        $game->init($x);
        $this->game = $game;
        return $game;
    }

    protected function setUp(): void {
        $this->game();
    }
    public function testGetAdjacentHexes() {
        $game = $this->game;

        // Center hex (9,9) should have 6 neighbors
        $adj = $game->hexMap->getAdjacentHexes("hex_9_9");
        $this->assertCount(6, $adj);
        sort($adj);
        $this->assertEquals(["hex_10_8", "hex_10_9", "hex_8_10", "hex_8_9", "hex_9_10", "hex_9_8"], $adj);

        // Edge hex should have fewer neighbors
        $adj = $game->hexMap->getAdjacentHexes("hex_1_9");
        $this->assertLessThan(6, count($adj));
        $this->assertContains("hex_2_9", $adj);
        $this->assertNotContains("hex_0_9", $adj); // off the board

        // Non-existent hex returns empty
        $this->assertEmpty($game->hexMap->getAdjacentHexes("hex_99_99"));
    }

    public function testGetMoveDistance() {
        $game = $this->game;

        // Same hex
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_9_9"));
        // Grimheim
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_10_9"));
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_9_8"));
        // Two steps
        $this->assertEquals(2, $game->hexMap->getMoveDistance("hex_11_6", "hex_11_8"));
        // Invalid
        $this->assertEquals(-1, $game->hexMap->getMoveDistance("hex_9_9", "hex_99_99"));
        $this->assertEquals(-1, $game->hexMap->getMoveDistance("hex_99_99", "hex_9_9"));

        // Grimheim hexes: distance 0 between any two Grimheim hexes
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_8_10")); // both Grimheim
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_8_9", "hex_10_8")); // both Grimheim, far apart

        // Distance from Grimheim to adjacent non-Grimheim hex
        $this->assertEquals(1, $game->hexMap->getMoveDistance("hex_9_9", "hex_11_8")); // hex_11_8 is adjacent to hex_10_8 (Grimheim)
        $this->assertEquals(1, $game->hexMap->getMoveDistance("hex_11_8", "hex_8_9")); // symmetric
    }

    public function testGetReachableHexes() {
        $game = $this->game;

        // From a Grimheim hex, all Grimheim hexes are at distance 0
        $reachable = $game->hexMap->getReachableHexes("hex_9_9", 3);
        $this->assertArrayHasKey("hex_9_8", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_10_8", $reachable); // Grimheim
        $this->assertEquals(0, $reachable["hex_9_8"]);

        // Adjacent non-Grimheim hex at distance 1
        $this->assertArrayHasKey("hex_11_8", $reachable);
        $this->assertEquals(1, $reachable["hex_11_8"]);

        // Start hex excluded
        $this->assertArrayNotHasKey("hex_9_9", $reachable);

        // Mountain hexes not reachable for heroes
        $this->assertArrayNotHasKey("hex_9_11", $reachable); // mountain

        // Far hexes not reachable
        $this->assertArrayNotHasKey("hex_9_1", $reachable);
    }

    public function testGetReachableHexesMountainForMonster() {
        $game = $this->game;

        // hex_13_4 is mountain — not reachable by hero
        $reachable = $game->hexMap->getReachableHexes("hex_12_4", 1, "hero");
        $this->assertArrayNotHasKey("hex_13_4", $reachable);

        // But reachable by monster
        $reachable = $game->hexMap->getReachableHexes("hex_12_4", 1, "monster");
        $this->assertArrayHasKey("hex_13_4", $reachable);
    }

    public function testGetReachableHexesBlockedByOccupied() {
        $game = $this->game;
        $game->tokens->createTokens();

        // Place a hero on hex_11_8
        $game->tokens->db->moveToken("hero_1", "hex_11_8");

        // hex_11_8 should not be reachable (occupied)
        $reachable = $game->hexMap->getReachableHexes("hex_10_8", 3);
        $this->assertArrayNotHasKey("hex_11_8", $reachable);

        // Hexes behind the occupied one should still be reachable via other paths
        $this->assertArrayHasKey("hex_12_8", $reachable);
    }

    public function testEnteringGrimheimEndsMovement() {
        $game = $this->game;

        // From hex_11_8 (adjacent to Grimheim hex_10_8), entering Grimheim ends movement
        $reachable = $game->hexMap->getReachableHexes("hex_11_8", 3);

        // All Grimheim hexes should be reachable
        $this->assertArrayHasKey("hex_10_8", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_9_9", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_8_9", $reachable); // Grimheim

        // But hexes on the other side of Grimheim should NOT be reachable
        // because entering Grimheim ends movement
        $this->assertArrayNotHasKey("hex_7_9", $reachable); // beyond Grimheim
        $this->assertArrayNotHasKey("hex_7_10", $reachable); // beyond Grimheim
    }

    public function testExitingGrimheimCostsOneStep() {
        $game = $this->game;

        // From Grimheim, adjacent non-mountain hexes should be at distance 1
        $reachable = $game->hexMap->getReachableHexes("hex_9_9", 3);

        // hex_11_8 is adjacent to Grimheim border hex hex_10_8
        $this->assertArrayHasKey("hex_11_8", $reachable);
        $this->assertEquals(1, $reachable["hex_11_8"]);

        // hex_7_9 is adjacent to Grimheim border hex hex_8_9
        $this->assertArrayHasKey("hex_7_9", $reachable);
        $this->assertEquals(1, $reachable["hex_7_9"]);
    }

    public function testSetupCreatesHeroCardsInDecks() {
        $game = $this->game;
        $game->setupGameTables();

        // Hero 1 assigned to player 1 (PCOLOR), hero 2 to player 2 (BCOLOR)
        // Starting cards should be on tableau
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->db->getTokenLocation("card_hero_1_1"));
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->db->getTokenLocation("card_ability_1_3"));
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->db->getTokenLocation("card_equip_1_15"));

        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->db->getTokenLocation("card_hero_2_1"));
        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->db->getTokenLocation("card_ability_2_3"));
        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->db->getTokenLocation("card_equip_2_15"));
    }

    public function testSetupHeroCardsInCorrectDecks() {
        $game = $this->game;
        $game->setupGameTables();

        // Hero 1 Level II hero card should be in limbo (not a starting card)
        $this->assertEquals("limbo", $game->tokens->db->getTokenLocation("card_hero_1_2"));

        // Non-starting ability cards should be in ability deck
        $this->assertEquals("deck_ability_" . PCOLOR, $game->tokens->db->getTokenLocation("card_ability_1_9"));

        // Non-starting equip cards should be in equip deck
        $this->assertEquals("deck_equip_" . PCOLOR, $game->tokens->db->getTokenLocation("card_equip_1_20"));

        // Event cards (indexed) should be in event deck
        $this->assertEquals("deck_event_" . PCOLOR, $game->tokens->db->getTokenLocation("card_event_1_28_1"));
        $this->assertEquals("deck_event_" . PCOLOR, $game->tokens->db->getTokenLocation("card_event_1_28_2"));
    }

    public function testSetupUnusedHeroesInLimbo() {
        $game = $this->game;
        $game->setupGameTables();

        // 2 players: heroes 3 and 4 should be in limbo
        $this->assertEquals("limbo", $game->tokens->db->getTokenLocation("hero_3"));
        $this->assertEquals("limbo", $game->tokens->db->getTokenLocation("hero_4"));

        // No cards created for unused heroes
        $this->assertNull($game->tokens->db->getTokenInfo("card_hero_3_1"));
        $this->assertNull($game->tokens->db->getTokenInfo("card_hero_4_1"));
    }

    public function testSetupEventCardDuplicates() {
        $game = $this->game;
        $game->setupGameTables();

        // Burning Arrows (num=28) has count=4, should create 4 indexed tokens
        for ($i = 1; $i <= 4; $i++) {
            $loc = $game->tokens->db->getTokenLocation("card_event_1_28_{$i}");
            $this->assertNotNull($loc, "card_event_1_28_{$i} should exist");
            $this->assertEquals("deck_event_" . PCOLOR, $loc);
        }
        // 5th copy should not exist
        $this->assertNull($game->tokens->db->getTokenInfo("card_event_1_28_5"));
    }

    public function testInstanciateAllOperations() {
        $this->game();
        $this->game->setupGameTables();
        $this->game->tokens->db->moveToken("hero_1", "hex_9_9");
        $token_types = $this->game->material->get();
        $tested = [];
        foreach ($token_types as $key => $info) {
            $this->assertTrue(!!$key);
            if (!startsWith($key, "Op_")) {
                continue;
            }
            echo "testing op $key\n";
            $this->subTestOp($key, $info);
            $tested[$key] = 1;
        }

        $dir = dirname(dirname(__FILE__));
        $files = glob("$dir/Operations/*.php");

        foreach ($files as $file) {
            $base = basename($file);
            $this->assertTrue(!!$base);
            if (!startsWith($base, "Op_")) {
                continue;
            }
            $mne = preg_replace("/Op_(.*).php/", "\\1", $base);
            $key = "Op_{$mne}";
            if (str_contains($key, "Base")) {
                continue;
            }
            if (array_key_exists($key, $tested)) {
                continue;
            }
            echo "testing op $key\n";
            $this->subTestOp($key, ["type" => $mne]);
        }
    }

    function subTestOp($key, $info = []) {
        $type = substr($key, 3);
        $this->assertTrue(!!$type);
        if ($info["notimpl"] ?? false) {
            return;
        }

        /** @var Operation */
        $op = $this->game->machine->instanciateOperation($type, PCOLOR);

        $args = $op->getArgs();
        $ttype = array_get($args, "ttype");
        $this->assertTrue($ttype != "", "empty ttype for $key");

        $this->assertFalse(str_contains($op->getOpName(), "?"), $op->getOpName());
        $this->assertFalse($op->getOpName() == $op->getType(), "No name set for operation $key");
        return $op;
    }
}
