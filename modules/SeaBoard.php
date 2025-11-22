<?php

/**
 * SeaBoard class for Seas of Havoc
 * Manages the game board state and object placement/movement
 */

enum Heading: int
{
    case NO_HEADING = 0;
    case NORTH = 1;
    case EAST = 2;
    case SOUTH = 3;
    case WEST = 4;

    public function toString(): string
    {
        return match ($this) {
            Heading::NO_HEADING => "NO_HEADING",
            Heading::NORTH => "NORTH",
            Heading::EAST => "EAST",
            Heading::SOUTH => "SOUTH",
            Heading::WEST => "WEST",
        };
    }
}

enum Turn: int
{
    case LEFT = 0;
    case RIGHT = 1;
    case AROUND = 2;
    case NOTURN = 4;

    public function toString(): string
    {
        return match ($this) {
            Turn::LEFT => "LEFT",
            Turn::RIGHT => "RIGHT",
            Turn::AROUND => "AROUND",
            Turn::NOTURN => "NOTURN",
        };
    }
}

class SeaBoard
{
    const WIDTH = 6;
    const HEIGHT = 6;

    private array|null $contents;
    private $sqlfunc;
    private $bga;
    private bool $db_sync_active = false;

    function __construct($dbquery, $bga)
    {
        $this->sqlfunc = $dbquery;
        $this->contents = null;
        $this->bga = $bga;
        $this->db_sync_active = false;
    }

    private function init()
    {
        $row = array_fill(0, self::WIDTH, []);
        $this->contents = array_fill(0, self::HEIGHT, $row);
    }

    // public static string headingAsString(Heading $heading) {

    // }
    public function getObjects(int $x, int $y)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        return $this->contents[$x][$y];
    }

    public function getAllObjectsFlat()
    {
        $this->syncFromDB();
        $flat_contents = [];
        foreach ($this->contents as $x => $row) {
            foreach ($row as $y => $contents) {
                foreach ($contents as $entry) {
                    $flat_contents[] = [
                        "x" => $x,
                        "y" => $y,
                        "type" => $entry["type"],
                        "arg" => $entry["arg"],
                        "heading" => $entry["heading"],
                    ];
                }
            }
        }
        return $flat_contents;
    }

    public function getObjectsOfTypes(int $x, int $y, array $types)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        $contents = $this->contents[$x][$y];
        return array_values(array_filter($contents, fn($k) => in_array($k["type"], $types)));
    }

    public function findObject($type, $arg)
    {
        $this->syncFromDB();
        for ($x = 0; $x < self::WIDTH; $x++) {
            for ($y = 0; $y < self::HEIGHT; $y++) {
                $objects = $this->getObjectsOfTypes($x, $y, [$type]);
                foreach ($objects as $o) {
                    if ($o["arg"] == $arg) {
                        return ["x" => $x, "y" => $y, "object" => $o];
                    }
                }
            }
        }
    }

    public function getForwardPosition(int $x, int $y, Heading $heading)
    {
        $this->syncFromDB();
        $result = $this->computeForwardMovement($x, $y, $heading);
        return ["x" => $result["new_x"], "y" => $result["new_y"]];
    }

    private function computeForwardMovement(int $x, int $y, Heading $heading)
    {
        $new_x = $x;
        $new_y = $y;
        $teleport_at = null;
        $teleport_to = null;

        switch ($heading) {
            case Heading::NORTH:
                $new_y--;
                if ($new_y == -1) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => $new_x, "y" => self::HEIGHT];
                    $new_y = self::HEIGHT - 1;
                }
                break;
            case Heading::EAST:
                $new_x++;
                if ($new_x == self::WIDTH) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => -1, "y" => $new_y];
                    $new_x = 0;
                }
                break;
            case Heading::WEST:
                $new_x--;
                if ($new_x == -1) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => self::WIDTH, "y" => $new_y];
                    $new_x = self::WIDTH - 1;
                }
                break;
            case Heading::SOUTH:
                $new_y++;
                if ($new_y == self::HEIGHT) {
                    $teleport_at = ["x" => $new_x, "y" => $new_y];
                    $teleport_to = ["x" => $new_x, "y" => -1];
                    $new_y = 0;
                }
                break;
        }
        return ["new_x" => $new_x, "new_y" => $new_y, "teleport_at" => $teleport_at, "teleport_to" => $teleport_to];
    }

    public function moveObjectForward(string $object_type, string $arg, array $collision_types)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        $object = $object_info["object"];
        $this->bga->dump("object being moved forward", $object_info);
        $movement_result = $this->computeForwardMovement($object_info["x"], $object_info["y"], $object["heading"]);

        $collided = $this->getObjectsOfTypes($movement_result["new_x"], $movement_result["new_y"], $collision_types);
        if (count($collided) > 0) {
            return [
                "type" => "collision",
                "colliders" => $collided,
                "collision_x" => $movement_result["new_x"],
                "collision_y" => $movement_result["new_y"],
            ];
        }
        $old_x = $object_info["x"];
        $old_y = $object_info["y"];

        $this->removeObject($object_info["x"], $object_info["y"], $object["type"], $object["arg"]);
        $this->placeObject($movement_result["new_x"], $movement_result["new_y"], $object);

        return [
            "type" => "move",
            "colliders" => $collided,
            "old_x" => $old_x,
            "old_y" => $old_y,
            "new_x" => $movement_result["new_x"],
            "new_y" => $movement_result["new_y"],
            "teleport_at" => $movement_result["teleport_at"],
            "teleport_to" => $movement_result["teleport_to"],
        ];
    }

    public static function turnHeading(Heading $heading, Turn $direction)
    {
        switch ($direction) {
            case Turn::LEFT:
                $heading = Heading::from($heading->value - 1);
                if ($heading == Heading::NO_HEADING) {
                    $heading = Heading::WEST;
                }
                break;
            case Turn::RIGHT:
            # fallthrough
            case Turn::AROUND:
                $new_value = $heading->value + ($direction == Turn::RIGHT ? 1 : 2);
                if ($new_value > Heading::WEST->value) {
                    $new_value -= 4;
                }
                $heading = Heading::from($new_value);
                if ($heading > Heading::WEST) {
                    $heading = Heading::from($heading->value - 4);
                }
                break;
        }
        return $heading;
    }

    public function turnObject(string $object_type, string $arg, Turn $direction)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        $object = $object_info["object"];
        $new_heading = $this->turnHeading($object["heading"], $direction);
        $old_heading = $object["heading"];

        $this->removeObject($object_info["x"], $object_info["y"], $object["type"], $object["arg"]);
        $this->bga->trace(
            "changing heading from " .
                $object["heading"]->toString() .
                " to " .
                $new_heading->toString() .
                " due to " .
                $direction->toString() .
                " turn",
        );
        $object["heading"] = $new_heading;
        $this->placeObject($object_info["x"], $object_info["y"], $object);

        return ["type" => "turn", "old_heading" => $old_heading, "new_heading" => $new_heading];
    }

    public function resolveCannonFire(string $player_id, Turn $direction, int $distance, array $collision_types)
    {
        $this->syncFromDB();
        $object_info = $this->findObject("player_ship", $player_id);
        $ship_heading = $object_info["object"]["heading"];
        $fire_heading = $this->turnHeading($ship_heading, $direction);
        $x = $object_info["x"];
        $y = $object_info["y"];
        $this->bga->trace(
            "resolving cannon fire from " .
                $x .
                " " .
                $y .
                "ship heading" .
                $ship_heading->toString() .
                " fire direction " .
                $direction->toString() .
                " fire heading " .
                $fire_heading->toString(),
        );
        for ($d = 1; $d <= $distance; $d++) {
            $movement_result = $this->computeForwardMovement($x, $y, $fire_heading);
            $x = $movement_result["new_x"];
            $y = $movement_result["new_y"];
            $this->bga->trace("checking " . $x . " " . $y . " while firing");
            $collided = $this->getObjectsOfTypes($x, $y, $collision_types);
            $this->bga->dump("collided", $collided);
            if (count($collided)) {
                return [
                    "type" => "fire_hit",
                    "hit_x" => $x,
                    "hit_y" => $y,
                    "hit_objects" => $collided,
                    "fire_heading" => $fire_heading,
                ];
            }
        }
        return ["type" => "fire_miss"];
    }

    public function isObjectOnWhirlpool(string $object_type, string $arg)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        if (!$object_info) {
            return false;
        }
        $x = $object_info["x"];
        $y = $object_info["y"];
        $whirlpools = $this->getObjectsOfTypes($x, $y, ["whirlpool"]);
        return count($whirlpools) > 0;
    }

    public function getGustAtObjectLocation(string $object_type, string $arg)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        if (!$object_info) {
            return null;
        }
        $x = $object_info["x"];
        $y = $object_info["y"];
        $gusts = $this->getObjectsOfTypes($x, $y, ["gust"]);
        if (count($gusts) > 0) {
            return $gusts[0]; // Return the first gust (there should only be one)
        }
        return null;
    }

    public function pushObjectInDirection(string $object_type, string $arg, Heading $direction, array $collision_types)
    {
        $this->syncFromDB();
        $object_info = $this->findObject($object_type, $arg);
        $object = $object_info["object"];
        $this->bga->trace("Pushing " . $object_type . " " . $arg . " in direction " . $direction->toString());
        
        $movement_result = $this->computeForwardMovement($object_info["x"], $object_info["y"], $direction);

        $collided = $this->getObjectsOfTypes($movement_result["new_x"], $movement_result["new_y"], $collision_types);
        if (count($collided) > 0) {
            return [
                "type" => "collision",
                "colliders" => $collided,
                "collision_x" => $movement_result["new_x"],
                "collision_y" => $movement_result["new_y"],
            ];
        }
        $old_x = $object_info["x"];
        $old_y = $object_info["y"];

        $this->removeObject($object_info["x"], $object_info["y"], $object["type"], $object["arg"]);
        $this->placeObject($movement_result["new_x"], $movement_result["new_y"], $object);

        return [
            "type" => "move",
            "colliders" => $collided,
            "old_x" => $old_x,
            "old_y" => $old_y,
            "new_x" => $movement_result["new_x"],
            "new_y" => $movement_result["new_y"],
            "teleport_at" => $movement_result["teleport_at"],
            "teleport_to" => $movement_result["teleport_to"],
        ];
    }

    public function placeObject(int $x, int $y, array $object)
    {
        assert($x >= 0 && $x < self::WIDTH);
        assert($y >= 0 && $y < self::HEIGHT);
        $this->syncFromDB();
        $this->contents[$x][$y][] = $object;
        $this->syncToDB();
    }

    public function removeObject(int $x, int $y, string $object_type, string $arg)
    {
        $this->syncFromDB();
        $this->contents[$x][$y] = array_filter(
            $this->contents[$x][$y],
            fn($o) => $o["type"] != $object_type || $o["arg"] != $arg,
        );
        $this->syncToDB();
    }

    public function syncToDB()
    {
        if ($this->db_sync_active) {
            return;
        }
        $this->db_sync_active = true;
        call_user_func($this->sqlfunc, "DELETE FROM sea");

        $sql = "INSERT INTO sea (x, y, type, arg, heading) VALUES ";
        $values = [];
        foreach ($this->getAllObjectsFlat() as $entry) {
            $values[] =
                "('" .
                $entry["x"] .
                "','" .
                $entry["y"] .
                "','" .
                $entry["type"] .
                "','" .
                $entry["arg"] .
                "','" .
                $entry["heading"]->value .
                "')";
        }
        $sql .= implode(",", $values);
        call_user_func($this->sqlfunc, $sql);
        $this->db_sync_active = false;
    }

    public function syncFromDB()
    {
        if ($this->db_sync_active) {
            return;
        }
        $this->db_sync_active = true;
        if ($this->contents === null) {
            $sql = "select x, y, type, arg, heading from sea";
            $result = call_user_func($this->sqlfunc, $sql);
            $this->init();
            foreach ($result as $row) {
                $this->placeObject(intval($row["x"]), intval($row["y"]), [
                    "type" => $row["type"],
                    "arg" => $row["arg"],
                    "heading" => Heading::from($row["heading"]),
                ]);
            }
        }
        $this->db_sync_active = false;
    }

    function dump()
    {
        $this->bga->dump("seaboard", $this->contents);
    }
}
