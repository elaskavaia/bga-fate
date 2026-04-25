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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * resolveHits: Read dice from display_battle, count hits, then queue dealDamage.
 *
 * Data fields:
 *   - attacker  (heroId)
 *   - target    (primary hex)
 *   - secondary (optional hex) — when set, splits damage between primary and secondary.
 *                                Set by Op_c_reaper (Reaper Swing).
 *
 * Defender is derived from target hex.
 *
 * Auto-resolves in single-target mode. With `secondary` set, prompts the player to
 * choose how many of the total hits go to the primary target (rest go to secondary).
 */
class Op_resolveHits extends Operation {
    private function getAttackerId(): string {
        return (string) $this->getDataField("attacker", "");
    }

    private function getPrimaryHex(): string {
        return (string) $this->getDataField("target", "");
    }

    private function getSecondaryHex(): string {
        return $this->getDataField("secondary", "");
    }

    /** Returns 0 when the op is being inspected without runtime data (e.g. smoke instantiation). */
    private function computeHits(): int {
        $primaryHex = $this->getPrimaryHex();
        if ($primaryHex === "") {
            return 0;
        }
        $defenderId = $this->game->hexMap->getCharacterOnHex($primaryHex);
        $this->game->systemAssert("ERR:resolveHits:noCharOnHex:$primaryHex", $defenderId !== null);
        return $this->game->countHits($this->getAttackerId(), $defenderId);
    }

    function getPrompt() {
        if ($this->getSecondaryHex()) {
            return clienttranslate('How many of ${total} damage go to the primary target?');
        }
        return "";
    }

    function getExtraArgs() {
        return ["total" => $this->computeHits()];
    }

    function getPossibleMoves() {
        if (!$this->getSecondaryHex()) {
            return parent::getPossibleMoves();
        }
        $total = $this->computeHits();
        $targets = [];
        for ($i = 0; $i <= $total; $i++) {
            $targets["choice_$i"] = [
                "q" => Material::RET_OK,
                "name" => "$i", // damage to primary target
            ];
        }
        return $targets;
    }

    function resolve(): void {
        $attackerId = $this->getAttackerId();
        $primaryHex = $this->getPrimaryHex();
        $this->game->systemAssert("ERR:resolveHits:missingAttacker", $attackerId !== "");
        $this->game->systemAssert("ERR:resolveHits:missingTarget", $primaryHex !== "");
        $secondaryHex = $this->getSecondaryHex();
        $hits = $this->getArgs()["total"] ?? 0;
        if (!$secondaryHex) {
            $this->queueDamage($attackerId, $primaryHex, $hits);
            return;
        }

        $choice = (string) $this->getCheckedArg();

        $primaryHits = (int) getPart($choice, 1);

        $secondaryHits = $hits - $primaryHits;

        $this->queueDamage($attackerId, $primaryHex, $primaryHits);
        $this->queueDamage($attackerId, $secondaryHex, $secondaryHits);
    }

    /** Queue a dealDamage for one half of a split. Always queues (even 0 hits) so per-defender side effects fire. */
    private function queueDamage(string $attackerId, string $targetHex, int $hits): void {
        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:resolveHits:noCharOnHex:$targetHex", $defenderId !== null);

        if ($hits <= 0) {
            $this->game->notifyMessage(clienttranslate('${char_name}\'s attack missed!'), [
                "char_name" => $attackerId,
            ]);
            $hits = 0;
        } else {
            $defender = $this->game->getCharacter($defenderId);
            $hits = $defender->applyArmor($hits);
            if ($hits <= 0) {
                $this->game->notifyMessage(clienttranslate('${char_name}\'s attack was fully absorbed by armor'), [
                    "char_name" => $attackerId,
                ]);
            }
        }

        $defenderOwner = str_starts_with($defenderId, "hero_") ? $this->game->getHeroOwner($defenderId) : null;
        if ($defenderOwner !== null) {
            $this->queueTrigger(Trigger::ResolveHits, $defenderOwner, ["target" => $defenderId]);
        }
        $this->queue("dealDamage", null, [
            "attacker" => $attackerId,
            "target" => $targetHex,
            "count" => $hits,
            "card" => $this->getDataField("card", ""),
        ]);
    }
}
