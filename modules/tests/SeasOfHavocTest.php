<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Mock the database functions before including the game file
function getUniqueValueFromDB($sql, $low_priority_select = false) {
    return 0;
}

function getObjectFromDB($sql) {
    return [];
}

function getCollectionFromDB($sql, $bSingleValue = false) {
    return [];
}

function DbQuery($sql, $specific_db = null, $bMulti = false) {
    return true;
}

require_once "../seasofhavoc.game.php";

class SeasOfHavocUT extends SeasOfHavoc {
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

final class SeasOfHavocTest extends TestCase {
    
    private $game;
    
    protected function setUp(): void {
        $this->game = new SeasOfHavocUT();
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
}
