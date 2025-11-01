<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

require_once __DIR__ . "/../../seasofhavoc.game.php";

/**
 * Mock BGA class for testing
 */
if (!class_exists('MockBGA')) {
    class MockBGA {
        public function dump($label, $data) {}
        public function trace($message) {}
    }
}

/**
 * Unit tests for the SeaBoard class with mocked database functionality
 */
final class SeaBoardTest extends TestCase {
    
    private SeaBoard $seaBoard;
    private $mockBga;
    private array $mockDbData;
    private array $executedQueries;
    
    protected function setUp(): void {
        // Reset mock data for each test
        $this->mockDbData = [];
        $this->executedQueries = [];
        
        // Create a mock BGA object
        $this->mockBga = new MockBGA();
        
        // Create SeaBoard with mocked database function
        $this->seaBoard = new SeaBoard([$this, 'mockDbQuery'], $this->mockBga);
    }
    
    /**
     * Mock database query function that simulates database operations
     */
    public function mockDbQuery(string $sql): array {
        $this->executedQueries[] = $sql;
        
        if (strpos($sql, 'DELETE FROM sea') === 0) {
            $this->mockDbData = [];
            return [];
        }
        
        if (strpos($sql, 'INSERT INTO sea') === 0) {
            // Parse INSERT statement and add to mock data
            if (preg_match_all("/\('(\d+)','(\d+)','([^']+)','([^']+)','(\d+)'\)/", $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->mockDbData[] = [
                        'x' => $match[1],
                        'y' => $match[2],
                        'type' => $match[3],
                        'arg' => $match[4],
                        'heading' => $match[5]
                    ];
                }
            }
            return [];
        }
        
        if (strpos($sql, 'select x, y, type, arg, heading from sea') === 0) {
            return $this->mockDbData;
        }
        
        return [];
    }
    
    public function testConstructor(): void {
        $this->assertInstanceOf(SeaBoard::class, $this->seaBoard);
    }
    
    public function testConstants(): void {
        $this->assertEquals(6, SeaBoard::WIDTH);
        $this->assertEquals(6, SeaBoard::HEIGHT);
    }
    
    public function testPlaceObject(): void {
        $object = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $this->seaBoard->placeObject(2, 3, $object);
        
        // Verify the object was placed
        $objects = $this->seaBoard->getObjects(2, 3);
        $this->assertCount(1, $objects);
        $this->assertEquals('player_ship', $objects[0]['type']);
        $this->assertEquals('player1', $objects[0]['arg']);
        $this->assertEquals(Heading::NORTH, $objects[0]['heading']);
        
        // Verify database operations
        $this->assertContains('DELETE FROM sea', $this->executedQueries);
        $this->assertTrue(
            array_reduce($this->executedQueries, function($carry, $query) {
                return $carry || strpos($query, 'INSERT INTO sea') === 0;
            }, false)
        );
    }
    
    public function testPlaceMultipleObjects(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $treasure = [
            'type' => 'treasure',
            'arg' => 'gold',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(1, 1, $ship);
        $this->seaBoard->placeObject(1, 1, $treasure);
        
        $objects = $this->seaBoard->getObjects(1, 1);
        $this->assertCount(2, $objects);
    }
    
    public function testGetObjects(): void {
        // Place an object first
        $object = [
            'type' => 'island',
            'arg' => 'island1',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(0, 0, $object);
        
        // Test getting objects from that position
        $objects = $this->seaBoard->getObjects(0, 0);
        $this->assertCount(1, $objects);
        $this->assertEquals('island', $objects[0]['type']);
        
        // Test getting objects from empty position
        $emptyObjects = $this->seaBoard->getObjects(5, 5);
        $this->assertCount(0, $emptyObjects);
    }
    
    public function testGetObjectsOfTypes(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $treasure = [
            'type' => 'treasure',
            'arg' => 'gold',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(2, 2, $ship);
        $this->seaBoard->placeObject(2, 2, $treasure);
        
        // Test filtering by specific types
        $ships = $this->seaBoard->getObjectsOfTypes(2, 2, ['player_ship']);
        $this->assertCount(1, $ships);
        $this->assertEquals('player_ship', $ships[0]['type']);
        
        $treasures = $this->seaBoard->getObjectsOfTypes(2, 2, ['treasure']);
        $this->assertCount(1, $treasures);
        $this->assertEquals('treasure', $treasures[0]['type']);
        
        $both = $this->seaBoard->getObjectsOfTypes(2, 2, ['player_ship', 'treasure']);
        $this->assertCount(2, $both);
    }
    
    public function testRemoveObject(): void {
        $object = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $this->seaBoard->placeObject(3, 3, $object);
        $this->assertEquals(1, count($this->seaBoard->getObjects(3, 3)));
        
        $this->seaBoard->removeObject(3, 3, 'player_ship', 'player1');
        $this->assertEquals(0, count($this->seaBoard->getObjects(3, 3)));
    }
    
    public function testFindObject(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::EAST
        ];
        
        $this->seaBoard->placeObject(4, 2, $ship);
        
        $result = $this->seaBoard->findObject('player_ship', 'player1');
        $this->assertNotNull($result);
        $this->assertEquals(4, $result['x']);
        $this->assertEquals(2, $result['y']);
        $this->assertEquals('player_ship', $result['object']['type']);
        $this->assertEquals('player1', $result['object']['arg']);
        $this->assertEquals(Heading::EAST, $result['object']['heading']);
        
        // Test finding non-existent object
        $notFound = $this->seaBoard->findObject('nonexistent', 'nothing');
        $this->assertNull($notFound);
    }
    
    public function testGetAllObjectsFlat(): void {
        $ship1 = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $ship2 = [
            'type' => 'player_ship',
            'arg' => 'player2',
            'heading' => Heading::SOUTH
        ];
        
        $this->seaBoard->placeObject(1, 1, $ship1);
        $this->seaBoard->placeObject(3, 4, $ship2);
        
        $allObjects = $this->seaBoard->getAllObjectsFlat();
        $this->assertCount(2, $allObjects);
        
        // Check first object
        $this->assertEquals(1, $allObjects[0]['x']);
        $this->assertEquals(1, $allObjects[0]['y']);
        $this->assertEquals('player_ship', $allObjects[0]['type']);
        $this->assertEquals('player1', $allObjects[0]['arg']);
        $this->assertEquals(Heading::NORTH, $allObjects[0]['heading']);
        
        // Check second object
        $this->assertEquals(3, $allObjects[1]['x']);
        $this->assertEquals(4, $allObjects[1]['y']);
        $this->assertEquals('player_ship', $allObjects[1]['type']);
        $this->assertEquals('player2', $allObjects[1]['arg']);
        $this->assertEquals(Heading::SOUTH, $allObjects[1]['heading']);
    }
    
    public function testTurnHeadingLeft(): void {
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::NORTH, Turn::LEFT));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::EAST, Turn::LEFT));
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::SOUTH, Turn::LEFT));
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::WEST, Turn::LEFT));
    }
    
    public function testTurnHeadingRight(): void {
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::NORTH, Turn::RIGHT));
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::EAST, Turn::RIGHT));
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::SOUTH, Turn::RIGHT));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::WEST, Turn::RIGHT));
    }
    
    public function testTurnHeadingAround(): void {
        $this->assertEquals(Heading::SOUTH, SeaBoard::turnHeading(Heading::NORTH, Turn::AROUND));
        $this->assertEquals(Heading::WEST, SeaBoard::turnHeading(Heading::EAST, Turn::AROUND));
        $this->assertEquals(Heading::NORTH, SeaBoard::turnHeading(Heading::SOUTH, Turn::AROUND));
        $this->assertEquals(Heading::EAST, SeaBoard::turnHeading(Heading::WEST, Turn::AROUND));
    }
    
    public function testTurnObject(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $this->seaBoard->placeObject(2, 2, $ship);
        
        $result = $this->seaBoard->turnObject('player_ship', 'player1', Turn::RIGHT);
        
        $this->assertEquals('turn', $result['type']);
        $this->assertEquals(Heading::NORTH, $result['old_heading']);
        $this->assertEquals(Heading::EAST, $result['new_heading']);
        
        // Verify the object's heading was actually changed
        $updatedShip = $this->seaBoard->findObject('player_ship', 'player1');
        $this->assertEquals(Heading::EAST, $updatedShip['object']['heading']);
    }
    
    public function testMoveObjectForwardSimple(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::EAST
        ];
        
        $this->seaBoard->placeObject(2, 2, $ship);
        
        $result = $this->seaBoard->moveObjectForward('player_ship', 'player1', []);
        
        $this->assertEquals('move', $result['type']);
        $this->assertEquals(2, $result['old_x']);
        $this->assertEquals(2, $result['old_y']);
        $this->assertEquals(3, $result['new_x']);
        $this->assertEquals(2, $result['new_y']);
        
        // Verify the ship moved
        $this->assertCount(0, $this->seaBoard->getObjects(2, 2));
        $this->assertCount(1, $this->seaBoard->getObjects(3, 2));
    }
    
    public function testMoveObjectForwardWithCollision(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::EAST
        ];
        
        $obstacle = [
            'type' => 'island',
            'arg' => 'island1',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(2, 2, $ship);
        $this->seaBoard->placeObject(3, 2, $obstacle);
        
        $result = $this->seaBoard->moveObjectForward('player_ship', 'player1', ['island']);
        
        $this->assertEquals('collision', $result['type']);
        $this->assertEquals(3, $result['collision_x']);
        $this->assertEquals(2, $result['collision_y']);
        $this->assertCount(1, $result['colliders']);
        $this->assertEquals('island', $result['colliders'][0]['type']);
        
        // Verify the ship didn't move
        $this->assertCount(1, $this->seaBoard->getObjects(2, 2));
        $this->assertCount(1, $this->seaBoard->getObjects(3, 2));
    }
    
    public function testMoveObjectForwardWithTeleportation(): void {
        // Test teleportation when moving off the board
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::EAST
        ];
        
        // Place ship at the right edge
        $this->seaBoard->placeObject(5, 2, $ship);
        
        $result = $this->seaBoard->moveObjectForward('player_ship', 'player1', []);
        
        $this->assertEquals('move', $result['type']);
        $this->assertEquals(5, $result['old_x']);
        $this->assertEquals(2, $result['old_y']);
        $this->assertEquals(0, $result['new_x']); // Teleported to left side
        $this->assertEquals(2, $result['new_y']);
        $this->assertNotNull($result['teleport_at']);
        $this->assertNotNull($result['teleport_to']);
        
        // Verify the ship teleported
        $this->assertCount(0, $this->seaBoard->getObjects(5, 2));
        $this->assertCount(1, $this->seaBoard->getObjects(0, 2));
    }
    
    public function testResolveCannonFireHit(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $target = [
            'type' => 'player_ship',
            'arg' => 'player2',
            'heading' => Heading::SOUTH
        ];
        
        $this->seaBoard->placeObject(3, 3, $ship);
        $this->seaBoard->placeObject(3, 1, $target); // 2 spaces north
        
        $result = $this->seaBoard->resolveCannonFire('player1', Turn::NOTURN, 3, ['player_ship']);
        
        $this->assertEquals('fire_hit', $result['type']);
        $this->assertEquals(3, $result['hit_x']);
        $this->assertEquals(1, $result['hit_y']);
        $this->assertCount(1, $result['hit_objects']);
        $this->assertEquals('player_ship', $result['hit_objects'][0]['type']);
        $this->assertEquals('player2', $result['hit_objects'][0]['arg']);
        $this->assertEquals(Heading::NORTH, $result['fire_heading']);
    }
    
    public function testResolveCannonFireMiss(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $this->seaBoard->placeObject(3, 3, $ship);
        
        $result = $this->seaBoard->resolveCannonFire('player1', Turn::NOTURN, 2, ['player_ship']);
        
        $this->assertEquals('fire_miss', $result['type']);
    }
    
    public function testResolveCannonFireWithDirection(): void {
        $ship = [
            'type' => 'player_ship',
            'arg' => 'player1',
            'heading' => Heading::NORTH
        ];
        
        $target = [
            'type' => 'island',
            'arg' => 'island1',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(3, 3, $ship);
        $this->seaBoard->placeObject(4, 3, $target); // To the right
        
        // Fire to the right (turn right from north heading)
        $result = $this->seaBoard->resolveCannonFire('player1', Turn::RIGHT, 2, ['island']);
        
        $this->assertEquals('fire_hit', $result['type']);
        $this->assertEquals(4, $result['hit_x']);
        $this->assertEquals(3, $result['hit_y']);
        $this->assertEquals(Heading::EAST, $result['fire_heading']);
    }
    
    public function testBoundaryAssertions(): void {
        $object = [
            'type' => 'test',
            'arg' => 'test',
            'heading' => Heading::NO_HEADING
        ];
        
        // Test valid boundaries
        $this->seaBoard->placeObject(0, 0, $object);
        $this->seaBoard->placeObject(5, 5, $object);
        
        // Test assertions for invalid coordinates
        $this->expectException(AssertionError::class);
        $this->seaBoard->placeObject(-1, 0, $object);
    }
    
    public function testBoundaryAssertionsY(): void {
        $object = [
            'type' => 'test',
            'arg' => 'test',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->expectException(AssertionError::class);
        $this->seaBoard->placeObject(0, 6, $object);
    }
    
    public function testDatabaseSyncOperations(): void {
        $object1 = [
            'type' => 'ship',
            'arg' => 'ship1',
            'heading' => Heading::NORTH
        ];
        
        $object2 = [
            'type' => 'treasure',
            'arg' => 'gold',
            'heading' => Heading::NO_HEADING
        ];
        
        $this->seaBoard->placeObject(1, 2, $object1);
        $this->seaBoard->placeObject(3, 4, $object2);
        
        // Check that proper SQL operations were executed
        $deleteQueries = array_filter($this->executedQueries, function($query) {
            return strpos($query, 'DELETE FROM sea') === 0;
        });
        $this->assertGreaterThan(0, count($deleteQueries));
        
        $insertQueries = array_filter($this->executedQueries, function($query) {
            return strpos($query, 'INSERT INTO sea') === 0;
        });
        $this->assertGreaterThan(0, count($insertQueries));
        
        // Verify the mock database contains the expected data
        $this->assertCount(2, $this->mockDbData);
    }
}
