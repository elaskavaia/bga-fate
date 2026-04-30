<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Stubs\GameUT;

/**
 * Tests for Op_completeQuest — the player-initiated free action that surfaces
 * the deck-top equipment card when its quest_r is reachable via Trigger::Manual
 * (i.e. quest_on is empty). Mirrors Op_useCardTest's shape.
 */
final class Op_completeQuestTest extends AbstractOpTestCase {
    /** card_equip_3_23 = Leg Guards (Embla, quest_on=, quest_r=spendAction(actionFocus):gainEquip). */
    private string $manualQuestCard = "card_equip_3_23";
    /** card_equip_2_22 = Belt of Youth (Alva, quest_on=TStep) — cannot be claimed manually. */
    private string $triggerQuestCard = "card_equip_2_22";

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(3); // Embla — Leg Guards lives in her deck
        $this->game->clearHand();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->createOp();
    }

    private function deckLocation(): string {
        return "deck_equip_" . $this->owner;
    }

    /** Force a specific card to the top of deck_equip_{owner}. */
    private function seedDeckTop(string $cardId): void {
        $this->game->tokens->moveToken($cardId, $this->deckLocation(), 9999);
    }

    public function testEmptyDeckHasNoTargets(): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card_equip", $this->deckLocation())) as $key) {
            $this->game->tokens->moveToken($key, "limbo");
        }
        $this->createOp();
        $this->assertNoValidTargets();
    }

    public function testCardWithoutQuestRIsDisabled(): void {
        // card_equip_3_15 = Flimsy Blade (starting equipment, no quest_r).
        $noQuest = "card_equip_3_15";
        $this->seedDeckTop($noQuest);
        $this->createOp();
        $this->assertTargetError($noQuest, Material::ERR_PREREQ);
    }

    public function testTriggerOnlyQuestIsDisabledForManual(): void {
        // Belt of Youth has quest_on=TStep — should not be claimable via Manual.
        $this->seedDeckTop($this->triggerQuestCard);
        $this->createOp();
        $this->assertTargetError($this->triggerQuestCard, Material::ERR_PREREQ);
    }

    public function testManualQuestIsValidTarget(): void {
        $this->seedDeckTop($this->manualQuestCard);
        $this->createOp();
        $this->assertValidTarget($this->manualQuestCard);
        $this->assertValidTargetCount(1);
    }

    public function testCanSkipIsTrue(): void {
        $this->seedDeckTop($this->manualQuestCard);
        $this->createOp();
        $this->assertTrue($this->op->canSkip(), "completeQuest is a voluntary free action");
    }

    public function testResolveQueuesQuestR(): void {
        $this->seedDeckTop($this->manualQuestCard);
        $this->createOp();
        $this->call_resolve($this->manualQuestCard);
        $pending = $this->game->machine->getTopOperations($this->owner);
        $this->assertNotEmpty($pending, "quest_r chain should be queued onto the machine");
    }
}
