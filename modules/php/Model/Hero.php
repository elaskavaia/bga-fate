<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Game;

/**
 * Hero character: has an owner (player color), tableau with cards, and derived stats.
 */
class Hero extends Character {
    private string $owner;

    public function __construct(Game $game, string $heroId) {
        parent::__construct($game, $heroId);
        $this->owner = $this->game->getHeroOwner($heroId);
    }

    function getOwner(): string {
        return $this->owner;
    }

    function getPlayerId(): int {
        return $this->game->custom_getPlayerIdByColor($this->owner);
    }

    /** Returns all cards on this hero's tableau. */
    function getTableauCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_{$this->owner}");
    }

    /** Returns all cards in this hero's hand. */
    function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_{$this->owner}");
    }

    function getHandSize(): int {
        return count($this->getHandCards());
    }

    function getHandLimit(): int {
        // Starsong II: "You may have 5 cards in hand."
        $loc = $this->game->tokens->getTokenLocation("card_ability_2_8");
        if ($loc === "tableau_{$this->owner}") {
            return 5;
        }
        return 4;
    }

    /**
     * Draw 1 event card from deck to hand.
     * @return bool true if a card was drawn, false if deck is empty
     */
    function drawEventCard(): bool {
        $deck = "deck_event_{$this->owner}";
        $hand = "hand_{$this->owner}";
        $topCard = $this->game->tokens->getTokenOnTop($deck);
        if ($topCard === null) {
            return false;
        }
        $cardId = $topCard["key"];
        $this->game->tokens->dbSetTokenLocation($cardId, $hand, 0, clienttranslate('${char_name} draws an event card'), [
            "char_name" => $this->id,
        ]);
        $this->game->customUndoSavepoint($this->getPlayerId(), 1, "draw");
        return true;
    }

    function getCountOfCardsInEventDeck() {
        $deck = "deck_event_{$this->owner}";
        return count($this->game->tokens->getTokensOfTypeInLocation("card", $deck));
    }

    /**
     * Discard an event card from hand to the discard pile.
     */
    function discardEventCard(string $cardId): void {
        $discard = "discard_{$this->owner}";
        $top = $this->game->tokens->getTokenOnTop($discard);
        $state = $top ? ((int) $top["state"]) + 1 : 0;
        $this->game->tokens->dbSetTokenLocation($cardId, $discard, $state, clienttranslate('${char_name} discards ${token_name}'), [
            "char_name" => $this->id,
        ]);
    }

    /**
     * Returns the total attack strength: base hero card + equipment + abilities on tableau.
     * Hero card has an integer strength (e.g. 2), equipment/abilities use "+N" format.
     */
    function getAttackStrength(): int {
        $cards = $this->getTableauCards();
        $total = 0;
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $strength = $this->game->material->getRulesFor($cardId, "strength", "");
            if ($strength === "" || $strength === null) {
                continue;
            }
            $strength = (string) $strength;
            if (str_starts_with($strength, "+")) {
                $total += (int) substr($strength, 1);
            } else {
                $total += (int) $strength;
            }
        }
        return max($total, 0);
    }

    /**
     * Returns the max health from the hero card on tableau.
     */
    function getMaxHealth(): int {
        $heroCardKey = $this->game->tokens->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_{$this->owner}");
        return (int) $this->game->material->getRulesFor($heroCardKey, "health", 9);
    }

    /**
     * Returns hex IDs occupied by monsters within the given range of this hero.
     * @param int $range max distance in hexes
     * @param callable|null $filter optional filter — receives monsterId, returns bool
     * @return string[] array of hex IDs
     */
    function getMonsterHexesInRange(int $range, ?callable $filter = null): array {
        $heroHex = $this->game->hexMap->getCharacterHex($this->id);
        $this->game->systemAssert("ERR:heroNotOnMap:{$this->id}", $heroHex !== null);
        $hexesInRange = $this->game->hexMap->getHexesInRange($heroHex, $range);
        $targets = [];
        foreach ($hexesInRange as $hexId) {
            $monsterId = $this->game->hexMap->isOccupiedByCharacterType($hexId, "monster");
            if ($monsterId !== null && ($filter === null || $filter($monsterId))) {
                $targets[] = $hexId;
            }
        }
        return $targets;
    }

    /**
     * Resolve a range parameter string to an integer range value.
     * "adj" → 1, "inRange" → hero's attack range, "inRangeN" → N.
     */
    function getRangeFromParam(string $param): int {
        if ($param === "adj") {
            return 1;
        }
        if ($param === "inRange") {
            return $this->getAttackRange();
        }
        if (str_starts_with($param, "inRange")) {
            return (int) substr($param, 7);
        }

        if (is_numeric($param)) {
            return (int) $param;
        }
        return 1;
    }

    /**
     * Returns attack range based on equipment cards. Default is 1.
     */
    function getAttackRange(): int {
        $cards = $this->getTableauCards();
        $maxRange = 1;
        foreach ($cards as $card => $info) {
            $range = (int) $this->game->material->getRulesFor($card, "attack_range", 0);
            if ($range > $maxRange) {
                $maxRange = $range;
            }
        }
        return $maxRange;
    }

    /**
     * Returns the list of main action types taken this turn, derived from marker token locations.
     */
    function getActionsTaken(): array {
        $taken = [];
        foreach ([1, 2] as $i) {
            $loc = $this->game->tokens->getTokenLocation("marker_{$this->owner}_{$i}");
            $prefix = "aslot_{$this->owner}_";
            if (str_starts_with($loc, $prefix) && !str_contains($loc, "_empty_")) {
                $taken[] = str_replace($prefix, "", $loc);
            }
        }
        return $taken;
    }

    /**
     * Returns the number of main actions still available this turn.
     */
    function getActionsRemaining(): int {
        return 2 - count($this->getActionsTaken());
    }

    /**
     * Place the next free action marker on the given action slot.
     */
    function placeActionMarker(string $actionType): void {
        $x = count($this->getActionsTaken()) + 1;
        $actionName = $this->game->getTokenName("Op_$actionType");
        $this->game->tokens->dbSetTokenLocation(
            "marker_{$this->owner}_{$x}",
            "aslot_{$this->owner}_{$actionType}",
            0,
            clienttranslate('${char_name} takes ${action_name} action'),
            ["char_name" => $this->id, "action_name" => $actionName]
        );
    }

    /**
     * Award XP (yellow crystals) from supply to tableau.
     */
    function gainXp(int $amount): void {
        if ($amount <= 0) {
            return;
        }
        $this->moveCrystals("yellow", $amount, "tableau_{$this->owner}", [
            "message" => clienttranslate('${char_name} gains ${count} XP'),
        ]);
    }

    /**
     * Apply damage to this hero after monster attack.
     * If total damage >= health, hero is knocked out:
     * - Moved to Grimheim, damage set to 5, 2 town pieces destroyed.
     * @return bool true if the hero was knocked out
     */
    function applyDamageEffects(int $amount, string $attackerId): bool {
        $this->game->systemAssert("cannot be negative amount", $amount >= 0);
        $totalDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
        $health = $this->getMaxHealth();

        $this->game->notifyMessage(clienttranslate('${char_name} takes ${amount} damage (${totalDamage}/${health})'), [
            "char_name" => $this->id,
            "amount" => $amount,
            "totalDamage" => $totalDamage,
            "health" => $health,
        ]);

        if ($totalDamage >= $health) {
            // Adjust damage to exactly 5: remove excess or add missing
            $diff = $totalDamage - 5;
            if ($diff !== 0) {
                $this->moveCrystals("red", -$diff, $this->id, ["message" => ""]);
            }

            // Move hero to their starting hex in Grimheim
            $startHex = $this->getRulesFor("location");
            $this->moveTo(
                $startHex,
                clienttranslate('${char_name} is knocked out and carried back to Grimheim. By their mother. She is not happy.')
            );

            // "Some villagers panic and flee, leaving their houses undefended"
            $this->game->effect_destroyHouses(2, $this->id, clienttranslate('Villagers panic and flee, ${token_name} is left undefended!'));

            if ($this->game->isEndOfGame()) {
                $this->game->handleEndOfGame();
            }
            return true;
        }
        return false;
    }
}
