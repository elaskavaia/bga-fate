<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * custom: Stub for cards with custom effects not yet implemented.
 */
class Op_custom extends Operation {
    public function getPossibleMoves() {
        return ["q" => Material::ERR_NOT_APPLICABLE];
    }
    function resolve(): void {}
}
