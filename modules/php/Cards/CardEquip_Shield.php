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

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;
use Bga\Games\Fate\Model\Trigger;

use function Bga\Games\Fate\getPart;

/**
 * Shield (card_equip_4_16) — bespoke quest with two OR-branches:
 *   A. Enter Ogre Valley                  → auto-claim
 *   B. Skip the gold (XP) from a troll kill → optional yes/no claim
 *
 * Both branches stay live until the card is claimed; declining a troll kill
 * doesn't disable branch A, and stepping outside Ogre Valley doesn't disable B.
 *
 * CSV has `quest_on=custom` and empty `quest_r` so the default `triggerQuest`
 * never matches — we route everything through this override.
 */
class CardEquip_Shield extends CardGeneric {
    public function triggerQuest(Trigger $event): void {
        $chain = $event->chain();

        // Branch A — ActionMove/Move chain through Step, so any hero hex entry triggers this.
        if (in_array(Trigger::Step, $chain, true)) {
            $heroHex = $this->game->hexMap->getCharacterHex($this->game->getHeroTokenId($this->owner));
            if ($heroHex !== null && $this->game->hexMap->getHexNamedLocation($heroHex) === "OgreValley") {
                $this->queue("gainEquip");
                return;
            }
        }

        // Branch B — troll kill prompts an optional ?(blockXp:gainEquip) chain.
        if (in_array(Trigger::MonsterKilled, $chain, true)) {
            $hex = $this->game->getAttackHex();
            if ($hex === null) {
                return;
            }
            $monsterId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($monsterId === null || getPart($monsterId, 1) !== "troll") {
                return;
            }
            $this->queue("?(blockXp:gainEquip)");
        }
    }
}
