<?php

namespace mongoglue\tests\documents;

class testDetail extends \mongoglue\Document{

	function collectionName(){
		return 'test_detail';
	}

	public static function model($mongo, $dbname = null, $class = __CLASS__){
		return parent::model($mongo, $dbname, $class);
	}

}