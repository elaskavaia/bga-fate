<?php

declare(strict_types=1);

/**
 * Contract that a game class must implement for use with GameDriver.
 */
interface HarnessGameInterface {
    public function getGameName(): string;
    public function saveDbState(): array;
    public function loadDbState(array $db): void;
    public function getAllDatas(): array;
}
