<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * Base class for cards with custom (`on=custom`) behavior.
 *
 * Concrete subclasses live under modules/php/Cards/ and override `on_<triggerName>`
 * methods for the triggers they care about. Subclasses can delegate voluntary
 * effects back to the CSV `r` field via `queue($r)` instead of reimplementing
 * standard rule-expression logic in PHP.
 *
 * The Card is instantiated by Op_trigger for the duration of a single trigger
 * dispatch and holds a reference to the calling operation so that `queue()`
 * can insert sub-ops into the correct operation frame.
 */
class Card {
    protected $owner;
    protected $id;
    protected $state;
    protected $location;
    public function __construct(protected Game $game, string|array $cardOrId, protected Operation $op) {
        $this->owner = $op->getOwner();
        if (is_array($cardOrId)) {
            $this->id = $cardOrId["key"];
            $this->location = $cardOrId["location"];
            $this->state = $cardOrId["state"];
        } else {
            $this->id = $cardOrId;
            $info = $this->game->tokens->getTokenInfo($cardOrId);
            $this->location = $info["location"];
            $this->state = $info["state"];
        }
    }

    function getId(): string {
        return $this->id;
    }

    function getOwner(): string {
        return $this->owner;
    }

    /** Read a Material rule field for this card (e.g. "r", "on", "name"). */
    function getRulesFor(string $field, mixed $default = ""): mixed {
        return $this->game->getRulesFor($this->id, $field, $default);
    }

    /** Number of red crystals (damage) on this card. */
    function getDamage(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
    }

    /** Number of green crystals (mana) on this card. */
    function getMana(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $this->id));
    }

    /** Number of yellow crystals (gold/markers) on this card. */
    function getGold(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->id));
    }

    /**
     * Queue a sub-operation in the calling operation's frame, with this card
     * pre-set as the context. Mirrors `Operation::queue()`.
     */
    function queue(string $type, ?string $owner = null, ?array $data = null): void {
        $data ??= [];
        $data["card"] ??= $this->id;
        $data["reason"] ??= $this->op->getReason();
        $this->op->queue($type, $owner ?? $this->owner, $data);
    }

    /**
     * Single entry point called by Op_trigger when a trigger fires for this card.
     * Routes to on<TriggerName>() — e.g. trigger "enter" → onEnter().
     *
     * If the subclass does not implement the hook, falls back to onTriggerDefault().
     *
     * @param string $triggerName Trigger type, e.g. "enter", "roll", "actionAttack"
     */
    public function onTrigger(string $triggerName): void {
        $method = $this->getTriggerMethod($triggerName);
        if (!method_exists($this, $method)) {
            $this->onTriggerDefault($triggerName);
            return;
        }
        $this->$method();
    }

    public function onTriggerDefault(string $triggerName): void {}

    protected function getTriggerMethod(string $triggerName) {
        if (!$triggerName) {
            return "onManual";
        }
        $method = "on" . ucfirst($triggerName);
        return $method;
    }

    /**
     * Checks if card can be played with this trigger (empty string means can be play now)
     */
    public function canTrigger(string $triggerName): bool {
        $method = $this->getTriggerMethod($triggerName);
        if (method_exists($this, $method)) {
            if ($triggerName == "enter" && $this->op->getDataField("card", "") == $this->id) {
                return true;
            } else {
                return true;
            }
        }

        return false;
    }

    public function canBePlayed(string $triggerName, ?array &$errorRes = null): bool {
        if (!$errorRes) {
            $errorRes = [];
        }

        $errorRes = ["q" => 0];
        return true;
    }

    /**
     * Operation type used to "use" this card via player action — derived from
     * the card supertype: equip → useEquipment, ability/hero → useAbility,
     * event → playEvent. Asserts on unknown card types.
     */
    public function getUseCardOperationType(): string {
        $type = getPart($this->id, 1);
        $action = match ($type) {
            "equip" => "useEquipment",
            "ability", "hero" => "useAbility",
            "event" => "playEvent",
            default => null,
        };
        $this->game->systemAssert("ERR:Card:unknownType:" . $this->id, $action !== null);
        return $action;
    }
}
