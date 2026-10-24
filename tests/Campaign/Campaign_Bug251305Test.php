<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

use Bga\GameFramework\UserException;

/**
 * BGA #251305: with several prevention cards, once the incoming damage is fully prevented
 * the useCard prompt is re-queued anyway, offering nothing, and clicking a card errors.
 */
class Campaign_Bug251305Test extends CampaignBaseTest {
    public function testPromptRequeuedAfterDamageFullyPrevented(): void {
        $this->setupGame([1]);
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_$color", ["card_event_1_27_1", "card_event_1_27_2"]);
        $this->clearHand($color);
        $this->clearMonstersFromMap();

        $helmet = "card_equip_1_21";
        $tunic = "card_equip_1_23";
        $this->game->tokens->moveToken($helmet, "tableau_$color");
        $this->game->tokens->moveToken($tunic, "tableau_$color");
        $this->game->tokens->moveToken($heroId, "hex_7_9");
        $this->game->getMonster("monster_goblin_20")->moveTo("hex_7_8", "");
        $this->seedRand([5]); // goblin str=1 - one hit

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        $this->assertOperation("useCard");
        $this->assertValidTarget($helmet);
        $this->assertValidTarget($tunic);
        $this->respond($helmet);
        $this->assertEquals(0, $this->countDamage($heroId));

        // Pins buggy behavior for BGA #251305 - flip the assertion when the fix lands
        $this->assertOperation("useCard");
        $this->assertEmpty($this->getOpArgs()["target"] ?? []);
        $this->assertNotEquals(0, $this->getOpArgs()["info"][$tunic]["q"] ?? 0);
        $this->expectException(UserException::class);
        $this->respond($tunic);
    }
}
