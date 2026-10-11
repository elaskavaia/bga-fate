<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Every statistic in stats.jsonc must move exactly when the effect it names happens, and stay put
 * otherwise. Undo must take stats back together with the board.
 */
class Campaign_StatsTest extends CampaignBaseTest {
    private string $color;
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1", "card_event_1_27_2"]);
    }

    public function testAKillCountsForTheKillerAndTheTeam(): void {
        $goblin = "monster_goblin_20";
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $this->game->getMonster($goblin)->moveTo("hex_5_8", "");
        $this->seedRand([5, 5, 5]);

        $this->respond("hex_5_8");
        $this->skipUseCard("card_hero_1_1"); // decline Bjorn's +2 damage after the roll

        $this->assertSame("supply_monster", $this->tokenLocation($goblin), "sanity: the goblin died");
        $this->assertSame(1, $this->stat("game_monsters_killed"));
        $this->assertSame(1, $this->tableStat("game_monsters_killed"), "the kill also counts toward the team total");
        $this->assertSame(0, $this->stat("game_legends_killed"), "a goblin is not a legend");
        $this->assertSame(3, $this->stat("game_damage_dealt"), "all three hits count, overkill included");
        $this->assertSame(1, $this->stat("game_xp_gained"), "a goblin is worth 1 XP");
        $this->assertSame(0, $this->stat("game_damage_taken"));
    }

    public function testXpFromPracticeCounts(): void {
        $xpBefore = $this->countXp();

        $this->respond("actionPractice");

        $this->assertSame($xpBefore + 1, $this->countXp(), "sanity: Practice gains 1 XP");
        $this->assertSame(1, $this->stat("game_xp_gained"));
    }

    public function testXpFromMiningCounts(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_5_8"); // adjacent to 1 mountain
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->game->machine->instantiateOperation("c_mining", $this->color);
        $op->action_resolve(["target" => "confirm"]);

        $this->assertSame(2, $this->stat("game_xp_gained"), "one adjacent mountain mines 2 gold");
    }

    public function testALegendKillAlsoCountsAsALegend(): void {
        $legend = "monster_legend_3_1"; // Grendel (I)
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $this->game->getMonster($legend)->moveTo("hex_5_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->game->machine->instantiateOperation("killMonster", $this->color);
        $op->action_resolve(["target" => "hex_5_8"]);
        $this->game->machine->dispatchAll();

        $this->assertSame("supply_monster", $this->tokenLocation($legend), "sanity: Grendel died");
        $this->assertSame(1, $this->stat("game_monsters_killed"));
        $this->assertSame(1, $this->stat("game_legends_killed"));
    }

    public function testAMonsterAttackCountsDamageTakenAndTheKnockout(): void {
        $this->game->effect_moveCrystals($this->heroId, "red", 8, $this->heroId, ["message" => ""]);

        $this->attackBjornWithABrute();

        $this->assertSame(3, $this->stat("game_damage_taken"), "only the attack counts, not damage placed by the test");
        $this->assertSame(1, $this->stat("game_knockouts"));
        $this->assertSame(0, $this->stat("game_grimheim_visits"), "being carried home is not a visit");
        $this->assertSame(0, $this->stat("game_moves"), "nor a move");
        $this->assertSame(0, $this->stat("game_damage_dealt"), "a monster's damage is credited to no player");
        $this->assertSame(0, $this->tableStat("game_monsters_killed"), "a knocked out hero is not a monster kill");
    }

    public function testEachMainActionTakenCounts(): void {
        $this->respond("hex_7_8");
        $this->respond("actionFocus");

        $this->assertSame(1, $this->stat("game_actionMove"));
        $this->assertSame(1, $this->stat("game_actionFocus"));
        foreach (["actionAttack", "actionPrepare", "actionMend", "actionPractice"] as $action) {
            $this->assertSame(0, $this->stat("game_$action"), "$action was not taken");
        }
    }

    public function testLeavingGrimheimIsNotAVisit(): void {
        $this->respond("hex_7_8");

        $this->assertSame("hex_7_8", $this->tokenLocation($this->heroId), "sanity: Bjorn left");
        $this->assertSame(0, $this->stat("game_grimheim_visits"));
    }

    public function testBeingPushedIntoGrimheimIsNotTheHerosMove(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_8_8"); // next hex toward Grimheim is hex_9_8
        $this->game->getMonster("monster_goblin_20")->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->game->machine->instantiateOperation("monsterDiePush", $this->game->getAutomaColor())->resolve();

        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)), "sanity: pushed into Grimheim");
        $this->assertSame(0, $this->stat("game_grimheim_visits"));
        $this->assertSame(0, $this->stat("game_moves"));
    }

    public function testEveryAreaMovedCountsAsOneMove(): void {
        $start = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $start);

        $this->respond("hex_5_9");

        $this->assertSame("hex_5_9", $this->tokenLocation($this->heroId), "sanity: Bjorn arrived");
        $this->assertGreaterThan(1, $this->game->hexMap->getHexDistance($start, "hex_5_9"), "sanity: a multi-area move");
        $this->assertSame($this->game->hexMap->getHexDistance($start, "hex_5_9"), $this->stat("game_moves"));
    }

    public function testAMoveEndingInGrimheimIsAVisit(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_7_8");

        $this->respond("hex_8_9");

        $this->assertTrue($this->game->hexMap->isInGrimheim($this->tokenLocation($this->heroId)), "sanity: Bjorn is home");
        $this->assertSame(1, $this->stat("game_grimheim_visits"));
    }

    public function testCompletingAQuestCounts(): void {
        $bow = "card_equip_1_16"; // Bone Bane Bow: in(Nailfare):spendAction(actionMend):gainEquip
        $this->seedDeck("deck_equip_$this->color", [$bow]);
        $this->game->tokens->moveToken($this->heroId, "hex_16_5"); // Nailfare

        $this->respond("completeQuest");
        $this->respond($bow);
        $this->confirmCardEffect();

        $this->assertSame("tableau_$this->color", $this->tokenLocation($bow), "sanity: the quest reward landed");
        $this->assertSame(1, $this->stat("game_quests_completed"));
        $this->assertSame(0, $this->stat("game_actionMend"), "paying a Mend action for the quest is not taking it");
    }

    public function testDecliningTheUpgradeCountsNothing(): void {
        $this->game->effect_moveCrystals($this->heroId, "yellow", 5, "tableau_$this->color", ["message" => ""]);

        $this->driveToTheUpgrade();
        $this->skip();

        $this->assertUpgradeStats(0, 0, 0);
    }

    public function testEachKindOfUpgradeCountsSeparately(): void {
        $startingAbility = array_key_first($this->game->tokens->getTokensOfTypeInLocation("card_ability", "tableau_$this->color"));
        $this->seedDeck("deck_ability_$this->color", ["card_ability_1_9"]);

        $this->upgradeAtEndOfTurn("card_hero_1_1");
        $this->assertUpgradeStats(1, 0, 0);

        $this->upgradeAtEndOfTurn("card_ability_1_9");
        $this->assertUpgradeStats(1, 1, 0);

        $this->upgradeAtEndOfTurn($startingAbility);
        $this->assertUpgradeStats(1, 1, 1);
    }

    public function testPlayingAnEventCounts(): void {
        $rest = "card_event_1_27_1";
        $this->seedHand($rest, $this->color);
        $this->game->effect_moveCrystals($this->heroId, "red", 3, $this->heroId, ["message" => ""]);

        $this->respond($rest);
        $this->confirmCardEffect();

        $this->assertSame(1, $this->countDamage($this->heroId), "sanity: Rest healed 2");
        $this->assertSame(1, $this->stat("game_events_played"));
        $this->assertSame(2, $this->stat("game_damage_healed"));
    }

    public function testHealingCountsOnlyTheDamageActuallyRemoved(): void {
        $rest = "card_event_1_27_1"; // heals 2
        $this->seedHand($rest, $this->color);
        $this->game->effect_moveCrystals($this->heroId, "red", 1, $this->heroId, ["message" => ""]);

        $this->respond($rest);
        $this->confirmCardEffect();

        $this->assertSame(0, $this->countDamage($this->heroId), "sanity: fully healed");
        $this->assertSame(1, $this->stat("game_damage_healed"), "healing past full health is not counted");
    }

    public function testAMonsterReachingGrimheimCountsForTheTeam(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_5_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_8_8", ""); // adjacent to Grimheim hex_9_8

        $this->driveOneMonsterTurn();

        $this->assertSame("supply_monster", $this->tokenLocation($goblin), "sanity: the goblin went into Grimheim");
        $this->assertSame(1, $this->tableStat("game_monsters_reached_grimheim"));
        $this->assertSame(0, $this->tableStat("game_rune_stone_position"), "set only when the game ends");
    }

    public function testCompletingTheTimeTrackSetsTheEndStats(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 11); // step 12 (last) after the advance

        $this->driveOneMonsterTurn();

        $this->assertTrue($this->game->isEndOfGame(), "sanity: the track is complete");
        $this->assertSame(1, $this->tableStat("game_heroes_won"));
        $this->assertSame(12, $this->tableStat("game_rune_stone_position"), "the last step of the short track");
        $this->assertSame($this->game->getHousesRemainingCount(), $this->tableStat("game_houses_remaining"));
        $this->assertGreaterThan(0, $this->tableStat("game_houses_remaining"));
    }

    /** A real monster turn calls handleEndOfGame twice (knockout, then end of monster turn), so it may only set stats. */
    public function testLosingTheWellSetsTheEndStatsOnce(): void {
        $houses = array_values(array_diff(array_keys($this->game->tokens->getTokensOfTypeInLocation("house", "hex%")), ["house_0"]));
        foreach (array_slice($houses, 1) as $house) {
            $this->game->tokens->moveToken($house, "limbo");
        }
        $this->game->effect_moveCrystals($this->heroId, "red", 8, $this->heroId, ["message" => ""]);

        $this->attackBjornWithABrute(); // the knockout destroys the last house and the Well

        $this->assertTrue($this->game->isWellDestroyed(), "sanity: the Well is gone");
        $this->assertSame(0, $this->tableStat("game_heroes_won"));
        $this->assertSame(0, $this->tableStat("game_houses_remaining"));
        $this->assertSame(1, $this->tableStat("game_rune_stone_position"), "lost in the first round");
        $allStats = serialize([$this->game->tableStats, $this->game->playerStats]);

        $this->game->handleEndOfGame();

        $this->assertSame($allStats, serialize([$this->game->tableStats, $this->game->playerStats]), "a second call changes no stat");
    }

    /** The roll is an undo barrier: the kill after it can be undone, and its stats go with it. */
    public function testUndoTakesTheStatsBackWithTheBoard(): void {
        $this->moveHeroOutOfGrimheim();
        [$monster] = $this->spawnMonsterAdjacent("goblin");
        $monsterHex = $this->tokenLocation($monster);
        $this->seedRand([5, 5, 5]);

        $this->respond("actionAttack");
        $this->respond($monsterHex);
        $this->assertSame(1, $this->stat("game_monsters_killed"), "sanity: the kill counted");

        $this->undo();

        $this->assertSame($monsterHex, $this->tokenLocation($monster), "sanity: the goblin is back");
        $this->assertSame(0, $this->stat("game_monsters_killed"));
        $this->assertSame(0, $this->stat("game_damage_dealt"));
        $this->assertSame(0, $this->tableStat("game_monsters_killed"));

        $this->respond($monsterHex);

        $this->assertSame(1, $this->stat("game_monsters_killed"), "redoing the kill counts it once");
        $this->assertSame(1, $this->tableStat("game_monsters_killed"));
    }

    private function stat(string $name): int {
        return (int) $this->game->playerStats->get($name, $this->game->custom_getPlayerIdByColor($this->color));
    }

    private function tableStat(string $name): int {
        return (int) $this->game->tableStats->get($name);
    }

    /** Bjorn at hex_7_9 takes 3 hits from a brute (strength 3), the way Op_turnMonster queues it. */
    private function attackBjornWithABrute(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_7_8", "");
        $this->seedRand([5, 5, 5]);
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->game->machine->instantiateOperation("monsterAttack", $this->game->getAutomaColor(), [
            "char" => "monster_brute_1",
            "target" => "hex_7_9",
        ]);
        $op->resolve();
        $this->game->machine->dispatchAll();
    }

    private function driveOneMonsterTurn(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
    }

    private function upgradeAtEndOfTurn(string $target): void {
        $this->game->effect_moveCrystals($this->heroId, "yellow", 10, "tableau_$this->color", ["message" => ""]);
        $this->driveToTheUpgrade();
        $this->respond($target);
        $this->skipIfOp("drawEvent");
    }

    private function assertUpgradeStats(int $hero, int $gained, int $upgraded): void {
        $this->assertSame($hero, $this->stat("game_hero_upgraded"), "hero upgraded");
        $this->assertSame($gained, $this->stat("game_abilities_gained"), "abilities gained");
        $this->assertSame($upgraded, $this->stat("game_abilities_upgraded"), "abilities upgraded");
    }

    private function driveToTheUpgrade(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->assertOperation("upgrade");
    }

    private function undo(): void {
        $this->game->_setCurrentPlayerId((int) $this->game->getActivePlayerId());
        $this->driver->runStep("action_undo", ["move_id" => 0]);
        for ($i = 0; $i < 5 && ($this->getOpArgs()["type"] ?? "") === ""; $i++) {
            $this->driver->runDispatchLoop();
        }
        $this->game->_setCurrentPlayerId((int) $this->game->getActivePlayerId());
    }
}
