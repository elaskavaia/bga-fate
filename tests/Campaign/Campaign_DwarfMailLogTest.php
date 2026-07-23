<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Verifies the fix for BGA report #233796 (display): using Boldur's Dwarf Mail
 * (card_equip_4_18, r=preventDamage) used to log a client error
 *   "string.substitute could not find key \"count\" in template"
 * for the prompt 'Prevent ${count} of ${max} damage?'.
 *
 * Was caused by Op_preventDamage::getExtraArgs() overriding
 * CountableOperation::getExtraArgs() and returning only "max", dropping the
 * "count" key that the prompt template references. Now fixed by merging parent args.
 */
class Campaign_DwarfMailLogTest extends CampaignBaseTest {
    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    /**
     * Mimic the runtime stack the moment Dwarf Mail's preventDamage becomes
     * available: an incoming dealDamage aimed at Boldur (attacker set), then
     * the preventDamage reaction on top. Then inspect the args the client would
     * receive and apply the same placeholder-resolution check the harness uses
     * for notifications - the "${count}" placeholder must resolve.
     */
    public function testPreventDamagePromptCountPlaceholderResolves(): void {
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);

        // Boldur outside Grimheim with a monster adjacent (attacker for the incoming hit).
        $this->game->tokens->moveToken("card_equip_4_18", "tableau_$color"); // Dwarf Mail
        $this->game->tokens->moveToken($heroId, "hex_7_9");
        $monster = "monster_goblin_1";
        $this->game->getMonster($monster)->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        // Incoming damage on Boldur - this is what preventDamage looks for.
        $this->game->machine->push("dealDamage", $color, [
            "target" => "hex_7_9",
            "attacker" => $monster,
            "count" => 3,
        ]);

        // Dwarf Mail's reaction: preventDamage. Build the args the client gets.
        $op = $this->game->machine->instantiateOperation("preventDamage", $color);
        $args = $op->getArgs();
        $prompt = $args["prompt"] ?? "";

        $this->assertStringContainsString('${count}', $prompt, "sanity: prompt references \${count}");
        // "max" is supplied, so string.substitute only chokes on the missing "count".
        $this->assertArrayHasKey("max", $args, "sanity: \${max} is resolvable");

        // Fixed for BGA #233796: Op_preventDamage merges parent args, so both
        // placeholders the prompt references now resolve on the client.
        $this->assertArrayHasKey(
            "count",
            $args,
            "prompt references \${count}; the arg must be present or client string.substitute fails"
        );
    }
}
