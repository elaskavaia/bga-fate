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
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * removeDamage: remove 1 damage (red crystal) from a target hero or equipment card.
 *
 * Targets are picked one at a time — when count > 1, the op resolves one unit and
 * re-queues itself with count-1, so the player may split between targets. This is
 * the engine for the rule "remove X damage from heroes and/or equipment".
 *
 * Params (param 0):
 *  - ""       — acting hero OR any card on tableau (default for actionMend in Grimheim, encounters)
 *  - "self"   — acting hero only
 *  - "adj"    — heroes within range 1 (including self) — does NOT include cards
 *  - "max"    — pick a single target, remove ALL damage from it (count multiplies the picks:
 *               2removeDamage(max) prompts twice, all damage stripped from each pick)
 *  - "all"    — single confirm; remove damage from EVERY eligible target at once.
 *               No per-target prompt. Count = max damage to remove per target.
 *               At the parent level both acting hero and own cards are eligible;
 *               subclasses narrow this (Op_heal: heroes only, Op_repairCard: cards only).
 *
 * Subclassed by Op_heal (heroes only) and Op_repairCard (cards only) for tighter
 * compatibility with existing card text.
 */
class Op_removeDamage extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose a hero or card to remove damage from");
    }

    protected function getMode(): string {
        return $this->getParam(0, "");
    }

    protected function allowsHeroes(): bool {
        return in_array($this->getMode(), ["", "self", "adj", "all"], true);
    }

    protected function allowsCards(): bool {
        return in_array($this->getMode(), ["", "max", "all"], true);
    }

    protected function isAllMode(): bool {
        return $this->getMode() === "all";
    }

    /**
     * Returns [targetKey => tokenId] for every eligible target under the current mode.
     * Heroes are keyed by hex (so the client clicks the hex to select a hero); cards by id.
     */
    protected function getCandidates(): array {
        $owner = $this->getOwner();
        $candidates = [];

        if ($this->allowsHeroes()) {
            $heroId = $this->game->getHeroTokenId($owner);
            if ($this->getMode() === "adj") {
                $heroHex = $this->game->hexMap->getCharacterHex($heroId);
                $hexes = array_merge([$heroHex], $this->game->hexMap->getHexesInRange($heroHex, 1));
                foreach ($hexes as $hex) {
                    $other = $this->game->hexMap->getCharacterOnHex($hex, "hero");
                    if ($other) {
                        $candidates[$hex] = $other;
                    }
                }
            } else {
                $candidates[$this->game->hexMap->getCharacterHex($heroId)] = $heroId;
            }
        }

        if ($this->allowsCards()) {
            foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner")) as $cardId) {
                $candidates[$cardId] = $cardId;
            }
        }

        return $candidates;
    }

    private function damageOn(string $tokenId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $tokenId));
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        if ($this->isAllMode()) {
            return $this->hasAnyDamageLeft()
                ? ["confirm"]
                : ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No damage to remove")];
        }
        $targets = [];
        foreach ($this->getCandidates() as $key => $tokenId) {
            $targets[$key] =
                $this->damageOn($tokenId) > 0
                    ? ["q" => Material::RET_OK]
                    : ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No damage to remove")];
        }
        return $targets;
    }

    function resolve(): void {
        $heroId = $this->game->getHeroTokenId($this->getOwner());

        if ($this->isAllMode()) {
            $perTarget = (int) $this->getCount();
            foreach ($this->getCandidates() as $tokenId) {
                $this->removeFrom($heroId, $tokenId, $perTarget);
            }
            return;
        }

        $targetKey = $this->getCheckedArg();
        $candidates = $this->getCandidates();
        $tokenId = $candidates[$targetKey] ?? null;
        $this->game->systemAssert("ERR:removeDamage:noToken:$targetKey", $tokenId !== null);

        // Singleton damaged candidate → apply full count in one shot (no re-queue, no split prompt).
        $singleChoice = $this->isOneChoice();
        $count = (int) $this->getCount();
        $perUnit = $this->getMode() === "max" ? $this->damageOn($tokenId) : ($singleChoice ? $count : 1);
        $this->removeFrom($heroId, $tokenId, $perUnit);

        // Re-queue the remainder when more damaged targets exist.
        // Preserve the op type (heal/repairCard/removeDamage) so subclass restrictions stay.
        $remainingCount = $count - $perUnit;
        if ($remainingCount > 0 && !$singleChoice) {
            // re-queue itself with lower count
            $this->withDataField("count", $remainingCount);
            $this->withDataField("mcount", $remainingCount);
            $this->queueOp($this);
        }
    }

    private function hasAnyDamageLeft(): bool {
        foreach ($this->getCandidates() as $tokenId) {
            if ($this->damageOn($tokenId) > 0) {
                return true;
            }
        }
        return false;
    }

    private function removeFrom(string $actingHeroId, string $tokenId, int $amount): void {
        $damage = $this->damageOn($tokenId);
        $amount = min($amount, $damage);
        if ($amount <= 0) {
            return;
        }
        $isHero = str_starts_with($tokenId, "hero_");
        $message = $isHero
            ? clienttranslate('${char_name} heals ${count} [DAMAGE] from ${token_name}')
            : clienttranslate('${char_name} repairs ${count} [DAMAGE] from ${token_name}');
        $this->game->effect_moveCrystals($actingHeroId, "red", -$amount, $tokenId, [
            "message" => $message,
            "token_name" => $tokenId,
        ]);
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false; // mandatory if possible
    }

    public function getUiArgs() {
        // Cards are clicked directly; heroes via their hex. No buttons.
        return ["buttons" => false];
    }
}
