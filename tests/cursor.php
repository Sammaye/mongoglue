<?php

namespace mongoglue\tests;

require '../Server.php';

class cursor extends \PHPUnit_Framework_TestCase{

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

	function testMongoCursor(){

		$this->mongo->select('test')->insert(array('d' => 1));
		$cursor = $this->mongo->select('test')->find();

		foreach($cursor as $row){
			$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $row);
			break;
		}
	}

	function testArrayCursor(){
		$this->assertInstanceOf('\\mongoglue\\Cursor', new \mongoglue\Cursor(array(
			array('d' => 1),
			array('e' => 2),
			array('f' => 3)
		)));
	}

	function testManualCursor(){
		$this->assertInstanceOf('\\mongoglue\\Cursor', new \mongoglue\Cursor(array(), 'test', $this->mongo));
	}

	function testArrayLimit(){
		$cursor = new \mongoglue\Cursor(array(
			array('d' => 1),
			array('e' => 2),
			array('f' => 3)
		));

		$cursor->limit(2);
		$a = iterator_to_array($cursor);
		$this->assertEquals(2, sizeof($a));
	}

	function testArraySkip(){
		$cursor = new \mongoglue\Cursor(array(
			array('d' => 1),
			array('e' => 2),
			array('f' => 3)
		));

		$cursor->skip(2);
		$a = iterator_to_array($cursor);
		$this->assertEquals(1, sizeof($a));
	}

	function testArrayCount(){

		$cursor = new \mongoglue\Cursor(array(
			array('d' => 1),
			array('e' => 2),
			array('f' => 3)
		));

		$this->assertEquals(3, $cursor->count());
	}

	function testDocumentCurrent(){

		$this->mongo->select('test')->insert(array('d' => 1));

		$cursor = new \mongoglue\Cursor(array(), 'test', $this->mongo);

		foreach($cursor as $row){
			$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $row);
			break;
		}
	}

	function testOtherCurrent(){
		$cursor = new \mongoglue\Cursor(array(
			array('d' => 1),
			array('e' => 2),
			array('f' => 3)
		));

		foreach($cursor as $row){
			$this->assertTrue(is_array($row));
			break;
		}
	}

	function testCurrentScenario(){
		$this->mongo->select('test')->insert(array('d' => 1));

		$cursor = new \mongoglue\Cursor(array(), 'test', $this->mongo);

		foreach($cursor as $row){
			$this->assertEquals('update', $row->getScenario());
			break;
		}
	}

	function testCurrentIsNew(){
		$this->mongo->select('test')->insert(array('d' => 1));

		$cursor = new \mongoglue\Cursor(array(), 'test', $this->mongo);

		foreach($cursor as $row){
			$this->assertFalse($row->isNew());
			break;
		}
	}

	function test__call(){
		$cursor = new \mongoglue\Cursor(array(), 'test', $this->mongo);
		$this->assertFalse($cursor->hasNext());
	}
}