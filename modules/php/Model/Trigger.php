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

/**
 * Published game events that cards can react to via on<EventName>() hooks.
 *
 * The case `name` (e.g. "ActionAttack") is used by Card::getTriggerMethod() to derive
 * the hook method name ("onActionAttack"). The case `value` (e.g. "EventActionAttack")
 * is the wire format used in CSV `on` columns and serialized op-machine expressions
 * like trigger(EventActionAttack).
 *
 * The `Event` prefix exists to disambiguate event names from operation type names,
 * which previously shared the same string namespace (e.g. both an Op_actionAttack
 * operation and an "actionAttack" trigger).
 */
enum Trigger: string {
    case ActionAttack = "EventActionAttack";
    case ActionMove = "EventActionMove"; // move with action move
    case Move = "EventMove"; // any move
    case Roll = "EventRoll";
    case ResolveHits = "EventResolveHits";
    case TurnEnd = "EventTurnEnd";
    case TurnStart = "EventTurnStart";
    case MonsterMove = "EventMonsterMove";
    case MonsterKilled = "EventMonsterKilled";
    case Enter = "EventEnter";
    /**
     * Synthetic "event" representing manual activation from the useCard free-action
     * prompt. Never published via Op_trigger and never appears in the CSV `on` column —
     * it exists so that `getTriggerMethod(Trigger::Manual)` derives the `onManual` hook
     * the same way real events derive `onRoll`, `onActionAttack`, etc.
     */
    case Manual = "EventManual";

    /**
     * Parent trigger, or null if this trigger is a root. A parent relationship means
     * "this trigger is a more specific flavor of its parent" — e.g. an ActionAttack is
     * a Roll that came from an attack action. A card listening on the parent fires for
     * its children too; a card listening on a child does not fire for the parent alone.
     */
    public function parent(): ?self {
        return match ($this) {
            self::ActionAttack => self::Roll,
            self::ActionMove => self::Move,
            default => null,
        };
    }

    /**
     * Returns the trigger chain from most-specific to least-specific.
     * For ActionAttack: [ActionAttack, Roll]. For a root trigger: [self].
     *
     * Used by card-matching (CardGeneric::canTriggerEffectOn) and the on(...) gate
     * (Op_on) to resolve a dispatched trigger against a card's `on` field or an
     * r-expression guard. A card with `on=EventRoll` matches a dispatched ActionAttack
     * because Roll is in the ActionAttack chain.
     *
     * @return self[]
     */
    public function chain(): array {
        $out = [];
        $t = $this;
        while ($t !== null) {
            $out[] = $t;
            $t = $t->parent();
        }
        return $out;
    }
}
