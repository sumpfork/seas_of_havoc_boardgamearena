<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

class StateIdTest extends TestCase
{
    /** Two states sharing an id silently breaks the state machine, so keep the STATE_* defines unique. */
    public function testStateConstantValuesAreUnique(): void
    {
        $states = [];
        foreach (get_defined_constants() as $name => $value) {
            if (str_starts_with($name, "STATE_")) {
                $states[$value][] = $name;
            }
        }
        $this->assertNotEmpty($states);
        foreach ($states as $value => $names) {
            $this->assertCount(1, $names, "State id $value used by: " . implode(", ", $names));
        }
    }

    /** ... and no two state classes may claim the same id either. */
    public function testStateClassesClaimDistinctIds(): void
    {
        $game = new SeasOfHavocUT();
        $seen = [];
        foreach (glob(__DIR__ . "/../php/States/*.php") as $file) {
            require_once $file;
            $class = "Bga\\Games\\SeasOfHavoc\\States\\" . basename($file, ".php");
            $id = (new $class($game))->id;
            $this->assertArrayNotHasKey($id, $seen, "id $id claimed by both " . ($seen[$id] ?? "") . " and $class");
            $seen[$id] = $class;
        }
        $this->assertCount(27, $seen);
    }
}
