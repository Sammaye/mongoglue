<?php

namespace mongoglue\tests\documents;

class test extends \mongoglue\Document{

	/** @virtual */
	public $lastRunEvent;
	
	public $sessions = array();
	
	public $embedObjects = array();

	function collectionName(){
		return 'test';
	}

	function behaviours(){
		return array('Timestamp');
	}

	function relations(){
		return array(
			'testDetail' => array('one', 'testDetail', 'test_id'),
			'testDetails' => array('many', 'testDetail', 'test_id'),
			'embeddedDetails' => array('many', 'testDetail', '_id', 'on' => 'test_ids'),
			'conditionalDetails' => array('many', 'testDetail', 'test_id', 'where' => array(
				'name' => 'Programming'
			))
		);
	}

	public static function model($mongo, $dbname = null, $class = __CLASS__){
		return parent::model($mongo, $dbname, $class);
	}

	function afterConstruct(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function beforeFind(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function afterFind(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function beforeValidate(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function afterValidate(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function beforeSave(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function afterSave(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function beforeDelete(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}

	function afterDelete(){
		$this->lastRunEvent = __FUNCTION__;
		return true;
	}
	
	function UsernameF($field, $value, $params){
		return true;
	}
}