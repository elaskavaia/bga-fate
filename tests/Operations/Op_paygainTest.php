<?php

declare(strict_types=1);

/**
 * Pins the empty-gain hardening pair (no BGA report; hardening after the #235445 investigation):
 * - Op_gainMana::canSkip: a gain with no valid targets is skippable, so standalone it
 *   auto-skips instead of softlocking (the #234859 removeDamage shape).
 * - Op_paygain::getPossibleMoves checks noValidTargets, not isVoid, so a skippable-empty
 *   gain still withholds the whole chain - no paying for a gain that would silently skip.
 *
 * Requires a tableau where NO card carries the mana icon (gainMana targets cards by the
 * `mana` material field; crystal counts are irrelevant, so spending mana does not affect
 * this). Normal play cannot reach that: every hero starts with a mana-icon card and
 * abilities never leave the tableau - hence no campaign coverage.
 */
final class Op_paygainTest extends AbstractOpTestCase {
    private const HERO_CARD = "card_hero_1_1";

    protected function setUp(): void {
        parent::setUp();
        // Remove Bjorn's starting mana card; tableau now holds no gainMana target.
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
    }

    public function testEmptyGainManaAloneAutoSkips(): void {
        $op = $this->createOp("gainMana");
        $this->assertTrue($op->noValidTargets());
        $this->assertTrue($op->canSkip(), "empty gain is skippable");
        $this->assertTrue($op->canResolveAutomatically(), "resolves as an auto-skip, no player prompt");
    }

    public function testPaygainWithEmptyGainIsWithheld(): void {
        $op = $this->createOp("spendUse:gainMana", ["card" => self::HERO_CARD]);
        $this->assertTrue($op->noValidTargets(), "chain is not offered when its gain half is empty");
    }

    public function testPaygainOfferedAgainOnceGainTargetExists(): void {
        $this->game->tokens->moveToken("card_ability_1_3", $this->getPlayersTableau());
        $op = $this->createOp("spendUse:gainMana", ["card" => self::HERO_CARD]);
        $this->assertFalse($op->noValidTargets(), "control: same chain is offered with a mana card present");
    }
}
