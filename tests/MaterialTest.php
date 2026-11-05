<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Checks on the generated Material.php source, since clienttranslate() is invisible at runtime. */
final class MaterialTest extends TestCase {
    private const DISPLAYED_TEXT_FIELDS = ["name", "tooltip", "tooltip_action", "text", "effect", "quest", "flavour", "ftext"];

    private function getSourceLines(): array {
        return file(__DIR__ . "/../modules/php/Material.php", FILE_IGNORE_NEW_LINES);
    }

    /** BGA #254408: quest (and flavour, ftext) text never reached the BGA translation system. */
    public function testDisplayedTextIsMarkedForTranslation(): void {
        $fields = implode("|", self::DISPLAYED_TEXT_FIELDS);
        $plain = preg_grep("/^\s+\"($fields)\" => [\"']/", $this->getSourceLines());
        $this->assertEmpty($plain, "plain (untranslatable) display text:\n" . implode("\n", $plain));
    }

    /** CSV-style doubled quotes are not unescaped by genmat, so the player would see them literally. */
    public function testDisplayedTextHasNoDoubledQuotes(): void {
        $doubled = preg_grep('/clienttranslate\(.*\\\\"\\\\"/', $this->getSourceLines());
        $this->assertEmpty($doubled, implode("\n", $doubled));
    }
}
