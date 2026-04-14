<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_supfire: Prevent a monster within range 3 from moving this monster turn.
 *
 * Params:
 * - param(0): optional filter expression — e.g. "'rank<=2'" for Level I (default "true")
 *
 * Data Fields:
 * - card: the ability card ID that triggered this (set by useCard)
 *
 * Behaviour:
 * - Player selects an eligible monster hex within range 3
 * - A green crystal is placed on the monster (stun marker)
 * - Op_monsterMoveAll checks for green crystals and skips stunned monsters (crystal stays)
 * - "Cannot choose same monster next turn": monsters that already have a green crystal
 *   are excluded from getPossibleMoves(). The old crystal is removed when the player
 *   picks a new target (in resolve) or skips (in onSkip).
 *
 * Used by: Suppressive Fire I (card_ability_1_5, card_ability_2_9),
 *          Suppressive Fire II (card_ability_1_6, card_ability_2_10).
 */
class Op_c_supfire extends Operation {
    function getPrompt() {
        return clienttranslate("Choose a monster to suppress (prevent from moving)");
    }

    private function matchesFilter(string $monsterId): bool {
        $filter = $this->getParam(0, "true");
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $monsterId);
    }

    /** Find the green crystal currently on a monster (from previous suppression), or null. */
    private function findStunCrystal(): ?string {
        $monsters = $this->game->hexMap->getMonstersOnMap();
        foreach ($monsters as $monster) {
            $found = $this->game->tokens->getTokensOfTypeInLocationSingleKey("crystal_green", $monster["key"]);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    function getPossibleMoves(): array {
        $hero = $this->game->getHero($this->getOwner());

        $hexes = $hero->getMonsterHexesInRange(3, function (string $monsterId) {
            if (!$this->matchesFilter($monsterId)) {
                return false;
            }
            // Cannot choose a monster that already has a green crystal (suppressed last turn)
            $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", $monsterId);
            if (count($crystals) > 0) {
                return false;
            }
            return true;
        });

        $targets = [];
        foreach ($hexes as $hexId) {
            $targets[$hexId] = ["q" => Material::RET_OK];
        }
        return $targets;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();

        $monsterId = $this->game->hexMap->getCharacterOnHex($targetHex, "monster");
        $this->game->systemAssert("ERR:c_supfire:noMonsterOnHex:$targetHex", $monsterId !== null);

        // Move existing stun crystal to the new monster (or pick a fresh one from supply)
        $existingCrystal = $this->findStunCrystal();
        if ($existingCrystal !== null) {
            $this->game->tokens->dbSetTokenLocation(
                $existingCrystal,
                $monsterId,
                0,
                clienttranslate('${char_name} suppresses ${token_name} — it cannot move this turn'),
                [
                    "char_name" => $this->game->getHeroTokenId($this->getOwner()),
                    "token_name" => $monsterId,
                ]
            );
        } else {
            $heroId = $this->game->getHeroTokenId($this->getOwner());
            $this->game->effect_moveCrystals($heroId, "green", 1, $monsterId, [
                "message" => clienttranslate('${char_name} suppresses ${token_name} — it cannot move this turn'),
                "token_name" => $monsterId,
            ]);
        }
    }

    function skip(): void {
        parent::skip();
        // Player chose not to suppress — remove old stun crystal
        $existingCrystal = $this->findStunCrystal();
        if ($existingCrystal !== null) {
            $this->game->tokens->dbSetTokenLocation($existingCrystal, "supply_crystal_green", 0, "");
        }
    }

    function canSkip(): bool {
        return true;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
