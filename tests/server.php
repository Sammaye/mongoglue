<?php
namespace mongoglue\tests;

require '../Server.php';

/**
 * A simple unit test case for the \mongoglue\Server class. 
 * 
 * My first ever one for PHP, took 2 hours to learn PHPUnit and write this class, yep, it took ages :(
 */
class server extends \PHPUnit_Framework_TestCase{
	
	protected $mongo = null;
	
	function setup(){
		$this->mongo = new \mongoglue\Server(new \Mongo(), array(
			'documentDir' => dirname(__FILE__).'/documents',
			'documentns' => '\\mongoglue\\tests\\documents'
		));
	}
	
	function tearDown(){
		unset($this->mongo);
	}
	
	function testSetup(){
		$this->assertInstanceOf('\mongoglue\Server', $this->mongo);
	}
	
	function testConnection(){
		$this->assertTrue($this->mongo->connection() instanceof \MongoClient || $this->mongo->connection() instanceof \Mongo);
	}
	
	function testAddConnection(){
		
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$connection = new \Mongo();
		}else{
			$connection = new \MongoClient();
		}
		
		$this->assertEquals('newCon', $this->mongo->addConnection('newCon', $connection));
	}
	
	function testSetConnectionByKey(){

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$connection = new \Mongo();
		}else{
			$connection = new \MongoClient();
		}
		
		$this->mongo->addConnection('newCon', $connection);
		$this->mongo->setConnection('newCon');
		
		$this->assertTrue($this->mongo->connection() instanceof \MongoClient || $this->mongo->connection() instanceof \Mongo);
	}
	
	function testSetConnectionByObject(){
		
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$connection = new \Mongo();
		}else{
			$connection = new \MongoClient();
		}
		
		$this->mongo->setConnection($connection);
		
		$this->assertTrue($this->mongo->connection() instanceof \MongoClient || $this->mongo->connection() instanceof \Mongo);		
	}
	
	function testRemoveConnectionByKey(){
		
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$connection = new \Mongo();
		}else{
			$connection = new \MongoClient();
		}
		
		$this->mongo->addConnection('newCon', $connection);		
		$this->mongo->removeConnection('newCon');

		$this->assertFalse($this->mongo->setConnection('newCon'));
	}
	
	function testRemoveConnectionByObject(){
		
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$connection = new \Mongo();
		}else{
			$connection = new \MongoClient();
		}		
		$this->assertTrue($this->mongo->removeConnection($connection));
	}
	
	function testGetName(){
		$this->assertEquals('default', $this->mongo->getName());
	}
	
	function testGetConnectionList(){
		$this->assertArrayHasKey('default', $this->mongo->getConnectionList());
	}
	
	function test__call(){
		$this->assertTrue($this->mongo->listDBs() || $this->mongo->listDBs() == null);
	}
	
	function test__getSelectDB(){
		$this->assertInstanceOf('\mongoglue\Database', $this->mongo->mydb);
	}
	
	function testSelectCollection(){
		$this->assertNull(@$this->mongo->selectCollection('mydb', 'newCol'));
	}
	
	function testCanLoadClass(){
		$this->assertTrue($this->mongo->canLoadClass('\mongoglue\Server') !== false);
	}
	
	function testSetGetObjectCache(){
		$this->mongo->setObjectCache('user', array('1f' => 1, '2f' => 1), array('g' => 1, 'f' => 2));
		$this->assertTrue($this->mongo->getObjCache('user')!==null);
	}
	
	function testGetVirtualCache(){
		$this->mongo->setObjectCache('user', array('1f' => 1, '2f' => 1), array('g' => 1, 'f' => 2));
		$this->assertTrue($this->mongo->getVirtualObjCache('user')!==null);		
	}
	
	function testGetDocumentCache(){
		$this->mongo->setObjectCache('user', array('1f' => 1, '2f' => 1), array('g' => 1, 'f' => 2));
		$this->assertTrue($this->mongo->getFieldObjCache('user')!==null);		
	}
	
	function testMerge(){
		
		$merged = $this->mongo->merge(array(
			'one' => 1,
			'two' => array('g' => 4)		
		), array(
			'three' => 3,
			'two' => array('h' => 4)	
		));
		
		$this->assertTrue(isset($merged['one']) && isset($merged['three']) && isset($merged['two']['g']) && isset($merged['two']['h']));
	}
}