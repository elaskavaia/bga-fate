<?php

declare(strict_types=1);

namespace Bga\Games\Fate;

class Material {
    const RET_OK = 0;
    const ERR_COST = 1;
    const ERR_PREREQ = 2;
    const ERR_OCCUPIED = 3;
    const ERR_MAX = 4;
    const ERR_NONE_LEFT = 5;
    const ERR_NOT_APPLICABLE = 6;
    const ERR_NO_PLACE = 7;

    const MA_PREF_CONFIRM_TURN = 101;

    const TIME_TRACK_SHORT_LENGTH = 12;

    const SPAWN_FACTION = [
        "DarkForest" => "trollkin",
        "OgreValley" => "trollkin",
        "TrollCaves" => "trollkin",
        "Highlands" => "firehorde",
        "SpewingMountain" => "firehorde",
        "TempleRuins" => "firehorde",
        "WyrmLair" => "firehorde",
        "DeadPlains" => "dead",
        "MarshOfSorrow" => "dead",
        "Nailfare" => "dead",
    ];

    private array $token_types;
    private bool $adjusted = false;
    public function __construct() {
        $this->token_types = [
            // #error codes - MANUAL ENTRY
            "err_0" => [
                //
                "code" => Material::RET_OK,
                "type" => "err",
                "name" => clienttranslate("Ok"),
            ],
            "err_1" => [
                //
                "code" => Material::ERR_COST,
                "type" => "err",
                "name" => clienttranslate("Insufficient Resources"),
            ],

            "err_2" => [
                //
                "code" => Material::ERR_PREREQ,
                "type" => "err",
                "name" => clienttranslate("Prerequisites are not fulfilled"),
            ],
            "err_3" => [
                //
                "code" => Material::ERR_OCCUPIED,
                "type" => "err",
                "name" => clienttranslate("Location is occupied"),
            ],
            "err_4" => [
                //
                "code" => Material::ERR_MAX,
                "type" => "err",
                "name" => clienttranslate("Too far"),
            ],
            "err_5" => [
                //
                "code" => Material::ERR_NONE_LEFT,
                "type" => "err",
                "name" => clienttranslate("None left"),
            ],

            "err_6" => [
                //
                "code" => Material::ERR_NOT_APPLICABLE,
                "type" => "err",
                "name" => clienttranslate("Not applicable"),
            ],

            "err_7" => [
                //
                "code" => Material::ERR_NO_PLACE,
                "type" => "err",
                "name" => clienttranslate("Not valid placement"),
            ],
        ];
        $this->addGeneratedTokens();
    }

    public function get(): array {
        return $this->token_types;
    }

    public function getTokensWithPrefix(string $prefix) {
        $tokenTypes = $this->get();
        $res = [];
        foreach ($tokenTypes as $key => $info) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $res[$key] = $info;
        }
        return $res;
    }

    /**
     * This has to be called from "initTable" method of game which is when db is conected but action is not started yet
     */
    public function adjustMaterial(Game $game) {
        if ($this->adjusted) {
            return $this->token_types;
        }
        $this->adjusted = true;
        // ... do something reading number or palyer of game options with material

        return $this->token_types;
    }

    function getRulesFor($token_id, $field = "r", $default = "") {
        $tt = $this->token_types;
        $key = $token_id;
        while ($key) {
            $data = $tt[$key] ?? null;
            if ($data) {
                if ($field === "*") {
                    $data["_key"] = $key;
                    return $data;
                }
                $value = $data[$field] ?? null;
                if ($value !== null) {
                    return $value;
                }
            }
            $new_key = $this->getPartsPrefix($key, -1);
            if ($new_key == $key) {
                break;
            }
            $key = $new_key;
        }
        //$this->systemAssertTrue("bad token $token_id for rule $field", false);
        return $default;
    }

    /** Find stuff in material file */
    function find(string $field, ?string $value, bool $ignorecase = true) {
        foreach ($this->token_types as $key => $rules) {
            $cur = $rules[$field] ?? null;
            if ($cur == $value) {
                return $key;
            }
            if ($ignorecase && is_string($cur) && strcasecmp($cur, $value) == 0) {
                return $key;
            }
        }
        return null;
    }
    function findByName(string $value, bool $ignorecase = true) {
        return $this->find("name", $value, $ignorecase);
    }

    /**
     * Return $i parts of string (part is chunk separated by _
     * I.e.
     * getPartsPrefix("a_b_c",2)=="a_b"
     *
     * If $i is negative - it will means how much remove from tail, i.e
     * getPartsPrefix("a_b_c",-1)=="a_b"
     */
    static function getPartsPrefix($haystack, $i) {
        $parts = explode("_", $haystack);
        $len = count($parts);
        if ($i < 0) {
            $i = $len + $i;
        }
        if ($i <= 0) {
            return "";
        }
        for (; $i < $len; $i++) {
            unset($parts[$i]);
        }
        return implode("_", $parts);
    }

    /**
     * Set rules for a token (used for testing)
     */
    function setRulesFor(string $token_id, array $rules): void {
        if (!isset($this->token_types[$token_id])) {
            $this->token_types[$token_id] = [];
        }
        $this->token_types[$token_id] = array_merge($this->token_types[$token_id], $rules);
    }

    function addGeneratedTokens() {
        $this->token_types += [
            /* --- gen php begin op_material --- */
    "Op_nop" => [ 
        "type" => "nop",
        "name" => clienttranslate("None"),
],
    "Op_savepoint" => [ 
        "type" => "savepoint",
        "name" => clienttranslate("None"),
],
    "Op_or" => [ 
        "type" => "or",
        "name" => clienttranslate("Choice"),
],
    "Op_order" => [ 
        "type" => "order",
        "name" => clienttranslate("Choose Order"),
],
    "Op_seq" => [ 
        "type" => "seq",
        "name" => clienttranslate("Sequence"),
],
    "Op_gain" => [ 
        "type" => "gain",
        "name" => clienttranslate("Gain"),
],
    "Op_pay" => [ 
        "type" => "pay",
        "name" => clienttranslate("Pay"),
],
    "Op_paygain" => [ 
        "type" => "paygain",
        "name" => clienttranslate("Trade"),
],
    "Op_turn" => [ 
        "type" => "turn",
        "name" => clienttranslate("Turn"),
],
    "Op_turnconf" => [ 
        "type" => "turnconf",
        "name" => clienttranslate("Confirm Turn"),
],
// # End of turn
    "Op_turnEnd" => [ 
        "type" => "turnEnd",
        "name" => clienttranslate("End of Turn"),
],
// # Monster turn (runs after all players have taken their turn)
    "Op_turnMonster" => [ 
        "type" => "turnMonster",
        "name" => clienttranslate("Monster Turn"),
],
// # Monster reinforcement (auto: draw monster cards, place monsters)
    "Op_reinforcement" => [ 
        "type" => "reinforcement",
        "name" => clienttranslate("Reinforcement"),
],
// # Main actions (2 per turn, cannot repeat)
    "Op_actionMove" => [ 
        "kind" => "main",
        "type" => "actionMove",
        "name" => clienttranslate("Move"),
],
    "Op_actionAttack" => [ 
        "kind" => "main",
        "type" => "actionAttack",
        "name" => clienttranslate("Attack"),
        "notimpl"=>true,
],
    "Op_actionPrepare" => [ 
        "kind" => "main",
        "type" => "actionPrepare",
        "name" => clienttranslate("Prepare"),
        "notimpl"=>true,
],
    "Op_actionFocus" => [ 
        "kind" => "main",
        "type" => "actionFocus",
        "name" => clienttranslate("Focus"),
        "notimpl"=>true,
],
    "Op_actionMend" => [ 
        "kind" => "main",
        "type" => "actionMend",
        "name" => clienttranslate("Mend"),
        "notimpl"=>true,
],
    "Op_actionPractice" => [ 
        "kind" => "main",
        "type" => "actionPractice",
        "name" => clienttranslate("Practice"),
],
// # Free actions (can be done between/after main actions)
    "Op_useEquipment" => [ 
        "kind" => "free",
        "type" => "useEquipment",
        "name" => clienttranslate("Use Equipment"),
        "notimpl"=>true,
],
    "Op_useAbility" => [ 
        "kind" => "free",
        "type" => "useAbility",
        "name" => clienttranslate("Use Ability"),
        "notimpl"=>true,
],
    "Op_playEvent" => [ 
        "kind" => "free",
        "type" => "playEvent",
        "name" => clienttranslate("Play Event"),
        "notimpl"=>true,
],
            /* --- gen php end op_material --- */

            /* --- gen php begin token_material --- */
// # create is one of the numbers
// # 0 - do not create token
// # 1 - the token with id $id will be created, count must be set to 1 if used
// # 2 - the token with id "${id}_{INDEX}" will be created, using count starting from 1
// # 3 - the token with id "${id}_{COLOR}_{INDEX}" will be created, using count, per player
// # 4 - the token with id "${id}_{COLOR}" for each player will be created, count must be 1
// # 5 - the token with id "${id}_{INDEX}_{COLOR}" for each player will be created
// # 6 - custom placeholders
// #9 house tokens (X is 1 to 9)  1p=4, 2p=6, 3p=8, 4p=10
    "house_0" => [ 
        "state" => 0,
        "name" => clienttranslate("Freya's Well"),
        "count" => 1,
        "type" => "house housemodel_0 well",
        "create" => 1,
        "location" => "hex_9_9",
],
    "house_1" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_1",
        "create" => 1,
        "location" => "hex_9_8",
],
    "house_2" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_4",
        "create" => 1,
        "location" => "hex_9_10",
],
    "house_3" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_1",
        "create" => 1,
        "location" => "hex_9_8",
],
    "house_4" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_4",
        "create" => 1,
        "location" => "hex_9_10",
],
    "house_5" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_1",
        "create" => 1,
        "location" => "hex_9_8",
],
    "house_6" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_4",
        "create" => 1,
        "location" => "hex_9_10",
],
    "house_7" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_1",
        "create" => 1,
        "location" => "hex_9_8",
],
    "house_8" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_4",
        "create" => 1,
        "location" => "hex_9_10",
],
    "house_9" => [ 
        "state" => 0,
        "name" => clienttranslate("House"),
        "count" => 1,
        "type" => "housemodel_1",
        "create" => 1,
        "location" => "hex_9_8",
],
// # player markers: 2 action markers + 1 upgrade cost marker per player
    "marker" => [ 
        "state" => 0,
        "name" => clienttranslate("Player Marker"),
        "count" => 3,
        "type" => "marker",
        "create" => 3,
        "location" => "limbo",
],
// #resources
    "crystal_green" => [ 
        "state" => 0,
        "name" => clienttranslate("Green Crystal"),
        "count" => 50,
        "create" => 2,
        "location" => "supply_crystal_green",
],
    "crystal_red" => [ 
        "state" => 0,
        "name" => clienttranslate("Red Crystal"),
        "count" => 50,
        "create" => 2,
        "location" => "supply_crystal_red",
],
    "crystal_yellow" => [ 
        "state" => 0,
        "name" => clienttranslate("Yellow Crystal"),
        "count" => 50,
        "create" => 2,
        "location" => "supply_crystal_yellow",
],
// # rune stone - time track marker
    "rune_stone" => [ 
        "state" => 0,
        "name" => clienttranslate("Rune Stone"),
        "count" => 1,
        "type" => "rune_stone",
        "create" => 1,
        "location" => "timetrack_1",
],
// # hero miniatures (one per hero, start with 2 heroes)
    "hero_1" => [ 
        "state" => 0,
        "name" => clienttranslate("Bjorn"),
        "count" => 1,
        "type" => "hero",
        "create" => 1,
        "location" => "hex_8_9",
],
    "hero_2" => [ 
        "state" => 0,
        "name" => clienttranslate("Alva"),
        "count" => 1,
        "type" => "hero",
        "create" => 1,
        "location" => "hex_8_10",
],
    "hero_3" => [ 
        "state" => 0,
        "name" => clienttranslate("Embla"),
        "count" => 1,
        "type" => "hero",
        "create" => 1,
        "location" => "hex_10_8",
],
    "hero_4" => [ 
        "state" => 0,
        "name" => clienttranslate("Boldur"),
        "count" => 1,
        "type" => "hero",
        "create" => 1,
        "location" => "hex_10_9",
],
// # hero cards (one per hero, placed on player tableau during setup)
    "card_hero_1" => [ 
        "state" => 0,
        "name" => clienttranslate("Bjorn"),
        "count" => 1,
        "type" => "card",
        "create" => 1,
        "location" => "limbo",
],
    "card_hero_2" => [ 
        "state" => 0,
        "name" => clienttranslate("Alva"),
        "count" => 1,
        "type" => "card",
        "create" => 1,
        "location" => "limbo",
],
    "card_hero_3" => [ 
        "state" => 0,
        "name" => clienttranslate("Embla"),
        "count" => 1,
        "type" => "card",
        "create" => 1,
        "location" => "limbo",
],
    "card_hero_4" => [ 
        "state" => 0,
        "name" => clienttranslate("Boldur"),
        "count" => 1,
        "type" => "card",
        "create" => 1,
        "location" => "limbo",
],
// # attack dice (20 total)
    "die_attack" => [ 
        "state" => 0,
        "name" => clienttranslate("Attack Die"),
        "count" => 20,
        "type" => "die",
        "create" => 2,
        "location" => "supply_dice",
],
// # damage dice (8 total, for tracking monster damage)
    "die_damage" => [ 
        "state" => 0,
        "name" => clienttranslate("Damage Die"),
        "count" => 8,
        "type" => "die",
        "create" => 2,
        "location" => "supply_dice",
],
// # monster die (optional variant, 1 die)
    "die_monster" => [ 
        "state" => 0,
        "name" => clienttranslate("Monster Die"),
        "count" => 1,
        "type" => "die",
        "create" => 1,
        "location" => "supply_dice",
],
            /* --- gen php end token_material --- */

            /* --- gen php begin monster_material --- */
// # Trollkin faction monsters
    "monster_goblin" => [ 
        "name" => clienttranslate("Goblin"),
        "count" => 20,
        "type" => "monster trollkin rank1",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "trollkin",
        "rank" => 1,
        "strength" => 1,
        "health" => 2,
        "xp" => 1,
        "move" => 2,
],
    "monster_brute" => [ 
        "name" => clienttranslate("Brute"),
        "count" => 15,
        "type" => "monster trollkin rank2",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "trollkin",
        "rank" => 2,
        "strength" => 3,
        "health" => 3,
        "xp" => 2,
],
    "monster_troll" => [ 
        "name" => clienttranslate("Troll"),
        "count" => 10,
        "type" => "monster trollkin rank3",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "trollkin",
        "rank" => 3,
        "strength" => 6,
        "health" => 7,
        "xp" => 3,
],
// # Fire Horde faction monsters
    "monster_sprite" => [ 
        "name" => clienttranslate("Sprite"),
        "count" => 20,
        "type" => "monster firehorde rank1",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "firehorde",
        "rank" => 1,
        "strength" => 1,
        "health" => 2,
        "xp" => 1,
],
    "monster_elemental" => [ 
        "name" => clienttranslate("Elemental"),
        "count" => 15,
        "type" => "monster firehorde rank2",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "firehorde",
        "rank" => 2,
        "strength" => 3,
        "health" => 4,
        "xp" => 2,
],
    "monster_jotunn" => [ 
        "name" => clienttranslate("Jotunn"),
        "count" => 10,
        "type" => "monster firehorde rank3",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "firehorde",
        "rank" => 3,
        "strength" => 5,
        "health" => 6,
        "xp" => 3,
],
// # Dead faction monsters
    "monster_imp" => [ 
        "name" => clienttranslate("Imp"),
        "count" => 20,
        "type" => "monster dead rank1",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "dead",
        "rank" => 1,
        "strength" => 2,
        "health" => 2,
        "xp" => 1,
],
    "monster_skeleton" => [ 
        "name" => clienttranslate("Skeleton"),
        "count" => 15,
        "type" => "monster dead rank2",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "dead",
        "rank" => 2,
        "strength" => 4,
        "health" => 3,
        "xp" => 2,
],
    "monster_draugr" => [ 
        "name" => clienttranslate("Draugr"),
        "count" => 10,
        "type" => "monster dead rank3",
        "create" => 2,
        "location" => "supply_monster",
        "faction" => "dead",
        "rank" => 3,
        "strength" => 6,
        "health" => 5,
        "xp" => 3,
        "armor" => 1,
],
// # Legend monsters (6 legends, each has yellow and red level sides)
    "monster_legend_grendel" => [ 
        "name" => clienttranslate("Grendel"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "trollkin",
],
    "monster_legend_nidhuggr" => [ 
        "name" => clienttranslate("Nidhuggr"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "dead",
],
    "monster_legend_surt" => [ 
        "name" => clienttranslate("Surt"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "firehorde",
],
    "monster_legend_queen" => [ 
        "name" => clienttranslate("Queen of the Dead"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "dead",
],
    "monster_legend_hrungbald" => [ 
        "name" => clienttranslate("Hrungbald"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "trollkin",
],
    "monster_legend_seer" => [ 
        "name" => clienttranslate("Seer of Odin"),
        "count" => 1,
        "type" => "monster legend",
        "create" => 0,
        "location" => "supply_monster",
        "faction" => "firehorde",
],
            /* --- gen php end monster_material --- */

            /* --- gen php begin monstercard_material --- */
// # spawnloc - locate where they spawn
// # spawn format: comma separed list of monster type abbreviation as they appear on location, i.e for Flanking it will be "G,G,,,B,,,T,,B"
// # Monster cards - yellow deck (early game) - 36
// # Yellow legends (1-6)
    "card_monster_1" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 1,
        "name" => clienttranslate("Queen of the Dead"),
        "spawnloc" => "Nailfare",
        "spawn" => "L",
        "ftext" => "Scary as Hel.",
],
    "card_monster_2" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 2,
        "name" => clienttranslate("Seer of Odin"),
        "spawnloc" => "TempleRuins",
        "spawn" => "L",
        "ftext" => "They say she sees everything, except what's before her eyes.",
],
    "card_monster_3" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 3,
        "name" => clienttranslate("Grendel"),
        "spawnloc" => "TrollCaves",
        "spawn" => "L",
        "ftext" => "Grendel has a face only a mother could love...and she's not too sure.",
],
    "card_monster_4" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 4,
        "name" => clienttranslate("Surt"),
        "spawnloc" => "Highlands",
        "spawn" => "L,J,E,E",
        "ftext" => "He really lights up the day.",
],
    "card_monster_5" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 5,
        "name" => clienttranslate("Hrungbald"),
        "spawnloc" => "OgreValley",
        "spawn" => "L,B,B,B",
        "ftext" => "Hrungbald Hammerfist has a fearsome reputation, a word he can't really pronounce.",
],
    "card_monster_6" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 6,
        "name" => clienttranslate("Nidhuggr"),
        "spawnloc" => "WyrmLair",
        "spawn" => "L",
        "ftext" => "Rumor has it that whoever dies will be his personal flosser in the afterlife.",
],
// # Yellow regular (7-36)
    "card_monster_7" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 7,
        "name" => clienttranslate("Fiery Projectiles"),
        "spawnloc" => "Highlands",
        "spawn" => "J,J,E",
        "ftext" => "I never expected this hero business to include so much running!",
],
    "card_monster_8" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 8,
        "name" => clienttranslate("Whirlwinds"),
        "spawnloc" => "Highlands",
        "spawn" => "E,E,E,E,E",
        "ftext" => "They always wind up attacking.",
],
    "card_monster_9" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 9,
        "name" => clienttranslate("Trending Monsters"),
        "spawnloc" => "Highlands",
        "spawn" => "J,E,E,S,S",
        "ftext" => "They are so hot right now.",
],
    "card_monster_10" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 10,
        "name" => clienttranslate("Burnt Offerings"),
        "spawnloc" => "Highlands",
        "spawn" => "E,E,E,E,S",
        "ftext" => "They just have to fire you first.",
],
    "card_monster_11" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 11,
        "name" => clienttranslate("Jotunn Clan"),
        "spawnloc" => "Highlands",
        "spawn" => "J,E,E,S,S",
        "ftext" => "We just follow the big guy's orders.",
],
    "card_monster_12" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 12,
        "name" => clienttranslate("Mischievous Sprites"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "S,S,S,S,S,S,S,S",
        "ftext" => "Like a bunch of hot-tempered kids, only on fire.",
],
    "card_monster_13" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 13,
        "name" => clienttranslate("Fire-Breathing Stones"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "J,E,E,E",
        "ftext" => 'As if just "breathing stones" wasn\'t bad enough...',
],
    "card_monster_14" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 14,
        "name" => clienttranslate("Jotunn Youth Camp"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "E,E,E,S,S,S",
        "ftext" => "The small ones need experience - let's warm them up...",
],
    "card_monster_15" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 15,
        "name" => clienttranslate("Air Strike"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "J,E,S,S,S,S",
        "ftext" => "Strategic bombing.",
],
    "card_monster_16" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 16,
        "name" => clienttranslate("Reunion"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "J,E,E,S,S",
        "ftext" => 'And they thought: "Why not at the human city? We can have a barbecue!"',
],
    "card_monster_17" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 17,
        "name" => clienttranslate("Boneyard"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "S,S,S,S,I",
        "ftext" => "Ivar the Boneless had bones after all!",
],
    "card_monster_18" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 18,
        "name" => clienttranslate("Bad to the Bone"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "D,S,S,S",
        "ftext" => "Yes, literally, their bones are rotten.",
],
    "card_monster_19" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 19,
        "name" => clienttranslate("Eyes in the Dark"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "S,S,I,I,I,I,I,I",
        "ftext" => "Can you see them? They see you!",
],
    "card_monster_20" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 20,
        "name" => clienttranslate("Skeletons on a Leash"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "D,D,S,S",
        "ftext" => "Draugrs' favorite pets.",
],
    "card_monster_21" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 21,
        "name" => clienttranslate("Marshing"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "D,S,I,I,I,I",
        "ftext" => "Out for a walk...",
],
    "card_monster_22" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 22,
        "name" => clienttranslate("Imp-ressive Swarm"),
        "spawnloc" => "DeadPlains",
        "spawn" => "I,I,I,I,I,I,I,S,S",
        "ftext" => "The sound of large flapping wings and toxic drool is just the appetizer.",
],
    "card_monster_23" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 23,
        "name" => clienttranslate("Bone Structures"),
        "spawnloc" => "DeadPlains",
        "spawn" => "S,S,S,S,S",
        "ftext" => "No, it doesn't make them look fat...",
],
    "card_monster_24" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 24,
        "name" => clienttranslate("Brain Dead"),
        "spawnloc" => "DeadPlains",
        "spawn" => "I,S,S,D",
        "ftext" => "Pure instincts.",
],
    "card_monster_25" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 25,
        "name" => clienttranslate("Dead Trio"),
        "spawnloc" => "DeadPlains",
        "spawn" => "D,D,D",
        "ftext" => "All for one!",
],
    "card_monster_26" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 26,
        "name" => clienttranslate("Sudden Death"),
        "spawnloc" => "DeadPlains",
        "spawn" => "I,I,S,S,D",
        "ftext" => "They are upping their game, it could be over at any moment.",
],
    "card_monster_27" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 27,
        "name" => clienttranslate("Brutish Offense"),
        "spawnloc" => "OgreValley",
        "spawn" => "B,B,B,B,B",
        "ftext" => "Relentless, ruthless, senseless, clueless.",
],
    "card_monster_28" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 28,
        "name" => clienttranslate("Flanking"),
        "spawnloc" => "OgreValley",
        "spawn" => "G,G,,,B,,,T,,B",
        "ftext" => "They are, quite flankly, devastating to our defenses.",
],
    "card_monster_29" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 29,
        "name" => clienttranslate("Foraging"),
        "spawnloc" => "OgreValley",
        "spawn" => "T,B,B,G,G",
        "ftext" => "Only looking for food.",
],
    "card_monster_30" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 30,
        "name" => clienttranslate("Brutus the Babysitter"),
        "spawnloc" => "OgreValley",
        "spawn" => "B,G,G,G,G,G,G,G",
        "ftext" => "Stay with the group!",
],
    "card_monster_31" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 31,
        "name" => clienttranslate("Strolling"),
        "spawnloc" => "OgreValley",
        "spawn" => "T,T,B",
        "ftext" => "Out for a walk, very aimlessly.",
],
    "card_monster_32" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 32,
        "name" => clienttranslate("Younglings"),
        "spawnloc" => "DarkForest",
        "spawn" => "B,G,G,G,G,G,G,G",
        "ftext" => "There are so many of them; what are we going to do?",
],
    "card_monster_33" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 33,
        "name" => clienttranslate("Brutal Band"),
        "spawnloc" => "DarkForest",
        "spawn" => ",,B,B,B,B,,,,,,B",
        "ftext" => "Specializing in war drums.",
],
    "card_monster_34" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 34,
        "name" => clienttranslate("Family Trip"),
        "spawnloc" => "DarkForest",
        "spawn" => "T,B,G,G,G,G",
        "ftext" => "The kids are so eager, let them run ahead...",
],
    "card_monster_35" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 35,
        "name" => clienttranslate("Forest Patrol"),
        "spawnloc" => "DarkForest",
        "spawn" => "B,B,B,B,G,G",
        "ftext" => "Attacking at their first convenience.",
],
    "card_monster_36" => [ 
        "create" => 1,
        "ctype" => "yellow",
        "location" => "deck_monster_yellow",
        "type" => "card card_monster ctype_yellow",
        "num" => 36,
        "name" => clienttranslate("Viral Trolls"),
        "spawnloc" => "DarkForest",
        "spawn" => "T,T,T",
        "ftext" => "The sheer amount of bacteria in their boogers and toxic breath makes them viral.",
],
// # Monster cards - red deck (late game) - 18
// # Red legends (37-42)
    "card_monster_37" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 37,
        "name" => clienttranslate("Queen of the Dead"),
        "spawnloc" => "DeadPlains",
        "spawn" => "L,S,S,S,I,I,I,I,D",
        "ftext" => "Scary as Hel.",
],
    "card_monster_38" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 38,
        "name" => clienttranslate("Seer of Odin"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "L,S,S,S,S,I,I,I",
        "ftext" => "I didn't see it coming, but she did...",
],
    "card_monster_39" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 39,
        "name" => clienttranslate("Grendel"),
        "spawnloc" => "OgreValley",
        "spawn" => "L,G,G,G",
        "ftext" => "Trolls will be trolls.",
],
    "card_monster_40" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 40,
        "name" => clienttranslate("Surt"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "L,E,E,E",
        "ftext" => "Hotter than a summer day in Muspelheim, and twice as destructive.",
],
    "card_monster_41" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 41,
        "name" => clienttranslate("Hrungbald"),
        "spawnloc" => "DarkForest",
        "spawn" => "L,B,B,B,B,B,G,G",
        "ftext" => "Now with added fury and an even smaller vocabulary.",
],
    "card_monster_42" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 42,
        "name" => clienttranslate("Nidhuggr"),
        "spawnloc" => "Highlands",
        "spawn" => "L,E,E,E,E",
        "ftext" => "Nidhuggr, the eater of worlds, has ordered Viking for dinner.",
],
// # Red regular (43-54)
    "card_monster_43" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 43,
        "name" => clienttranslate("Feed the Flames"),
        "spawnloc" => "Highlands",
        "spawn" => "J,E,E,E,E,S,S,S,S,S,S,S",
        "ftext" => "You've never seen flames spread this fast!",
],
    "card_monster_44" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 44,
        "name" => clienttranslate("Brimstone"),
        "spawnloc" => "Highlands",
        "spawn" => "J,J,J,J,S,S",
        "ftext" => "A trial by fire.",
],
    "card_monster_45" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 45,
        "name" => clienttranslate("Erupting"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "J,J,J,J,E",
        "ftext" => "The ancient Jotunn arrive through the fire portal.",
],
    "card_monster_46" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 46,
        "name" => clienttranslate("The Red Tide"),
        "spawnloc" => "SpewingMountain",
        "spawn" => "J,J,E,E,S,S,S,S,S,S",
        "ftext" => "If they could do this every month, they would. But we won't let them. Period.",
],
    "card_monster_47" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 47,
        "name" => clienttranslate("Walking Armors"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "D,D,D,D",
        "ftext" => "It's the march of sorrow.",
],
    "card_monster_48" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 48,
        "name" => clienttranslate("Gloom Fate"),
        "spawnloc" => "MarshOfSorrow",
        "spawn" => "D,S,S,S,S,S,S,S",
        "ftext" => "They're raised from the dead just to be killed again. Gotta pity those guys...",
],
    "card_monster_49" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 49,
        "name" => clienttranslate("Dread Summoning"),
        "spawnloc" => "DeadPlains",
        "spawn" => "I,I,I,I,S,D,D,D",
        "ftext" => "Death was just a brief pause. Now they're back for the finale, and they're dead serious about it...",
],
    "card_monster_50" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 50,
        "name" => clienttranslate("Intense Darkness"),
        "spawnloc" => "DeadPlains",
        "spawn" => "I,I,S,S,S,S,D,D",
        "ftext" => "I don't understand it, but it hurts, so it has to be real.",
],
    "card_monster_51" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 51,
        "name" => clienttranslate("Trololololol"),
        "spawnloc" => "OgreValley",
        "spawn" => "T,T,T,T,G,G",
        "ftext" => "Four grown trolls singing; it might be the death of me.",
],
    "card_monster_52" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 52,
        "name" => clienttranslate("Immoral Brutes"),
        "spawnloc" => "OgreValley",
        "spawn" => "T,B,B,B,B,B,B,G",
        "ftext" => "They do what they want, moralless.",
],
    "card_monster_53" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 53,
        "name" => clienttranslate("Trollkin United"),
        "spawnloc" => "DarkForest",
        "spawn" => "T,T,B,B,B,G,G,G,G",
        "ftext" => "A seldom-sighted formation of trolls.",
],
    "card_monster_54" => [ 
        "create" => 1,
        "ctype" => "red",
        "location" => "deck_monster_red",
        "type" => "card card_monster ctype_red",
        "num" => 54,
        "name" => clienttranslate("Brawling Brutes"),
        "spawnloc" => "DarkForest",
        "spawn" => "T,B,B,B,B,G,G,G,G,G,G",
        "ftext" => "Cheap to hire, fierce fighters. Not that bright, though.",
],
            /* --- gen php end monstercard_material --- */

            // Map hex data — generated from map_material.csv
            // Each entry represents a hex on the game board with coordinates, adjacency, and terrain info
            /* --- gen php begin map_material --- */
    "hex_9_1" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 1,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_10_1" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 1,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_11_1" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 1,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_12_1" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 1,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_13_1" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 1,
        "terrain" => "mountain",
],
    "hex_14_1" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 1,
        "terrain" => "mountain",
],
    "hex_15_1" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 1,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_16_1" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 1,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_17_1" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 1,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_8_2" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 2,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_9_2" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 2,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_10_2" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 2,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_11_2" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 2,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_12_2" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 2,
        "terrain" => "plains",
],
    "hex_13_2" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 2,
        "terrain" => "plains",
],
    "hex_14_2" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 2,
        "terrain" => "plains",
],
    "hex_15_2" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 2,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_16_2" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 2,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_17_2" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 2,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_7_3" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 3,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_8_3" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 3,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_9_3" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 3,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_10_3" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_11_3" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_12_3" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_13_3" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_14_3" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_15_3" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 3,
        "terrain" => "plains",
],
    "hex_16_3" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 3,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_17_3" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 3,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_6_4" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 4,
        "terrain" => "plains",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_7_4" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 4,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_8_4" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 4,
        "terrain" => "forest",
        "loc" => "DarkForest",
        "c" => "red",
],
    "hex_9_4" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 4,
        "terrain" => "plains",
],
    "hex_10_4" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 4,
        "terrain" => "forest",
],
    "hex_11_4" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 4,
        "terrain" => "plains",
],
    "hex_12_4" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 4,
        "terrain" => "forest",
        "loc" => "TempleRuins",
],
    "hex_13_4" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 4,
        "terrain" => "mountain",
],
    "hex_14_4" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 4,
        "terrain" => "plains",
],
    "hex_15_4" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 4,
        "terrain" => "plains",
],
    "hex_16_4" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 4,
        "terrain" => "plains",
        "loc" => "DeadPlains",
        "c" => "red",
],
    "hex_17_4" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 4,
        "terrain" => "lake",
],
    "hex_5_5" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 5,
        "terrain" => "lake",
],
    "hex_6_5" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_7_5" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_8_5" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_9_5" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_10_5" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_11_5" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 5,
        "terrain" => "forest",
],
    "hex_12_5" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_13_5" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_14_5" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 5,
        "terrain" => "mountain",
],
    "hex_15_5" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 5,
        "terrain" => "plains",
],
    "hex_16_5" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 5,
        "terrain" => "lake",
        "loc" => "Nailfare",
        "c" => "yellow",
],
    "hex_17_5" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 5,
        "terrain" => "lake",
        "loc" => "Nailfare",
        "c" => "yellow",
],
    "hex_4_6" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 6,
        "terrain" => "lake",
],
    "hex_5_6" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 6,
        "terrain" => "lake",
],
    "hex_6_6" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 6,
        "terrain" => "mountain",
        "loc" => "TrollCaves",
        "c" => "yellow",
],
    "hex_7_6" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_8_6" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_9_6" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 6,
        "terrain" => "forest",
],
    "hex_10_6" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_11_6" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_12_6" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_13_6" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_14_6" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_15_6" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 6,
        "terrain" => "plains",
],
    "hex_16_6" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 6,
        "terrain" => "lake",
],
    "hex_17_6" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 6,
        "terrain" => "lake",
],
    "hex_3_7" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 7,
        "terrain" => "mountain",
],
    "hex_4_7" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 7,
        "terrain" => "mountain",
],
    "hex_5_7" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 7,
        "terrain" => "mountain",
        "loc" => "TrollCaves",
        "c" => "yellow",
],
    "hex_6_7" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 7,
        "terrain" => "mountain",
        "loc" => "TrollCaves",
        "c" => "yellow",
],
    "hex_7_7" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_8_7" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_9_7" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_10_7" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_11_7" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_12_7" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_13_7" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_14_7" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 7,
        "terrain" => "plains",
],
    "hex_15_7" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 7,
        "terrain" => "mountain",
],
    "hex_16_7" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 7,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_17_7" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 7,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_2_8" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 8,
        "terrain" => "forest",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_3_8" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 8,
        "terrain" => "forest",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_4_8" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 8,
        "terrain" => "forest",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_5_8" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 8,
        "terrain" => "forest",
],
    "hex_6_8" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_7_8" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_8_8" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_9_8" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 8,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_10_8" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 8,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_11_8" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_12_8" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_13_8" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_14_8" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_15_8" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 8,
        "terrain" => "plains",
],
    "hex_16_8" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 8,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_17_8" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 8,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_1_9" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_2_9" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_3_9" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 9,
        "terrain" => "forest",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_4_9" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_5_9" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_6_9" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_7_9" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_8_9" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_9_9" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_10_9" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_11_9" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_12_9" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_13_9" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_14_9" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_15_9" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 9,
        "terrain" => "plains",
],
    "hex_16_9" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_17_9" => [ 
        "location" => "map_hexes",
        "x" => 17,
        "y" => 9,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_1_10" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_2_10" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 10,
        "terrain" => "forest",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_3_10" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_4_10" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 10,
        "terrain" => "forest",
],
    "hex_5_10" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_6_10" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_7_10" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_8_10" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_9_10" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "Grimheim",
        "c" => "yellow",
],
    "hex_10_10" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 10,
        "terrain" => "forest",
],
    "hex_11_10" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 10,
        "terrain" => "forest",
],
    "hex_12_10" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_13_10" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_14_10" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 10,
        "terrain" => "plains",
],
    "hex_15_10" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_16_10" => [ 
        "location" => "map_hexes",
        "x" => 16,
        "y" => 10,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_1_11" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 11,
        "terrain" => "plains",
        "loc" => "OgreValley",
        "c" => "red",
],
    "hex_2_11" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_3_11" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_4_11" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_5_11" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 11,
        "terrain" => "forest",
        "loc" => "RobberCamp",
],
    "hex_6_11" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_7_11" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_8_11" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_9_11" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 11,
        "terrain" => "mountain",
],
    "hex_10_11" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 11,
        "terrain" => "forest",
],
    "hex_11_11" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 11,
        "terrain" => "forest",
        "loc" => "WitchCabin",
],
    "hex_12_11" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_13_11" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 11,
        "terrain" => "plains",
],
    "hex_14_11" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 11,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_15_11" => [ 
        "location" => "map_hexes",
        "x" => 15,
        "y" => 11,
        "terrain" => "plains",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_1_12" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_2_12" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 12,
        "terrain" => "forest",
],
    "hex_3_12" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 12,
        "terrain" => "forest",
],
    "hex_4_12" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 12,
        "terrain" => "forest",
],
    "hex_5_12" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 12,
        "terrain" => "mountain",
],
    "hex_6_12" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_7_12" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_8_12" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_9_12" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_10_12" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_11_12" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 12,
        "terrain" => "plains",
],
    "hex_12_12" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 12,
        "terrain" => "forest",
],
    "hex_13_12" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 12,
        "terrain" => "forest",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_14_12" => [ 
        "location" => "map_hexes",
        "x" => 14,
        "y" => 12,
        "terrain" => "forest",
        "loc" => "MarshOfSorrow",
        "c" => "red",
],
    "hex_1_13" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 13,
        "terrain" => "forest",
],
    "hex_2_13" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 13,
        "terrain" => "forest",
],
    "hex_3_13" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_4_13" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 13,
        "terrain" => "mountain",
],
    "hex_5_13" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 13,
        "terrain" => "mountain",
],
    "hex_6_13" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_7_13" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_8_13" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_9_13" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 13,
        "terrain" => "mountain",
],
    "hex_10_13" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_11_13" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 13,
        "terrain" => "mountain",
],
    "hex_12_13" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 13,
        "terrain" => "plains",
],
    "hex_13_13" => [ 
        "location" => "map_hexes",
        "x" => 13,
        "y" => 13,
        "terrain" => "forest",
],
    "hex_1_14" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 14,
        "terrain" => "forest",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_2_14" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 14,
        "terrain" => "plains",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_3_14" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_4_14" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_5_14" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_6_14" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 14,
        "terrain" => "mountain",
],
    "hex_7_14" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_8_14" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_9_14" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 14,
        "terrain" => "plains",
],
    "hex_10_14" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 14,
        "terrain" => "mountain",
],
    "hex_11_14" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 14,
        "terrain" => "plains",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_12_14" => [ 
        "location" => "map_hexes",
        "x" => 12,
        "y" => 14,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_1_15" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 15,
        "terrain" => "plains",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_2_15" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 15,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_3_15" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 15,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_4_15" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 15,
        "terrain" => "plains",
],
    "hex_5_15" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 15,
        "terrain" => "mountain",
],
    "hex_6_15" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 15,
        "terrain" => "plains",
        "loc" => "WyrmLair",
        "c" => "yellow",
],
    "hex_7_15" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 15,
        "terrain" => "mountain",
],
    "hex_8_15" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 15,
        "terrain" => "plains",
],
    "hex_9_15" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 15,
        "terrain" => "plains",
],
    "hex_10_15" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 15,
        "terrain" => "plains",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_11_15" => [ 
        "location" => "map_hexes",
        "x" => 11,
        "y" => 15,
        "terrain" => "plains",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_1_16" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 16,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_2_16" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 16,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_3_16" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 16,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_4_16" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 16,
        "terrain" => "plains",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_5_16" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 16,
        "terrain" => "plains",
        "loc" => "WyrmLair",
        "c" => "yellow",
],
    "hex_6_16" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 16,
        "terrain" => "plains",
        "loc" => "WyrmLair",
        "c" => "yellow",
],
    "hex_7_16" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 16,
        "terrain" => "plains",
],
    "hex_8_16" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 16,
        "terrain" => "plains",
],
    "hex_9_16" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 16,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_10_16" => [ 
        "location" => "map_hexes",
        "x" => 10,
        "y" => 16,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_1_17" => [ 
        "location" => "map_hexes",
        "x" => 1,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_2_17" => [ 
        "location" => "map_hexes",
        "x" => 2,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_3_17" => [ 
        "location" => "map_hexes",
        "x" => 3,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "SpewingMountain",
        "c" => "red",
],
    "hex_4_17" => [ 
        "location" => "map_hexes",
        "x" => 4,
        "y" => 17,
        "terrain" => "mountain",
],
    "hex_5_17" => [ 
        "location" => "map_hexes",
        "x" => 5,
        "y" => 17,
        "terrain" => "plains",
        "loc" => "WyrmLair",
        "c" => "yellow",
],
    "hex_6_17" => [ 
        "location" => "map_hexes",
        "x" => 6,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_7_17" => [ 
        "location" => "map_hexes",
        "x" => 7,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_8_17" => [ 
        "location" => "map_hexes",
        "x" => 8,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
    "hex_9_17" => [ 
        "location" => "map_hexes",
        "x" => 9,
        "y" => 17,
        "terrain" => "mountain",
        "loc" => "Highlands",
        "c" => "red",
],
            /* --- gen php end map_material --- */

            /* --- gen php begin strings_material --- */
// # Terrain types
    "forest" => [ 
        "name" => clienttranslate("Forest"),
],
    "plains" => [ 
        "name" => clienttranslate("Plains"),
],
    "mountain" => [ 
        "name" => clienttranslate("Mountain"),
],
    "lake" => [ 
        "name" => clienttranslate("Lake"),
],
// # Named locations
    "DarkForest" => [ 
        "name" => clienttranslate("Dark Forest"),
],
    "DeadPlains" => [ 
        "name" => clienttranslate("Dead Plains"),
],
    "Grimheim" => [ 
        "name" => clienttranslate("Grimheim"),
],
    "Highlands" => [ 
        "name" => clienttranslate("Highlands"),
],
    "MarshOfSorrow" => [ 
        "name" => clienttranslate("Marsh of Sorrow"),
],
    "Nailfare" => [ 
        "name" => clienttranslate("Nailfare"),
],
    "OgreValley" => [ 
        "name" => clienttranslate("Ogre Valley"),
],
    "RobberCamp" => [ 
        "name" => clienttranslate("Robber Camp"),
],
    "SpewingMountain" => [ 
        "name" => clienttranslate("Spewing Mountain"),
],
    "TempleRuins" => [ 
        "name" => clienttranslate("Temple Ruins"),
],
    "TrollCaves" => [ 
        "name" => clienttranslate("Troll Caves"),
],
    "WitchCabin" => [ 
        "name" => clienttranslate("Witch Cabin"),
],
    "WyrmLair" => [ 
        "name" => clienttranslate("Wyrm Lair"),
],
// # Monster factions
    "trollkin" => [ 
        "name" => clienttranslate("Trollkin"),
],
    "firehorde" => [ 
        "name" => clienttranslate("Fire Horde"),
],
    "dead" => [ 
        "name" => clienttranslate("Dead"),
],
// # Area colors (used on map hex flags)
    "red" => [ 
        "name" => clienttranslate("Red"),
],
    "yellow" => [ 
        "name" => clienttranslate("Yellow"),
],
// # Time track spot types
    "tm_yellow_axes" => [ 
        "name" => clienttranslate("Yellow Reinforcements"),
],
    "tm_red_axes" => [ 
        "name" => clienttranslate("Red Reinforcements"),
],
    "tm_yellow_shield" => [ 
        "name" => clienttranslate("Yellow Shield"),
],
    "tm_red_shield" => [ 
        "name" => clienttranslate("Red Shield"),
],
    "tm_red_skull" => [ 
        "name" => clienttranslate("Charge"),
],
            /* --- gen php end strings_material --- */
            /* --- gen php begin time_material --- */
    "slot_timetrack_1_1" => [ 
        "num" => 1,
        "r" => "tm_yellow_axes",
],
    "slot_timetrack_1_2" => [ 
        "num" => 2,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_1_3" => [ 
        "num" => 3,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_1_4" => [ 
        "num" => 4,
        "r" => "tm_yellow_axes",
],
    "slot_timetrack_1_5" => [ 
        "num" => 5,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_1_6" => [ 
        "num" => 6,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_1_7" => [ 
        "num" => 7,
        "r" => "tm_red_axes",
],
    "slot_timetrack_1_8" => [ 
        "num" => 8,
        "r" => "tm_red_shield",
],
    "slot_timetrack_1_9" => [ 
        "num" => 9,
        "r" => "tm_red_skull",
],
    "slot_timetrack_1_10" => [ 
        "num" => 10,
        "r" => "tm_red_skull",
],
    "slot_timetrack_1_11" => [ 
        "num" => 11,
        "r" => "tm_red_skull",
],
    "slot_timetrack_1_12" => [ 
        "num" => 12,
        "r" => "tm_red_skull",
],
    "slot_timetrack_2_1" => [ 
        "num" => 1,
        "r" => "tm_yellow_axes",
],
    "slot_timetrack_2_2" => [ 
        "num" => 2,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_2_3" => [ 
        "num" => 3,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_2_4" => [ 
        "num" => 4,
        "r" => "tm_yellow_axes",
],
    "slot_timetrack_2_5" => [ 
        "num" => 5,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_2_6" => [ 
        "num" => 6,
        "r" => "tm_yellow_axes",
],
    "slot_timetrack_2_7" => [ 
        "num" => 7,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_2_8" => [ 
        "num" => 8,
        "r" => "tm_yellow_shield",
],
    "slot_timetrack_2_9" => [ 
        "num" => 9,
        "r" => "tm_red_axes",
],
    "slot_timetrack_2_10" => [ 
        "num" => 10,
        "r" => "tm_red_shield",
],
    "slot_timetrack_2_11" => [ 
        "num" => 11,
        "r" => "tm_red_axes",
],
    "slot_timetrack_2_12" => [ 
        "num" => 12,
        "r" => "tm_red_shield",
],
    "slot_timetrack_2_13" => [ 
        "num" => 13,
        "r" => "tm_red_skull",
],
    "slot_timetrack_2_14" => [ 
        "num" => 14,
        "r" => "tm_red_skull",
],
    "slot_timetrack_2_15" => [ 
        "num" => 15,
        "r" => "tm_red_skull",
],
    "slot_timetrack_2_16" => [ 
        "num" => 16,
        "r" => "tm_red_skull",
],
            /* --- gen php end time_material --- */
            /* --- gen php begin location_material --- */
// # Monster card decks
    "deck_monster_yellow" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Yellow Monster Deck"),
        "location" => "display_monsterturn",
        "scope" => "global",
        "counter" => "public",
        "content" => "hidden",
],
    "deck_monster_red" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Red Monster Deck"),
        "location" => "display_monsterturn",
        "scope" => "global",
        "counter" => "public",
        "content" => "hidden",
],
// # Monster turn display
    "display_monsterturn" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Monster Cards"),
        "location" => "thething",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
// # Supply areas
    "supply_monster" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Monster Supply"),
        "location" => "supply",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
    "supply_crystal_green" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Green Crystal Supply"),
        "location" => "supply",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
    "supply_crystal_red" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Red Crystal Supply"),
        "location" => "supply",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
    "supply_crystal_yellow" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Yellow Crystal Supply"),
        "location" => "supply",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
    "supply_dice" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Dice Supply"),
        "location" => "supply",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
// # Player board sub-locations
    "deck_ability" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Abilities"),
        "location" => "tableau",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "public",
],
    "deck_equip" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Equipment"),
        "location" => "tableau",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "public",
],
    "deck_event" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Event Deck"),
        "location" => "tableau",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "public",
],
    "discard" => [ 
        "type" => "location",
        "create" => 0,
        "name" => clienttranslate("Discard"),
        "location" => "tableau",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "public",
],
    "limbo" => [ 
        "type" => "location",
        "create" => 0,
        "showtooltip" => 0,
        "name" => clienttranslate("Limbo"),
        "scope" => "global",
        "counter" => "hidden",
        "content" => "hidden",
],
    "tableau" => [ 
        "type" => "location",
        "create" => 0,
        "showtooltip" => 0,
        "name" => clienttranslate("Player Area"),
        "location" => "players_panels",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "public",
],
    "hand" => [ 
        "type" => "location",
        "create" => 0,
        "showtooltip" => 0,
        "name" => clienttranslate("Player Hand"),
        "location" => "players_panels",
        "scope" => "player",
        "counter" => "hidden",
        "content" => "private",
],
    "supply" => [ 
        "type" => "location",
        "create" => 0,
        "showtooltip" => 0,
        "name" => clienttranslate("Supply"),
        "location" => "thething",
        "scope" => "global",
        "counter" => "hidden",
        "content" => "public",
],
            /* --- gen php end location_material --- */
            /* --- GEN PLACEHOLDR --- */
        ];
    }
}
