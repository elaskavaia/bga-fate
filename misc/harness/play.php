<?php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . "/../../modules/php/Tests/_autoload.php";
require_once __DIR__ . "/../../modules/php/Tests/Stubs/MachineInMem.php";
require_once __DIR__ . "/../../modules/php/Tests/Stubs/TokensInMem.php";
require_once __DIR__ . "/HarnessGameInterface.php";
require_once __DIR__ . "/GameWrapper.php";
require_once __DIR__ . "/GameDriver.php";

GameDriver::main(new GameWrapper(), $argv, __DIR__, __DIR__ . "/../../staging");
