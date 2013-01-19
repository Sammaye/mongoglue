<?php

namespace mongoglue\tests;

require '../Server.php';

use \mongoglue\tests\documents;

class document extends \PHPUnit_Framework_TestCase{

	protected $mongo;
	protected $db;

	protected $testDocs = array(
		array('name' => 'sammaye'),
	);

	protected $testDetailDocs = array(
		array('name' => 'Programming'),
		array('name' => 'Designging'),
		array('name' => 'Lego'),
		array('name' => 'Stackoverflow')
	);

	function setUp(){
		$this->mongo = new \mongoglue\Server(new \Mongo(), array(
			'documentDir' => dirname(__FILE__).'/documents',
			'documentns' => '\\mongoglue\\tests\\documents'
		));
		$this->db = $this->mongo->mongoglue_orm_test;
	}

	function tearDown(){
		$this->db->drop();
		unset($this->db, $this->mongo);
	}

	function setUpTestRelationalClasses(){

		$_emIds = array();

		documents\test::model($this->db)->setAttributes($this->testDocs[0])->insert();
		$doc = documents\test::model($this->db)->findOne();

		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $doc);

		foreach($this->testDetailDocs as $k => $row){
			//var_dump($row);
			documents\testDetail::model($this->db)->setAttributes($row)->insert();
		}
		$cursor = documents\testDetail::model($this->db)->find();

		$this->assertInstanceOf('\\mongoglue\\Cursor', $cursor);

		if($doc){
			foreach($cursor as $k => $row){
				$row->test_id = $doc->_id;
				$_emIds[] = $row->_id;
				$row->save();
			}

			$doc->test_ids = $_emIds;
		}

		$this->assertContainsOnlyInstancesOf('\\MongoId', $_emIds);
		$this->assertTrue($doc->save());
	}

	function testSet(){
		$t = documents\test::model($this->db);
		$t->name = 'sammaye';
		$this->assertEquals('sammaye', $t->name);
	}

	function testUnset(){
		$t = documents\test::model($this->db);
		$t->name = 'sammaye';
		$this->assertEquals('sammaye', $t->name);

		unset($t->name);
		$this->assertNull($t->name);
	}

	function testInsertScenario(){
		$t = documents\test::model($this->db);
		$this->assertEquals('insert', $t->getScenario());
		$this->assertTrue($t->isNew());
	}

	function testGet_SetScenarios(){
		$t = documents\test::model($this->db);

		$this->assertEquals('insert', $t->getScenario());

		$t->setScenario('scen');
		$this->assertEquals('scen', $t->getScenario());
	}

	function testUpdateScenario(){

		$t = documents\test::model($this->db)->setAttributes(array('name' => 'sammaye'))->insert();
		$t = documents\test::model($this->db)->findOne(array('name' => 'sammaye'));

		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $t);
		$this->assertTrue($t->getScenario() == 'update' && $t->isNew() == false);
	}

	function testInsert(){
		$t = documents\test::model($this->db);
		$t->name = 'sammaye';
		$this->assertTrue($t->save());
		$this->assertTrue(isset($t->_id));
	}

	function testUpdate(){
		$t = documents\test::model($this->db);
		$t->name = 'sammaye';
		$this->assertTrue($t->save());

		$t->occupation = 'Dev';
		$this->assertTrue($t->save());

		$t = documents\test::model($this->db)->findById($t->_id);
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $t);
	}

	function testRemove(){
		$t = $this->db->select('test');
		$t->name = 'sammaye';
		$this->assertTrue($t->save());
		$this->assertTrue($t->remove());
	}

	function testDrop(){
		$t = $this->db->select('test');
		$t->name = 'sammaye';
		$this->assertTrue($t->save());
		$this->assertTrue($t->drop());
	}

	function testVirtualFields(){
		$t = $this->db->select('test');
		$t->name = 'sammaye';
		$this->assertTrue($t->save());

		$cursor = $this->db->test->find();
		foreach($cursor as $k => $row){
			$this->assertFalse(isset($row['lastRunEvent']));
			break;
		}
	}

	/**
	 * Test relational behaviour
	 */

	function testManyChildRelations(){

		$this->setUpTestRelationalClasses();

		$doc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $doc);
		$this->assertContainsOnlyInstancesOf('\\mongoglue\\tests\\documents\\testDetail', iterator_to_array($doc->testDetails));
	}

	function testOneChildRelation(){
		$this->setUpTestRelationalClasses();

		$doc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $doc);
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\testDetail', $doc->testDetail);
	}

	function testEmbeddedRelation(){
		$this->setUpTestRelationalClasses();

		$doc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $doc);

		$this->assertContainsOnlyInstancesOf('\\mongoglue\\tests\\documents\\testDetail', iterator_to_array($doc->embeddedDetails));
	}

	function testConditionalRelation(){
		$this->setUpTestRelationalClasses();

		$doc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $doc);
		$array = iterator_to_array($doc->conditionalDetails);
		$this->assertEquals(1, sizeof($array));
	}

	/**
	 * Test events
	 */
	function testAfterConstruct(){

		$t = $this->db->select('test');
		$t->onAfterConstruct();

		$this->assertEquals('afterConstruct', $t->lastRunEvent);
	}

	function testBeforeFind(){

		$t = $this->db->select('test');
		$t->onBeforeFind();

		$this->assertEquals('beforeFind', $t->lastRunEvent);
	}

	function testAfterFind(){

		$t = $this->db->select('test');
		$t->onAfterFind();

		$this->assertEquals('afterFind', $t->lastRunEvent);
	}

	function testBeforeValidate(){

		$t = $this->db->select('test');
		$t->onBeforeValidate();

		$this->assertEquals('beforeValidate', $t->lastRunEvent);
	}

	function testAfterValidate(){

		$t = $this->db->select('test');
		$t->onAfterValidate();

		$this->assertEquals('afterValidate', $t->lastRunEvent);
	}

	function testBeforeSave(){

		$t = $this->db->select('test');
		$t->onBeforeSave();

		$this->assertEquals('beforeSave', $t->lastRunEvent);
	}

	function testAfterSave(){

		$t = $this->db->select('test');
		$t->onAfterSave();

		$this->assertEquals('afterSave', $t->lastRunEvent);
	}

	function testBeforeDelete(){

		$t = $this->db->select('test');
		$t->onBeforeDelete();

		$this->assertEquals('beforeDelete', $t->lastRunEvent);
	}

	function testAfterDelete(){

		$t = $this->db->select('test');
		$t->onAfterDelete();

		$this->assertEquals('afterDelete', $t->lastRunEvent);
	}


	/**
	 * Test behaviours
	 */
	function testInsertBehaviour(){

		$doc = $this->testDocs[0];
		$this->db->select('test')->insert($doc);

		$savedDoc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $savedDoc);
		$this->assertInstanceOf('\MongoDate', $savedDoc->created);
	}

	function testUpdateBehaviour(){
		$doc = $this->testDocs[0];
		$this->db->select('test')->insert($doc);

		$savedDoc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $savedDoc);

		$savedDoc->d = 1;
		$this->assertTrue($savedDoc->save());

		$savedDoc2 = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $savedDoc2);
		$this->assertInstanceOf('\MongoDate', $savedDoc2->updated);

	}

	function testGet__callBehaviour(){
		$doc = $this->testDocs[0];
		$this->db->select('test')->insert($doc);

		$savedDoc = $this->db->select('test')->findOne();
		$this->assertInstanceOf('\\mongoglue\\tests\\documents\\test', $savedDoc);
		$this->assertTrue(is_string($savedDoc->ago($savedDoc->created)));
	}

	/**
	 * Test validation
	 */
	function test_attributes(){
		$t = $this->db->select('test');

		$data['name'] = 'sammaye';
		$data['something_hacky'] = 'You are hacked';
		$valid = $t->validate($data, array(
			array('name', 'string', 'allowEmpty' => false, 'min' => 2, 'message' => 'Not Valid')
		));

		$this->assertTrue(isset($t->name) && !isset($t->something_hacky));
	}


	function testStandardValidator(){
		$t = $this->db->select('test');

		$data['name'] = 'sammaye';
		$valid = $t->validate($data, array(
			array('name', 'string', 'allowEmpty' => false, 'min' => 2, 'message' => 'Not Valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue($valid);
	}

	function testEmbedOneValidator(){
		$t = $this->db->select('test');

		$data['address'] = array(
			'road' => 'elm',
			'town' => 'poop',
			'county' => 'Dev',
			'postal_code' => 'lalala'
		);

		$valid = $t->validate($data, array(
			array('address', 'embedOne', 'rules' => array(
				array('road', 'string', 'allowEmpty' => false, 'message' => 'You must enter a road name'),
				array('town', 'string', 'allowEmpty' => false, 'message' => 'You must enter a town name'),
				array('county', 'string', 'allowEmpty' => false, 'message' => 'You must enter a county name'),
				array('postal_code', 'string', 'allowEmpty' => false, 'message' => 'You must enter a post code')
			))
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue($valid);
	}

	function testEmbedOneObjValidator(){
		$t = $this->db->select('test');

		$data['embedObjects'] = array(
				'name' => ''
		);

		$valid = $t->validate($data, array(
				array('embedObjects', 'embedOne', 'testEmbed')
		));

		$this->assertFalse(sizeof($t->getErrors()) <= 0);
		$this->assertFalse($valid);
	}

	function testEmbedManyValidator(){
		$t = $this->db->select('test');

		$data['address'][] = array(
			'road' => 'elm',
			'town' => 'poop',
			'county' => 'Dev',
			'postal_code' => 'lalala'
		);

		$data['address'][] = array(
			'road' => 'elm1',
			'town' => 'poop1',
			'county' => 'Dev1',
			'postal_code' => 'lalala1'
		);

		$data['address'][] = array(
			'road' => 'elm2',
			'town' => 'poop2',
			'county' => 'Dev2',
			'postal_code' => 'lalala2'
		);

		$valid = $t->validate($data, array(
			array('address', 'embedMany', 'rules' => array(
				array('road', 'string', 'allowEmpty' => false, 'message' => 'You must enter a road name'),
				array('town', 'string', 'allowEmpty' => false, 'message' => 'You must enter a town name'),
				array('county', 'string', 'allowEmpty' => false, 'message' => 'You must enter a county name'),
				array('postal_code', 'string', 'allowEmpty' => false, 'message' => 'You must enter a post code')
			))
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue($valid);
	}

	function testEmbedManyObjValidator(){
		$t = $this->db->select('test');

		$data['embedObjects'][] = array(
				'name' => ''
		);

		$valid = $t->validate($data, array(
			array('embedObjects', 'embedMany', 'testEmbed')
		));

		$this->assertFalse(sizeof($t->getErrors()) <= 0);
		$this->assertFalse($valid);
	}

	function testCustomValidator(){
		$t = $this->db->select('test');

		$data['name'] = 'sammaye';
		$valid = $t->validate($data, array(
			array('name', 'tester', 'message' => 'Not Valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue($valid);
	}

	function testClassValidator(){
		$t = $this->db->select('test');

		$data['name'] = 'sammaye';
		$valid = $t->validate($data, array(
			array('name', 'UsernameF', 'message' => 'Not Valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue($valid);
	}

	function testMultiFieldValidation(){
		$t = $this->db->select('test');

		$data['name'] = 'sammaye';
		$data['oc'] = 'sammaye';
		$valid = $t->validate($data, array(
			array('name,oc', 'UsernameF', 'message' => 'Not Valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
		$this->assertTrue(isset($t->name, $t->oc));
		$this->assertTrue($valid);
	}

	/**
	 * Test all individual validators one by fucking one
	 */

	function testBooleanValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $t->validate($data, array(
				array('name', 'boolean', 'allowNull' => true, 'message' => 'Not valid')
		));

		$this->assertFalse(sizeof($t->getErrors()) <= 0);

		$data['name'] = 1;
		$valid = $t->validate($data, array(
				array('name', 'boolean', 'allowNull' => true, 'message' => 'Not valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);
	}

	function testStringValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $t->validate($data, array(
			array('name', 'string', 'message' => 'Not valid')
		));

		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 1;
		$valid = $t->validate($data, array(
			array('name', 'string', 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testObjExist(){
		$t = $this->db->select('test');
		$t->name = 'd';
		$this->assertTrue($t->save());

		$o = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $o->validate($data, array(
			array('name', 'objExist', 'class'=>'test', 'field'=>'name', 'notExist' => true,
					'message' => 'That already exists please try another.')
		));
		$this->assertFalse($valid);
	}

	function testInValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $t->validate($data, array(
			array('name', 'in', 'range' => array('d'), 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$valid = $t->validate($data, array(
			array('name', 'in', 'range' => array('e'), 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testNinValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $t->validate($data, array(
			array('name', 'nin', 'range' => array('e'), 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$valid = $t->validate($data, array(
			array('name', 'nin', 'range' => array('d'), 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testRegexValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'd';
		$valid = $t->validate($data, array(
			array('name', 'regex', 'pattern' => '/^[a-zA-Z0-9]$/', 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$valid = $t->validate($data, array(
			array('name', 'regex', 'pattern' => '/^[a-zA-Z0-9]$/', 'nin' => true, 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testCompareValidator(){

		$t = $this->db->select('test');
		$data['name'] = 'd';
		$t->test = 'd';
		$valid = $t->validate($data, array(
			array('name', 'compare', 'field' => 'test', 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 'd';
		$t->test = 'e';
		$valid = $t->validate($data, array(
			array('name', 'compare', 'field' => 'test', 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testNumberValidator(){
		$t = $this->db->select('test');
		$data['name'] = 1;
		$valid = $t->validate($data, array(
			array('name', 'number', 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 'd';
		$valid = $t->validate($data, array(
			array('name', 'number', 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testUrlValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'http://www.facebook.com';
		$valid = $t->validate($data, array(
			array('name', 'url', 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 'c';
		$valid = $t->validate($data, array(
				array('name', 'url', 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testTokenizedValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'c';
		$valid = $t->validate($data, array(
			array('name', 'tokenized', 'max' => 1, 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 'c,d';
		$valid = $t->validate($data, array(
			array('name', 'tokenized', 'max' => 1, 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testEmailValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'g@g.com';
		$valid = $t->validate($data, array(
			array('name', 'email', 'message' => 'Not valid')
		));
		$this->assertTrue(sizeof($t->getErrors()) <= 0);

		$data['name'] = 'g@g'; // This does not validate to PHPs version but does to RFC
		$valid = $t->validate($data, array(
			array('name', 'email', 'message' => 'Not valid')
		));
		$this->assertFalse(sizeof($t->getErrors()) <= 0);
	}

	function testSafeValidator(){
		$t = $this->db->select('test');
		$data['name'] = 'g@g.com';
		$valid = $t->validate($data, array(
			array('name', 'safe')
		));
		$this->assertTrue(isset($t->name));
	}
}