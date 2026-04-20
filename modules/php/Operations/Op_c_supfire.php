<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_supfire: Prevent a monster within range from moving this monster turn.
 *
 * Params:
 * - param(0): range expression (e.g. "inRange3", "inRange", "adj")
 * - param(1): optional filter expression (default "true")
 *
 * Data Fields:
 * - card: the ability/event card ID that triggered this (set by useCard)
 *
 * Marker is per-card (stunmarker_<card>) so concurrent Suppressive Fires from different
 * players/cards don't clobber each other's "cannot choose same monster next turn" state.
 * Op_monsterMoveAll scans any stunmarker_* prefix.
 *
 * Used by: Suppressive Fire I/II (card_ability_1_5/6, card_ability_2_9/10),
 *          Take a Knee (card_event_2_29).
 */
class Op_c_supfire extends Operation {
    function getCustomMarker() {
        return "stunmarker_" . $this->getDataField("card", "c");
    }
    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "inRange3"));
    }

    function getPrompt() {
        return clienttranslate("Choose a monster to suppress (prevent from moving)");
    }

    private function matchesFilter(string $monsterId): bool {
        $filter = $this->getParam(1, "true");
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $monsterId);
    }

    private function findStunMarker(): string {
        $key = $this->getCustomMarker();
        if ($this->game->tokens->getTokenInfo($key)) {
            return $key;
        } else {
            return $this->game->tokens->createToken($key);
        }
    }

    function getPossibleMoves(): array {
        $hero = $this->game->getHero($this->getOwner());

        $hexes = $hero->getMonsterHexesInRange($this->getRange(), function (string $monsterId) {
            if (!$this->matchesFilter($monsterId)) {
                return false;
            }
            $marker = $this->getCustomMarker();
            $crystals = $this->game->tokens->getTokensOfTypeInLocation($marker, $monsterId);
            if (count($crystals) > 0) {
                return false;
            }
            return true;
        });

        return $hexes;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();

        $monsterId = $this->game->hexMap->getCharacterOnHex($targetHex, "monster");
        $this->game->systemAssert("ERR:c_supfire:noMonsterOnHex:$targetHex", $monsterId !== null);

        $existing = $this->findStunMarker();

        $this->game->tokens->dbSetTokenLocation(
            $existing,
            $monsterId,
            0,
            clienttranslate('${char_name} stuns ${token_name} — it cannot move this turn ${reason}'),
            [
                "char_name" => $this->game->getHeroTokenId($this->getOwner()),
                "token_name" => $monsterId,
                "reason" => $this->getReason(),
            ]
        );
    }

    function skip(): void {
        parent::skip();
        $existing = $this->findStunMarker();
        $this->game->tokens->dbSetTokenLocation($existing, "limbo", 0, "");
    }

    function canSkip(): bool {
        return true;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
