<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #255804: a page load during the final monster turn shows the heroes-win banner before the turn is over. */
class Campaign_Bug255804Test extends CampaignBaseTest {
    public function testWinBannerIsSentBeforeTheFinalMonsterTurnEnds(): void {
        $this->setupGame([1]);
        $this->clearMonstersFromMap();
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 11); // step 12 (last) after the advance

        $this->game->machine->instantiateOperation("turnMonster", $this->game->getAutomaColor())->resolve();

        $this->assertSame(12, $this->game->tokens->getTokenState("rune_stone"), "sanity: marker on the last spot");
        $this->assertNotNull($this->game->machine->findOperation(null, "monsterAttackAll"), "sanity: monsters have not attacked yet");
        $this->assertNotNull($this->game->machine->findOperation(null, "endOfMonsterTurn"), "sanity: monster turn not finished");
        $this->assertFalse($this->game->isWellDestroyed(), "sanity: the Well stands");

        $gamedatas = $this->game->getAllDatas();

        // Documents buggy behavior for BGA #255804 - flip the assertions when the fix lands
        $this->assertTrue($gamedatas["gameEnded"]);
        $this->assertArrayHasKey("endBanner", $gamedatas);
        $this->assertFalse($gamedatas["endBanner"]["isWellDestroyed"]);
    }
}
