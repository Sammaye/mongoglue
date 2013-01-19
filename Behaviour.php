<?php

namespace mongoglue;

/**
 * This class represents a single behaviour which can be run inline with the model at 
 * anytime. It is similar to how Yii uses behaviours so make sure to check those out.
 */
class Behaviour{

	/**
	 * The owner model where this behaviour was called from
	 * @example User model calls timestamp behaviour as such the owner is the User model
	 * @var Model
	 */
	public $owner;

	/**
	 * Sets the public scoped attributes of your behaviour which are accessible to this class
	 * @param array $a
	 */
	public function attributes($a){
		if(is_array($a)){
			foreach($a as $k => $v){ $this->$k = $v; }
		}
	}

	/**
	 * The events of the owner model
	 */
	public function afterConstruct(){ return true; }
	
	public function beforeValidate(){ return true; }

	public function afterValidate(){ return true; }

	public function beforeSave(){ return true; }

	public function afterSave(){ return true; }

	public function beforeDelete(){ return true; }

	public function afterDelete(){ return true; }

	public function beforeFind(){ return true; }

	public function afterFind(){ return true; }
}