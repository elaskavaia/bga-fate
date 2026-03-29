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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * resolveHits: Read dice from display_battle, count hits, then queue dealDamage.
 * Data fields: attacker (heroId), target (hexId).
 * Defender is derived from target hex.
 * Runs automatically (no user interaction).
 */
class Op_resolveHits extends Operation {
    function resolve(): void {
        $attackerId = $this->getDataField("attacker");
        $targetHex = $this->getDataField("target");
        $this->game->systemAssert("ERR:resolveHits:missingAttacker", $attackerId !== null);
        $this->game->systemAssert("ERR:resolveHits:missingTarget", $targetHex !== null);

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:resolveHits:noCharOnHex:$targetHex", $defenderId !== null);

        $hits = $this->game->effect_resolveHits($attackerId, $defenderId);

        // Armor absorbs hits (e.g. Draugr armor=1)
        $defender = $this->game->getCharacter($defenderId);
        $hits = $defender->applyArmor($hits);

        if ($hits > 0) {
            // Trigger damage prevention reactions for the defending hero
            $defenderOwner = str_starts_with($defenderId, "hero_") ? $this->game->getHeroOwner($defenderId) : null;
            if ($defenderOwner !== null) {
                $this->queueTrigger(null, $defenderOwner, ["target" => $defenderId]);
            }

            $this->queue("dealDamage", null, [
                "attacker" => $attackerId,
                "target" => $targetHex,
                "count" => $hits,
            ]);
        } else {
            $this->game->notifyMessage(clienttranslate('${char_name} attack missed!'), [
                "char_name" => $attackerId,
            ]);
        }
    }
}
