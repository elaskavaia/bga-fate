<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Stubs\GameUT;

/**
 * Regression pin for the reported "Sophisticated: Mend server error" scenario (still
 * unreproduced): hero with 4 damage moves into Grimheim via a REAL move op (home-hex
 * relocation + move triggers, not a teleport), then plays Sophisticated and picks Mend.
 * This clean single-hero variant resolves fine; the real-game crash needs an extra
 * ingredient (see TODO.md).
 */
final class Op_actionMendGrimheimMoveTest extends AbstractOpTestCase {
    protected function setUp(): void {
        // Skip AbstractOpTestCase::init (would instantiate a non-existent op named after this class).
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . $this->owner);
        $this->game->tokens->moveToken("hero_1", "hex_11_8"); // outside, adjacent to Grimheim hex_10_8
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testMendViaSophisticatedAfterMovingIntoGrimheim(): void {
        $this->game->effect_moveCrystals("hero_1", "red", 4, "hero_1", ["message" => ""]);

        // Real move op into Grimheim: relocates hero to their home hex and fires move triggers.
        $this->game->machine->push("move", $this->owner, ["target" => "hex_10_8"]);
        $this->game->machine->dispatchAll();
        $heroHex = $this->game->tokens->getTokenLocation("hero_1");
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex), "hero should be in Grimheim, got $heroHex");
        $this->assertSame(4, $this->countRedCrystals("hero_1"));

        // Play Sophisticated: the Op_or choice as presented to the player.
        $or = $this->game->machine->instantiateOperation(
            "actionMend/actionFocus/actionPrepare/actionPractice",
            $this->owner,
            ["card" => "card_event_3_29", "reason" => "card_event_3_29", "event" => "TManual"]
        );
        $or->withDataField("l_confirm", "true");
        $or->saveToDb(1, true);
        $or = $this->game->machine->createTopOperationFromDbForOwner();

        $moves = $or->getPossibleMoves();
        $this->assertSame(Material::RET_OK, $moves["choice_0"]["q"] ?? null, "Mend must be offered (hero has 4 damage)");

        $this->game->fakeUserAction($or, "choice_0"); // Mend - must not throw
        $this->game->machine->dispatchAll();

        // Drain any interactive removeDamage by repeatedly picking the first valid target.
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

        $this->assertSame(0, $this->countRedCrystals("hero_1"), "Mend in Grimheim should remove all 4 damage");
    }
}
