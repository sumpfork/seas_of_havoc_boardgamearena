<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

class StateIdTest extends TestCase
{
    /** Two states sharing an id silently breaks the state machine, so keep the defines unique. */
    public function testStateConstantValuesAreUnique(): void
    {
        preg_match_all(
            '/define\("(STATE_\w+)", (\d+)\)/',
            file_get_contents(__DIR__ . "/../../seasofhavoc.game.php"),
            $m
        );
        $this->assertNotEmpty($m[1]);
        $byValue = [];
        foreach ($m[1] as $i => $name) {
            $byValue[$m[2][$i]][] = $name;
        }
        foreach ($byValue as $value => $names) {
            $this->assertCount(1, $names, "State id $value used by: " . implode(", ", $names));
        }
    }

    /** Each state class must claim a distinct id, and use a constant rather than a bare number. */
    public function testStateClassesClaimDistinctIds(): void
    {
        $seen = [];
        foreach (glob(__DIR__ . "/../php/States/*.php") as $file) {
            $this->assertSame(1, preg_match('/id: (\S+?),/', file_get_contents($file), $m), "No id: in $file");
            $this->assertStringStartsWith("STATE_", $m[1], "$file uses a bare state id");
            $this->assertArrayNotHasKey($m[1], $seen, "$m[1] claimed by both " . ($seen[$m[1]] ?? '') . " and $file");
            $seen[$m[1]] = $file;
        }
        $this->assertCount(23, $seen);
    }
}
