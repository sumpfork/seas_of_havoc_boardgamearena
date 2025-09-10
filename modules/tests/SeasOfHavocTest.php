<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../seasofhavoc.game.php";

class SeasOfHavocUT extends SeasOfHavoc {
    public array $resource_types;
    public array $token_names;
    public array $non_playable_cards;
    public array $playable_cards;
    
    function __construct() {
        // Don't call parent constructor to avoid DB initialization
        include __DIR__ . "/../material.inc.php";
    }
    
    public function notifyAllPlayers($notification_type, $notification_log, $notification_args) {
        // Stub implementation for testing - just return without doing anything
        return;
    }
    
    public function getActivePlayerId() {
        // Stub implementation for testing
        return 1;
    }
    
    public function getPlayersNumber() {
        // Stub implementation for testing
        return 2;
    }
    
    // Simple method to test basic functionality
    public function testGetResourceTypes() {
        return $this->resource_types;
    }
}

class SeaBoardUT extends SeaBoard {
    
    function __construct($bga = null) {
        // Mock the database function and BGA object
        parent::__construct("mock_db_function", $bga ?: new MockBGA());
        // Initialize the board contents directly using reflection
        $this->initializeBoard();
    }
    
    // Override syncToDB to do nothing (mock)
    public function syncToDB() {
        // Do nothing - mocked
    }
    
    // Override syncFromDB to do nothing (mock)
    public function syncFromDB() {
        // Do nothing - mocked
    }
    
    // Initialize the board for testing
    private function initializeBoard() {
        $row = array_fill(0, self::WIDTH, []);
        $contents = array_fill(0, self::HEIGHT, $row);
        
        $reflection = new ReflectionClass(parent::class);
        $contentsProperty = $reflection->getProperty('contents');
        $contentsProperty->setAccessible(true);
        $contentsProperty->setValue($this, $contents);
    }
    
    // Helper method to get current board contents for testing
    public function getMockContents() {
        $reflection = new ReflectionClass(parent::class);
        $contentsProperty = $reflection->getProperty('contents');
        $contentsProperty->setAccessible(true);
        return $contentsProperty->getValue($this);
    }
    
    // Helper method to directly set board contents for testing
    public function setMockContents(array $contents) {
        $reflection = new ReflectionClass(parent::class);
        $contentsProperty = $reflection->getProperty('contents');
        $contentsProperty->setAccessible(true);
        $contentsProperty->setValue($this, $contents);
    }
}

class MockBGA {
    public function dump($label, $data) {
        // Mock implementation - do nothing
    }
    
    public function trace($message) {
        // Mock implementation - do nothing
    }
}

final class SeasOfHavocTest extends TestCase {
    
    private $game;
    private SeaBoardUT $seaboard;
    
    protected function setUp(): void {
        $this->game = new SeasOfHavocUT();
        $this->seaboard = new SeaBoardUT();
    }
    
    public function testGameProgression(): void {
        // Test that getGameProgression method exists and returns an integer
        // We can't test the actual progression without database setup
        $this->assertTrue(method_exists($this->game, 'getGameProgression'));
        
        // Test that the method is callable
        $this->assertTrue(is_callable([$this->game, 'getGameProgression']));
    }
    
    public function testResourceTypes(): void {
        // Test that resource types are properly defined
        $resourceTypes = $this->game->testGetResourceTypes();
        $this->assertIsArray($resourceTypes);
        $this->assertContains('sail', $resourceTypes);
        $this->assertContains('cannonball', $resourceTypes);
        $this->assertContains('doubloon', $resourceTypes);
        $this->assertContains('skiff', $resourceTypes);
    }
    
    public function testHeadingEnum(): void {
        // Test the Heading enum functionality
        $this->assertEquals("NORTH", Heading::NORTH->toString());
        $this->assertEquals("EAST", Heading::EAST->toString());
        $this->assertEquals("SOUTH", Heading::SOUTH->toString());
        $this->assertEquals("WEST", Heading::WEST->toString());
        $this->assertEquals("NO_HEADING", Heading::NO_HEADING->toString());
    }
    
    public function testTurnEnum(): void {
        // Test the Turn enum functionality
        $this->assertEquals("LEFT", Turn::LEFT->toString());
        $this->assertEquals("RIGHT", Turn::RIGHT->toString());
        $this->assertEquals("AROUND", Turn::AROUND->toString());
        $this->assertEquals("NOTURN", Turn::NOTURN->toString());
    }
    
    // SeaBoard Tests
    
    public function testSeaBoardConstants(): void {
        $this->assertEquals(6, SeaBoard::WIDTH);
        $this->assertEquals(6, SeaBoard::HEIGHT);
    }
    
    public function testSeaBoardInitialization(): void {
        $seaboard = new SeaBoardUT();
        $contents = $seaboard->getMockContents();
        
        // Should be initialized as empty board
        $this->assertIsArray($contents);
        $this->assertCount(SeaBoard::HEIGHT, $contents);
        $this->assertCount(SeaBoard::WIDTH, $contents[0]);
        
        // All positions should be empty arrays initially
        for ($x = 0; $x < SeaBoard::WIDTH; $x++) {
            for ($y = 0; $y < SeaBoard::HEIGHT; $y++) {
                $this->assertIsArray($contents[$x][$y]);
                $this->assertEmpty($contents[$x][$y]);
            }
        }
        
        // syncFromDB should do nothing (mocked)
        $seaboard->syncFromDB();
        $contentsAfterSync = $seaboard->getMockContents();
        $this->assertEquals($contents, $contentsAfterSync);
    }
    
    public function testPlaceAndGetObjects(): void {
        
        $ship = [
            "type" => "player_ship",
            "arg" => "player1",
            "heading" => Heading::NORTH
        ];
        
        $this->seaboard->placeObject(2, 3, $ship);
        
        $objects = $this->seaboard->getObjects(2, 3);
        $this->assertCount(1, $objects);
        $this->assertEquals("player_ship", $objects[0]["type"]);
        $this->assertEquals("player1", $objects[0]["arg"]);
        $this->assertEquals(Heading::NORTH, $objects[0]["heading"]);
        
        // Test empty position
        $emptyObjects = $this->seaboard->getObjects(0, 0);
        $this->assertEmpty($emptyObjects);
    }
    
    public function testGetAllObjectsFlat(): void {
        
        $ship1 = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $ship2 = ["type" => "player_ship", "arg" => "player2", "heading" => Heading::SOUTH];
        $treasure = ["type" => "treasure", "arg" => "gold", "heading" => Heading::NO_HEADING];
        
        $this->seaboard->placeObject(1, 1, $ship1);
        $this->seaboard->placeObject(2, 2, $ship2);
        $this->seaboard->placeObject(3, 3, $treasure);
        
        $flatObjects = $this->seaboard->getAllObjectsFlat();
        $this->assertCount(3, $flatObjects);
        
        // Check that all objects are present with correct coordinates
        $found = [];
        foreach ($flatObjects as $obj) {
            $key = $obj["x"] . "," . $obj["y"] . "," . $obj["type"] . "," . $obj["arg"];
            $found[$key] = true;
        }
        
        $this->assertArrayHasKey("1,1,player_ship,player1", $found);
        $this->assertArrayHasKey("2,2,player_ship,player2", $found);
        $this->assertArrayHasKey("3,3,treasure,gold", $found);
    }
    
    public function testGetObjectsOfTypes(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $treasure = ["type" => "treasure", "arg" => "gold", "heading" => Heading::NO_HEADING];
        $island = ["type" => "island", "arg" => "island1", "heading" => Heading::NO_HEADING];
        
        $this->seaboard->placeObject(2, 2, $ship);
        $this->seaboard->placeObject(2, 2, $treasure);
        $this->seaboard->placeObject(2, 2, $island);
        
        // Test filtering by single type
        $ships = $this->seaboard->getObjectsOfTypes(2, 2, ["player_ship"]);
        $this->assertCount(1, $ships);
        $this->assertEquals("player_ship", $ships[0]["type"]);
        
        // Test filtering by multiple types
        $shipAndTreasure = $this->seaboard->getObjectsOfTypes(2, 2, ["player_ship", "treasure"]);
        $this->assertCount(2, $shipAndTreasure);
        
        // Test filtering by non-existent type
        $empty = $this->seaboard->getObjectsOfTypes(2, 2, ["non_existent"]);
        $this->assertEmpty($empty);
    }
    
    public function testFindObject(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $this->seaboard->placeObject(3, 4, $ship);
        
        $result = $this->seaboard->findObject("player_ship", "player1");
        $this->assertNotNull($result);
        $this->assertEquals(3, $result["x"]);
        $this->assertEquals(4, $result["y"]);
        $this->assertEquals("player_ship", $result["object"]["type"]);
        $this->assertEquals("player1", $result["object"]["arg"]);
        
        // Test finding non-existent object
        $notFound = $this->seaboard->findObject("player_ship", "nonexistent");
        $this->assertNull($notFound);
    }
    
    public function testRemoveObject(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $treasure = ["type" => "treasure", "arg" => "gold", "heading" => Heading::NO_HEADING];
        
        $this->seaboard->placeObject(2, 2, $ship);
        $this->seaboard->placeObject(2, 2, $treasure);
        
        // Verify both objects are there
        $objects = $this->seaboard->getObjects(2, 2);
        $this->assertCount(2, $objects);
        
        // Remove one object
        $this->seaboard->removeObject(2, 2, "player_ship", "player1");
        
        // Verify only treasure remains
        $objects = $this->seaboard->getObjects(2, 2);
        $this->assertCount(1, $objects);
        $remainingObject = array_values($objects)[0]; // Re-index to get first element
        $this->assertEquals("treasure", $remainingObject["type"]);
    }
    
    public function testTurnHeading(): void {
        // Test turning left
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::NORTH, Turn::LEFT));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::EAST, Turn::LEFT));
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::SOUTH, Turn::LEFT));
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::WEST, Turn::LEFT));
        
        // Test turning right
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::NORTH, Turn::RIGHT));
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::EAST, Turn::RIGHT));
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::SOUTH, Turn::RIGHT));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::WEST, Turn::RIGHT));
        
        // Test turning around
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::NORTH, Turn::AROUND));
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::EAST, Turn::AROUND));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::SOUTH, Turn::AROUND));
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::WEST, Turn::AROUND));
    }
    
    public function testTurnObject(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $this->seaboard->placeObject(2, 2, $ship);
        
        $result = $this->seaboard->turnObject("player_ship", "player1", Turn::RIGHT);
        
        $this->assertEquals("turn", $result["type"]);
        $this->assertEquals(Heading::NORTH, $result["old_heading"]);
        $this->assertEquals(Heading::EAST, $result["new_heading"]);
        
        // Verify the object was actually turned
        $objects = $this->seaboard->getObjects(2, 2);
        $this->assertEquals(Heading::EAST, $objects[0]["heading"]);
    }
    
    public function testMoveObjectForward(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $this->seaboard->placeObject(2, 2, $ship);
        
        $result = $this->seaboard->moveObjectForward("player_ship", "player1", []);
        
        $this->assertEquals("move", $result["type"]);
        $this->assertEquals(2, $result["old_x"]);
        $this->assertEquals(2, $result["old_y"]);
        $this->assertEquals(2, $result["new_x"]);
        $this->assertEquals(1, $result["new_y"]); // Moved north (y decreased)
        
        // Verify object moved
        $oldObjects = $this->seaboard->getObjects(2, 2);
        $newObjects = $this->seaboard->getObjects(2, 1);
        $this->assertEmpty($oldObjects);
        $this->assertCount(1, $newObjects);
    }
    
    public function testMoveObjectForwardWithCollision(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $obstacle = ["type" => "island", "arg" => "island1", "heading" => Heading::NO_HEADING];
        
        $this->seaboard->placeObject(2, 2, $ship);
        $this->seaboard->placeObject(2, 1, $obstacle); // Place obstacle in front
        
        $result = $this->seaboard->moveObjectForward("player_ship", "player1", ["island"]);
        
        $this->assertEquals("collision", $result["type"]);
        $this->assertEquals(2, $result["collision_x"]);
        $this->assertEquals(1, $result["collision_y"]);
        $this->assertCount(1, $result["colliders"]);
        $this->assertEquals("island", $result["colliders"][0]["type"]);
        
        // Verify ship didn't move
        $objects = $this->seaboard->getObjects(2, 2);
        $this->assertCount(1, $objects);
        $this->assertEquals("player_ship", $objects[0]["type"]);
    }
    
    public function testMoveObjectForwardWithWrapping(): void {
        
        // Test wrapping from north edge
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $this->seaboard->placeObject(2, 0, $ship); // At north edge
        
        $result = $this->seaboard->moveObjectForward("player_ship", "player1", []);
        
        $this->assertEquals("move", $result["type"]);
        $this->assertEquals(2, $result["new_x"]);
        $this->assertEquals(5, $result["new_y"]); // Wrapped to south edge
        $this->assertNotNull($result["teleport_at"]);
        $this->assertNotNull($result["teleport_to"]);
    }
    
    public function testResolveCannonFire(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $target = ["type" => "player_ship", "arg" => "player2", "heading" => Heading::SOUTH];
        
        $this->seaboard->placeObject(2, 2, $ship);
        $this->seaboard->placeObject(2, 0, $target); // 2 spaces north
        
        // Fire straight ahead (no turn) with distance 3
        $result = $this->seaboard->resolveCannonFire("player1", Turn::NOTURN, 3, ["player_ship"]);
        
        $this->assertEquals("fire_hit", $result["type"]);
        $this->assertEquals(2, $result["hit_x"]);
        $this->assertEquals(0, $result["hit_y"]);
        $this->assertCount(1, $result["hit_objects"]);
        $this->assertEquals("player2", $result["hit_objects"][0]["arg"]);
    }
    
    public function testResolveCannonFireMiss(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $this->seaboard->placeObject(2, 2, $ship);
        
        // Fire with distance 1 into empty space
        $result = $this->seaboard->resolveCannonFire("player1", Turn::NOTURN, 1, ["player_ship"]);
        
        $this->assertEquals("fire_miss", $result["type"]);
    }
    
    public function testResolveCannonFireWithTurn(): void {
        
        $ship = ["type" => "player_ship", "arg" => "player1", "heading" => Heading::NORTH];
        $target = ["type" => "player_ship", "arg" => "player2", "heading" => Heading::SOUTH];
        
        $this->seaboard->placeObject(2, 2, $ship);
        $this->seaboard->placeObject(3, 2, $target); // To the east
        
        // Fire to the right (east) with distance 2
        $result = $this->seaboard->resolveCannonFire("player1", Turn::RIGHT, 2, ["player_ship"]);
        
        $this->assertEquals("fire_hit", $result["type"]);
        $this->assertEquals(3, $result["hit_x"]);
        $this->assertEquals(2, $result["hit_y"]);
        $this->assertEquals(Heading::EAST, $result["fire_heading"]);
    }
}
