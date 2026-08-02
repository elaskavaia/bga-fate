<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the fix for BGA #234859: gaining red crystals (heal / removeDamage) while
 * there is NO damage anywhere on the board used to dead-end in a player state with
 * zero clickable targets and no Skip button ("[Error: No damage to remove] ... (3 left)").
 *
 * Op_removeDamage::canSkip now returns true when there are no valid targets, so the
 * op auto-skips during dispatch instead of routing to a stuck player state.
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
     * Board has zero damage. A mandatory 3removeDamage (as produced by the
     * red-crystal / heal reward) silently completes: skippable-when-empty, so the
     * engine auto-dispatches it instead of parking a targetless player state.
     */
    public function testGain3RemoveDamageWithNoDamageAutoSkips(): void {
        // Sanity: nothing on the board carries damage.
        $this->assertEquals(0, count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1")));

        $this->op = $this->game->machine->instantiateOperation("3removeDamage", $this->owner, null);

        $this->assertEquals(3, (int) $this->op->getCount(), "reward is 3 removeDamage, matching the '(3 left)' prompt");
        $this->assertTrue($this->op->noValidTargets(), "nothing to heal, so no valid targets");
        $this->assertCount(0, $this->op->getArgsTarget(), "no clickable targets exist");
        $this->assertTrue($this->op->canSkip(), "empty removeDamage is skippable");
        $this->assertTrue($this->op->canResolveAutomatically(), "engine auto-dispatches the skip, no stuck player state");
    }
}
