<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;

abstract class AbstractCardTestCase extends AbstractOpTestCase {
    protected GameUT $game;
    protected string $owner;
    protected Operation $op;

    /**
     * Instantiate op and cache $this->op;
     */
    function createOp(?string $type = null, mixed $data = null): Operation {
        // Derive op type from the test class name: "Op_c_preyTest" → "c_prey"
        if ($type == null) {
            $type = "trigger(move)";
        }
        $this->op = $this->game->machine->instanciateOperation($type, $this->owner, $data);
        return $this->op;
    }
}
