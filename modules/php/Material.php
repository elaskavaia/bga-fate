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

    const TIME_TRACK_SHORT_LENGTH = 10;

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
                "notimpl" => true,
            ],
            "Op_actionPrepare" => [
                "kind" => "main",
                "type" => "actionPrepare",
                "name" => clienttranslate("Prepare"),
                "notimpl" => true,
            ],
            "Op_actionFocus" => [
                "kind" => "main",
                "type" => "actionFocus",
                "name" => clienttranslate("Focus"),
                "notimpl" => true,
            ],
            "Op_actionMend" => [
                "kind" => "main",
                "type" => "actionMend",
                "name" => clienttranslate("Mend"),
                "notimpl" => true,
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
                "notimpl" => true,
            ],
            "Op_useAbility" => [
                "kind" => "free",
                "type" => "useAbility",
                "name" => clienttranslate("Use Ability"),
                "notimpl" => true,
            ],
            "Op_playEvent" => [
                "kind" => "free",
                "type" => "playEvent",
                "name" => clienttranslate("Play Event"),
                "notimpl" => true,
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
            // #9 house tokens (X is 1 to 9)
            "house" => [
                "state" => 0,
                "name" => clienttranslate("House"),
                "count" => 9,
                "type" => "house",
                "create" => 2,
                "location" => "hex_9_10",
            ],
            "house_0" => [
                "state" => 0,
                "name" => clienttranslate("Freya's Well"),
                "count" => 1,
                "type" => "house well",
                "create" => 1,
                "location" => "hex_9_9",
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
                "location" => "hex_9_8",
            ],
            "hero_2" => [
                "state" => 0,
                "name" => clienttranslate("Astrid"),
                "count" => 1,
                "type" => "hero",
                "create" => 1,
                "location" => "limbo",
            ],
            "hero_3" => [
                "state" => 0,
                "name" => clienttranslate("Kara"),
                "count" => 1,
                "type" => "hero",
                "create" => 1,
                "location" => "limbo",
            ],
            "hero_4" => [
                "state" => 0,
                "name" => clienttranslate("Boldur"),
                "count" => 1,
                "type" => "hero",
                "create" => 1,
                "location" => "limbo",
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
                "name" => clienttranslate("Astrid"),
                "count" => 1,
                "type" => "card",
                "create" => 1,
                "location" => "limbo",
            ],
            "card_hero_3" => [
                "state" => 0,
                "name" => clienttranslate("Kara"),
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

            /* --- GEN PLACEHOLDR --- */
        ];
    }
}
