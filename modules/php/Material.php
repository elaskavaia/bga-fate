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
                "name" => clienttranslate("Maximum capacity is reached"),
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
    "Op_finalScoring" => [ 
        "type" => "finalScoring",
        "name" => clienttranslate("Final Scoring"),
],
            /* --- gen php end op_material --- */

            /* --- GEN PLACEHOLDR --- */
        ];
    }
}
