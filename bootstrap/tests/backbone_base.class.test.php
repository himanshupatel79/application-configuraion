<?php

class BackboneBaseTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        include_once dirname(__FILE__) . '/../common/classes/Backbone_Base.php';
        $this->objBackboneBase = new Backbone_Base();
        $this->config = $this->objBackboneBase->getConfig('config.ini');
        $this->dbConfig = $this->objBackboneBase->getConfig('database.ini');
        $this->appConfig = $this->objBackboneBase->getConfig('applications/application.ini');
    }

    public function tearDown()
    {
        unset($this->objBackboneBase);
        unset($this->config);
        unset($this->dbConfig);
        unset($this->appConfig);
    }

    public function testApplicationConfigEnvironmentSet()
    {
        $this->assertGreaterThan(
            0,
            count($this->config)
        );
    }

    public function testApplicationDatabaseConfigEnvironmentSet()
    {
        $this->assertGreaterThan(
            0,
            count($this->dbConfig)
        );
    }

    public function testTotalNumberOfEnvironmentSetInConfig()
    {
        $this->assertEquals(
            4,
            count($this->config)
        );
    }

    public function testTotalNumberOfEnvironmentSetInDatabaseConfig()
    {
        $this->assertEquals(
            4,
            count($this->dbConfig)
        );
    }

    public function testTotalNumberOfEnvironmentSetInApplicationConfig()
    {
        $this->assertEquals(
            4,
            count($this->appConfig)
        );
    }

    public function testTotalNumberOfDBUsersSetInDatabaseConfig()
    {
        $this->assertEquals(
            9,
            count($this->dbConfig['production']['mysql_users'])
        );
    }

    public function testEnvironmentSet()
    {
        $this->assertArrayHasKey('production', $this->dbConfig);
        $this->assertArrayHasKey('uat', $this->dbConfig);
        $this->assertArrayHasKey('staging', $this->dbConfig);
        $this->assertArrayHasKey('development', $this->dbConfig);
    }
}