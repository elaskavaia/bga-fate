<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;

/**
 * Pins the intended behaviour of a VOID Mend (nothing eligible has damage).
 *
 * Mend must stay void when it cannot do anything: the turn menu must not offer it,
 * and Op_or (e.g. Sophisticated's choice) must mark that sub-choice ERR_NOT_APPLICABLE
 * so it is unselectable in the UI. Force-picking the disabled choice anyway must be
 * rejected server-side (UserException from checkUserTargetSelection) - that throw is
 * validation, not a crash. A "never-void wasted Mend" fix was considered and rejected.
 *
 * Setup is in Grimheim (5removeDamage delegate); the non-Grimheim void case is covered
 * by Op_actionMendTest::testMendNotAvailableWithZeroDamage.
 */
final class Op_actionMendVoidTest extends AbstractOpTestCase {
    protected function setUp(): void {
        // Skip AbstractOpTestCase::init (would instantiate a non-existent op named after this class).
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . $this->owner);
        $this->game->tokens->moveToken("hero_1", "hex_9_9"); // Grimheim
    }

    private function damage(string $tokenId, int $n): void {
        $this->game->effect_moveCrystals($tokenId, "red", $n, $tokenId, ["message" => ""]);
    }

    /** Instantiate the Sophisticated choice op (Op_or) as it is presented to the player. */
    private function makeSophisticatedChoice(): Operation {
        $or = $this->game->machine->instantiateOperation(
            "actionMend/actionFocus/actionPrepare/actionPractice",
            $this->owner,
            ["card" => "card_event_3_29", "reason" => "card_event_3_29", "event" => "TManual"]
        );
        $or->withDataField("l_confirm", "true");
        $or->saveToDb(1, true);
        return $this->game->machine->createTopOperationFromDbForOwner();
    }

    public function testSophisticatedDisablesMendWhenNothingDamaged(): void {
        $moves = $this->makeSophisticatedChoice()->getPossibleMoves();
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["choice_0"]["q"], "void Mend choice must be marked not applicable");
        $this->assertNotEmpty($moves["choice_0"]["err"], "void Mend choice must carry an error for the UI");
    }

    /** The UI disables the choice; a forced pick must be rejected server-side, not resolved. */
    public function testForcedVoidMendChoiceIsRejected(): void {
        $or = $this->makeSophisticatedChoice();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->game->fakeUserAction($or, "choice_0"); // Mend
        $this->game->machine->dispatchAll();
    }

    /** Grimheim variant of Op_actionMendTest::testMendNotAvailableWithZeroDamage. */
    public function testMendFromTurnMenuVoidWhenNothingDamaged(): void {
        $mend = $this->game->machine->instantiateOperation("actionMend", $this->owner, []);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $mend->getErrorCode(), "Mend with nothing to repair must be void");
    }

    /** Regression guard: the normal path (hero has damage) still repairs and drains cleanly. */
    public function testMendViaSophisticatedRemovesHeroDamage(): void {
        $this->damage("hero_1", 3);
        $before = $this->countRedCrystals("hero_1");
        $this->assertSame(3, $before);

        $or = $this->makeSophisticatedChoice();
        $this->game->fakeUserAction($or, "choice_0"); // Mend
        $this->game->machine->dispatchAll();

        // Drain any interactive removeDamage by repeatedly picking the hero hex.
        for ($i = 0; $i < 10; $i++) {
            $top = $this->game->machine->createTopOperationFromDbForOwner();
            if ($top === null) {
                break;
            }
            $valid = $top->getArgsTarget();
            if (count($valid) === 0) {
                $this->game->machine->dispatchAll();
                continue;
            }
            $this->game->fakeUserAction($top, $valid[0]);
            $this->game->machine->dispatchAll();
        }

        $this->assertSame(0, $this->countRedCrystals("hero_1"), "hero damage should be fully removed");
    }
}
