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
     * Routes to on<TriggerName>() — e.g. trigger "enter" → onEnter().
     *
     * If the subclass does not implement the hook, falls back to onTriggerDefault().
     *
     * @param string $triggerName Trigger type, e.g. "enter", "roll", "actionAttack"
     */
    public function onTrigger(string $triggerName): void {
        if (!$this->canTriggerEffectOn($triggerName)) {
            return;
        }
        $method = $this->getTriggerMethod($triggerName);
        $this->callOnTriggerMethod($method, $triggerName);
    }

    /**
     * Invoke a hook method, optionally passing $triggerName as a named argument
     * if the method declares a parameter with that name.
     */
    protected function callOnTriggerMethod(string $method, string $triggerName): void {
        $ref = new \ReflectionMethod($this, $method);
        foreach ($ref->getParameters() as $param) {
            if ($param->getName() === "triggerName") {
                $this->$method(triggerName: $triggerName);
                return;
            }
        }
        $this->$method();
    }

    function checkPlayability($triggerName) {
        $errorRes = [];
        if (!$this->canBePlayed($triggerName, $errorRes)) {
            throw new UserException($errorRes["err"] ?? clienttranslate("Operation cannot be performed now"));
        }
    }

    protected function getTriggerMethod(string $triggerName) {
        if (!$triggerName) {
            return "onManual";
        }
        $method = "on" . ucfirst($triggerName);
        return $method;
    }

    /**
     * Checks if card can be played with this trigger (empty string means can be played now)
     * Can be triggered means it's a right trigger, but it may not be played which is another method
     */
    public function canTriggerEffectOn(string $triggerName): bool {
        $method = $this->getTriggerMethod($triggerName);
        if (method_exists($this, $method)) {
            if ($triggerName == "enter") {
                if ($this->op->getDataField("card", "") == $this->id) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if card can be played and sets the error code. The difference is UX - it's better
     * to explain to the user why the card cannot be played, sometimes it's subtle
     */
    public function canBePlayed(string $triggerName, ?array &$errorRes = null): bool {
        if (!$errorRes) {
            $errorRes = [];
        }

        if (!$this->canTriggerEffectOn($triggerName)) {
            $errorRes["q"] = Material::ERR_PREREQ;
            $errorRes["err"] = clienttranslate("Cannot be used now");
            return false;
        }

        $errorRes = array_merge($errorRes, ["q" => 0, "err" => ""]);
        return true;
    }

    public function useCard(string $triggerName) {
        $this->checkPlayability($triggerName);
        $cardId = $this->id;
        $hero = $this->game->getHero($this->getOwner());
        $effect = $this->game->material->getRulesFor($cardId, "effect", "");
        if ($this->isEvent()) {
            $message = clienttranslate('${char_name} plays event ${token_name}: ${effect_text}');
        } else {
            $message = clienttranslate('${char_name} uses ability of ${token_name}: ${effect_text}');
        }
        $this->game->notifyMessage($message, [
            "char_name" => $hero->getId(),
            "token_name" => $cardId,
            "effect_text" => $effect,
        ]);
        $r = $this->game->material->getRulesFor($cardId, "r", "nop");
        $this->queue($r, $this->getOwner(), ["card" => $cardId, "reason" => $cardId]);

        if ($this->isEvent()) {
            $hero->discardEventCard($cardId);
        } else {
            $on = $this->game->material->getRulesFor($cardId, "on", "");
            if (!$on) {
                //mark card as used, as these can only be used once per turn
                $this->setUsed(true);
            }
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
