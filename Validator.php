<?php

namespace mongoglue;

/**
 * Extend this class to add your own validators
 */
class Validator{

	public $owner;
	public $attribute;
	public $value;

	public $allowEmpty = true;

	function attributes($a){
		if(is_array($a)){
			foreach($a as $k => $v){
				$this->$k = $v;
			}
		}
	}

	/**
	 * This is where the magic happens. Put all of your validation stuff in here and we will run it
	 *
	 * @param Model $model
	 * @param string $attribute
	 * @param mixed $value
	 */
	function validate($attribute, $value){}

	public function isEmpty($value, $trim  = false){
		return $value===null || $value===array() || $value==='' || $trim && is_scalar($value) && trim($value)==='';
	}
}