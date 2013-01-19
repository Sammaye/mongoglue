<?php
namespace mongoglue\tests;

require '../Server.php';

class database extends \PHPUnit_Framework_TestCase{

	protected $mongo = null;

	function setUp(){
		$mongo = new \mongoglue\Server(new \Mongo(), array(
			'documentDir' => dirname(__FILE__).'/documents',
			'documentns' => '\\mongoglue\\tests\\documents'
		));
		$this->mongo = $mongo->mongoglue_orm_test;
	}

	function tearDown(){
		$this->mongo->drop(); // Drop DB
		unset($this->mongo);
	}

	function test__getSelectCollection(){
		$this->assertInstanceOf('\MongoCollection', $this->mongo->test);
	}

	function testConnection(){
		$this->assertInstanceOf('\mongoglue\Server', $this->mongo->getServer());
	}

	function test__call(){
		$this->assertTrue($this->mongo->lastError()!==null);
	}

	function test__callCollection(){
		$this->assertInstanceOf('\mongoglue\Document', $this->mongo->test());
	}

	function testCreateCollection(){
		$this->assertInstanceOf('\MongoCollection', $this->mongo->createCollection('myNewTest'));
	}

	/**
	 * This is designed to test that indexes do get made on a collection when called
	 * but that inteface ain't so public...
	 */
	function testCollectionIndex(){}

	function testSelect(){
		$this->assertInstanceOf('\mongoglue\Document', $this->mongo->select('test'));
	}

	function testGetDb(){
		$this->assertInstanceOf('\MongoDB', $this->mongo->getDb());
	}

	function testGetIndexes(){
		$this->assertTrue(is_array($this->mongo->getIndexes()));
	}
}