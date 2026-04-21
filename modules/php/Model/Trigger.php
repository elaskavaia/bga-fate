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
 * Published game triggers that cards can react to via on<TriggerName>() hooks.
 *
 * The case `name` (e.g. "ActionAttack") is used by Card::getTriggerMethod() to derive
 * the hook method name ("onActionAttack"). The case `value` (e.g. "TActionAttack")
 * is the wire format used in CSV `on` columns and serialized op-machine expressions
 * like trigger(TActionAttack).
 *
 * The `T` prefix exists to disambiguate trigger names from operation type names,
 * which share the same string namespace (e.g. both an Op_actionAttack operation
 * and an actionAttack-flavored trigger).
 */
enum Trigger: string {
    case ActionAttack = "TActionAttack";
    case AfterActionAttack = "TAfterActionAttack";
    case ActionMove = "TActionMove"; // move with action move
    case Move = "TMove"; // move (target only, i.e. last step)
    case Step = "TStep"; // every step of the move
    case Roll = "TRoll";
    case ResolveHits = "TResolveHits";
    case TurnEnd = "TTurnEnd";
    case TurnStart = "TTurnStart";
    case MonsterMove = "TMonsterMove";
    case MonsterKilled = "TMonsterKilled";
    case CardEnter = "TCardEnter";
    /**
     * Synthetic "event" representing manual activation from the useCard free-action
     * prompt. Never published via Op_trigger and never appears in the CSV `on` column —
     * it exists so that `getTriggerMethod(Trigger::Manual)` derives the `onManual` hook
     * the same way real events derive `onRoll`, `onActionAttack`, etc.
     */
    case Manual = "TManual";

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
            self::Move => self::Step, // Move is a specialization of Step — the final step
            default => null,
        };
    }

    /**
     * Returns the trigger chain from most-specific to least-specific.
     * For ActionAttack: [ActionAttack, Roll]. For a root trigger: [self].
     *
     * Used by card-matching (CardGeneric::canTriggerEffectOn) and the on(...) gate
     * (Op_on) to resolve a dispatched trigger against a card's `on` field or an
     * r-expression guard. A card with `on=TRoll` matches a dispatched ActionAttack
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
