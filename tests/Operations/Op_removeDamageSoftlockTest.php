<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Reproduces BGA #234859: gaining red crystals (heal / removeDamage) while there is
 * NO damage anywhere on the board dead-ends in a player state that offers zero
 * clickable targets and no Skip button ("[Error: No damage to remove] ... (3 left)").
 *
 * The op is mandatory (mcount > 0) and non-skippable, has no valid targets, and
 * cannot be resolved automatically, so top-level machine dispatch (dispatchOne ->
 * onEnteringGameState -> auto -> PlayerTurn) routes it to a stuck player state.
 */
final class Op_removeDamageSoftlockTest extends TestCase {
    private GameUT $game;
    private string $owner;
    private Operation $op;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->game->clearEquipDecks();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        // Hero on the map, hero card on the tableau (so getHeroTokenId resolves).
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . $this->owner);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    /**
     * documents buggy behavior for BGA #234859; flip once fixed
     *
     * Board has zero damage. A mandatory 3removeDamage (as produced by the
     * red-crystal / heal reward) should silently complete, but instead becomes a
     * targetless, unskippable, non-auto-resolvable op => softlock.
     */
    public function testGain3RemoveDamageWithNoDamageSoftlocks(): void {
        // Sanity: nothing on the board carries damage.
        $this->assertEquals(0, count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1")));

        $this->op = $this->game->machine->instantiateOperation("3removeDamage", $this->owner, null);

        $this->assertEquals(3, (int) $this->op->getCount(), "reward is 3 removeDamage, matching the '(3 left)' prompt");
        $this->assertTrue($this->op->noValidTargets(), "nothing to heal, so no valid targets");
        $this->assertCount(0, $this->op->getArgsTarget(), "player is shown zero clickable targets");
        $this->assertFalse($this->op->canSkip(), "BUG: op refuses to skip when there is nothing to remove");
        $this->assertFalse($this->op->canResolveAutomatically(), "BUG: engine cannot auto-dispatch, so it routes to a stuck player state");
    }
}
