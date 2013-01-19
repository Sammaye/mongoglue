<?php

namespace mongoglue\tests\documents;

class testEmbed extends \mongoglue\Document{
	function collectionName(){
		return 'test';
	}
	
	function rules(){
		return array(
			array('name', 'string', 'allowEmpty' => false, 'message' => 'You must fill in a god damn name')		
		);
	}

	public static function model($mongo, $dbname = null, $class = __CLASS__){
		return parent::model($mongo, $dbname, $class);
	}
}