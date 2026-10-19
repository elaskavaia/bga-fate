<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #251077: demote reveals the next card of the pile, so it must be an undo barrier.
 */
class Campaign_DemoteUndoTest extends CampaignBaseTest {
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->color = $this->getActivePlayerColor();
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1", "card_event_1_27_2"]);
    }

    /** Documents buggy behavior for BGA #251077 - flip the final asserts when the fix lands. */
    public function testUndoAfterDemoteRewindsPastTheRevealedCard(): void {
        $deck = "deck_ability_$this->color";
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip();
        for ($i = 0; $i < 6 && $this->opType() !== "drawEvent"; $i++) {
            $this->skip();
        }
        $this->respond("confirm");
        $this->assertSame("demote", $this->opType());

        $demoted = $this->game->tokens->getTokenOnTop($deck)["key"];
        $this->respond($demoted);
        $revealed = $this->game->tokens->getTokenOnTop($deck)["key"];
        $this->assertNotSame($demoted, $revealed, "demote revealed a new top ability");

        $this->game->_setCurrentPlayerId((int) $this->game->getActivePlayerId());
        $this->driver->runStep("action_undo", ["move_id" => 0]);
        for ($i = 0; $i < 5 && $this->opType() === ""; $i++) {
            $this->driver->runDispatchLoop();
        }

        // BUG: undo reaches back past the reveal - the player has seen $revealed and gets to choose again.
        $this->assertSame($demoted, $this->game->tokens->getTokenOnTop($deck)["key"], "undo put the demoted card back on top");
        $this->assertSame("demote", $this->opType(), "and offers the demote again");
    }

    private function opType(): string {
        return $this->getOpArgs()["type"] ?? "";
    }
}
