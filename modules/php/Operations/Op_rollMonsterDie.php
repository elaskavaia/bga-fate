<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Roll the Monster Die (variant rule, gated by `var_monster_die`).
 *
 * Picks the single `die_monster` token from `supply_die_monster`, rolls 1–6,
 * and parks it on `display_monsterturn` showing the rolled side. Per-side
 * effects are read off the parked die by downstream ops (Op_monsterAttack,
 * Op_monsterMoveAll, …) via Game::getMonsterDieSide().
 */
class Op_rollMonsterDie extends Operation {
    function resolve(): void {
        // Sweep any leftover die from a previous monster turn back to supply.
        if ($this->game->tokens->getTokenLocation("die_monster") === "display_monsterturn") {
            $this->game->tokens->dbSetTokenLocation("die_monster", "supply_die_monster", 6, "");
        }

        $roll = $this->game->bgaRand(1, 6);
        $sideName = $this->game->material->getRulesFor("side_die_monster_$roll", "name", "?");
        $this->game->tokens->dbSetTokenLocation(
            "die_monster",
            "display_monsterturn",
            $roll,
            clienttranslate('Monsters roll ${side_name} ${sides}'),
            ["side_name" => $sideName, "sides" => "[DIE_MON_$roll]"]
        );

        // Active sides queue a one-shot handler that runs before monsterMoveAll.
        // attack & charge are passive — readers inspect die state directly.
        $rule = $this->game->material->getRulesFor("side_die_monster_$roll", "rule", "");
        match ($rule) {
            "maneuver_1" => $this->queue("monsterDieManeuver(cw)"),
            "maneuver_2" => $this->queue("monsterDieManeuver(ccw)"),
            "push" => $this->queue("monsterDiePush"),
            "ambush" => $this->queue("monsterDieAmbush"),
            default => null,
        };

        // Roll result is hidden info that becomes public — non-rewindable.
        $this->game->customUndoSavepoint(0, 1, "roll");
    }
}
