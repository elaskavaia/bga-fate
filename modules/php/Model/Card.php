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

use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\Material;
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
    protected string $id;
    protected ?int $state;
    protected ?string $location;
    public function __construct(protected Game $game, string|array $cardOrId, protected Operation $op) {
        $this->owner = $op->getOwner();
        if (is_array($cardOrId)) {
            $this->id = $cardOrId["key"];
            $this->location = $cardOrId["location"];
            $this->state = (int) $cardOrId["state"];
        } else {
            $this->id = $cardOrId;
            $this->state = null;
            $this->location = null;
        }
    }

    function getId(): string {
        return $this->id;
    }

    function getOwner(): string {
        return $this->owner;
    }

    function getState(): int {
        if ($this->state !== null) {
            return (int) $this->state;
        }
        // lazy init
        $info = $this->game->tokens->getTokenInfo($this->id);
        $this->location = $info["location"];
        $this->state = (int) $info["state"];
        return $this->state;
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
     * Routes to on<TriggerName>() — e.g. Trigger::CardEnter → onCardEnter().
     *
     * Walks the trigger chain most-specific → least-specific and calls the first
     * matching hook. This preserves the contract that a bespoke card defining
     * onRoll still fires during an attack roll (dispatched as ActionAttack, whose
     * chain contains Roll).
     */
    public function onTrigger(Trigger $event): void {
        if (!$this->canTriggerEffectOn($event)) {
            return;
        }
        foreach ($event->chain() as $t) {
            $method = $this->getTriggerMethod($t);
            if (method_exists($this, $method)) {
                $this->callOnTriggerMethod($method, $event);
                return;
            }
        }
    }

    /**
     * Invoke a hook method, optionally passing $event as a named argument
     * if the method declares a parameter with that name.
     */
    protected function callOnTriggerMethod(string $method, Trigger $event): void {
        $ref = new \ReflectionMethod($this, $method);
        foreach ($ref->getParameters() as $param) {
            if ($param->getName() === "event") {
                $this->$method(event: $event);
                return;
            }
        }
        $this->$method();
    }

    function checkPlayability(Trigger $event) {
        $errorRes = [];
        if (!$this->canBePlayed($event, $errorRes)) {
            throw new UserException($errorRes["err"] ?? clienttranslate("Operation cannot be performed now"));
        }
    }

    /**
     * Derive the on<TriggerName>() hook method name from a Trigger case.
     * Uses the case `name` (e.g. Trigger::ActionAttack → "ActionAttack") so the hook
     * derivation does not depend on the `T` prefix in the wire-format value.
     */
    protected function getTriggerMethod(Trigger $event): string {
        return "on" . $event->name;
    }

    /**
     * Returns true if this card has a hook for the given event (and any extra
     * preconditions on the event are met). Walks the trigger chain — a card with
     * an onRoll hook returns true for a dispatched ActionAttack (Roll is in chain).
     */
    public function canTriggerEffectOn(Trigger $event): bool {
        foreach ($event->chain() as $t) {
            $method = $this->getTriggerMethod($t);
            if (method_exists($this, $method)) {
                if ($t === Trigger::CardEnter) {
                    // Lifecycle event only fires for the card that just entered play.
                    return $this->op->getDataField("card", "") == $this->id;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if card can be played and sets the error code. The difference is UX - it's better
     * to explain to the user why the card cannot be played, sometimes it's subtle
     */
    public function canBePlayed(Trigger $event, ?array &$errorRes = null): bool {
        if (!$errorRes) {
            $errorRes = [];
        }

        if (!$this->canTriggerEffectOn($event)) {
            $errorRes["q"] = Material::ERR_PREREQ;
            $errorRes["err"] = clienttranslate("Cannot be used now");
            return false;
        }

        $errorRes = array_merge($errorRes, ["q" => 0, "err" => ""]);
        return true;
    }

    public function useCard(Trigger $event) {
        $this->checkPlayability($event);
        $cardId = $this->id;
        $hero = $this->game->getHero($this->getOwner());

        if ($this->isEvent()) {
            $message = clienttranslate('${char_name} plays event ${token_name}');
        } else {
            $message = clienttranslate('${char_name} activates ${token_name}');
        }
        $this->game->notifyMessage($message, [
            "char_name" => $hero->getId(),
            "token_name" => $cardId,
        ]);

        $op = $this->createOperationForCardEffect($event);
        $this->op->queueOp($op);

        if ($this->isEvent()) {
            $hero->discardEventCard($cardId);
        }
    }

    function createOperationForCardEffect($event) {
        $cardId = $this->id;
        $r = $this->game->material->getRulesFor($cardId, "r", "nop");
        $op = $this->op->instantiateOperation($r, $this->getOwner(), [
            "card" => $cardId,
            "reason" => $cardId,
            "event" => $event->value,
        ]);
        $op->withDataField("l_confirm", "true"); // do not auto-resolve single choice
        if (!$this->isEvent()) {
            $op->withDataField("l_skip", "true");
        }
        return $op;
    }

    function promptUseCard(Trigger $event) {
        $owner = $this->getOwner();
        $action = "useCard";

        $alreadyOp = $this->game->machine->findOperation($owner, $action);
        if (!$alreadyOp) {
            $this->queue($action, null, ["l_confirm" => true, "on" => [$event->value]]);
        } else {
            $op = $this->game->machine->instantiateOperationFromDbRow($alreadyOp);
            $onarr = $op->getDataField("on", []);
            if (in_array($event->value, $onarr)) {
                return;
            }
            $onarr[] = $event->value;
            $op->withDataField("on", $onarr);
            $this->game->machine->db->updateData($op->getId(), $op->getDataForDb());
        }
    }

    function isEvent(): bool {
        return str_starts_with($this->id, "card_event");
    }

    function isUsed() {
        return $this->getState() == 1;
    }
    function setUsed(bool $used) {
        $state = $used ? 1 : 0;
        $this->op->dbSetTokenState($this->id, $state, "");
        $this->state = $state;
    }
}
