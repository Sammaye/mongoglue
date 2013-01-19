<?php

namespace mongoglue\validators;

class Base{

	public $model;

	private $attributes;

	function __get($k){
		return isset($this->attributes[$k]) ? $this->attributes[$k] : null;
	}

	function __set($k, $v){
		$this->attributes[$k] = $v;
	}

	function __isset($k){
		return isset($this->attributes[$k]) ? isset($this->attributes[$k]) : isset($this->$k);
	}

	function validate($validator, $field, $value, $params = array()){
		return $this->$validator($field, $value, $params);
	}

	function setAttributes($a){
		$this->attributes = array();
		if(is_array($a)){
			foreach($a as $k => $v){
				$this->attributes[$k] = $v;
			}
		}
	}

	public function isEmpty($value, $trim  = false){
		return $value===null || $value===array() || $value==='' || $trim && is_scalar($value) && trim($value)==='';
	}

	/**
	 * Field is required
	 */
	public function required($field, $value){
		if($this->isEmpty($value)){
			return false;
		}
		return true;
	}

	/**
	 * Checks if value entered is equal to 1 or 0, it also allows null values
	 *
	 * @param string $field The field to be tested
	 * @param mixed $value The field value to be tested
	 * @param array $params The parameters for the validator
	 */
	public function boolean($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowNull' => false,
			'falseValue' => 0,
			'trueValue' => 1
		), $params));

		if($this->allowNull && $this->isEmpty($value))
			return true;

		if($value === $this->trueValue || ($value === $this->falseValue || !$value)){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * Detects the character length of a certain fields value
	 *
	 * @param $field
	 * @param $value
	 * @param $params
	 */
	public function string($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'min' => null,
			'max' => null,
			'is' => null,
			'encoding' => null,
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		if(!is_string($value))
			return false;

		if(function_exists('mb_strlen') && $this->encoding)
			$str_length=mb_strlen($value, $this->encoding ? $this->encoding : 'UTF-8');
		else
			$str_length=strlen($value);

		if($this->min){
			if($this->min > $str_length){ // Lower than min required
				return false;
			}
		}

		if($this->max){
			if($this->max < $str_length){
				return false;
			}
		}
		return true;
	}

	public function objExist($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'class' => null,
			'condition' => null,
			'field' => null,
			'notExist' => false
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$cName = $this->class;
		$condition = isset($this->condition) ? $this->condition : array();
		$object = $this->model->getDatabase()->select($cName)->findOne(array_merge(array($this->field=>$value), $condition));
		if($object){
			return $this->notExist ? false : true;
		}else{
			return $this->notExist ? true : false;
		}
	}

	public function in($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'range' => array(),
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$found = false;
		foreach($this->range as $match){
			if($match == $value){
				$found = true;
			}
		}

		if(!$found){
			return false;
		}
		return true;
	}

	public function nin($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'range' => array(),
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$found = false;
		foreach($this->range as $match){
			if($match == $value){
				$found = true;
			}
		}

		if($found){
			return false;
		}
		return true;
	}

	public function regex($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'pattern' => null,
			'nin' => false
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		if($this->nin){
			if(preg_match($this->pattern, $value) > 0){
				return false;
			}
		}else{
			if(preg_match($this->pattern, $value) <= 0 || preg_match($this->pattern, $value) === false){
				return false;
			}
		}
		return true;
	}

	public function compare($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'with' => true,
			'field' => null,
			'operator' => '=',
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$with_val = $this->with;
		if($this->field){
			$with_val = $this->model->{$this->field};
		}

		switch($this->operator){
			case '=':
			case '==':
				if($value == $with_val){
					return true;
				}
				break;
			case '!=':
				if($value != $with_val){
					return true;
				}
				break;
			case ">=":
				if($value >= $with_val){
					return true;
				}
				break;
			case ">":
				if($value > $with_val){
					return true;
				}
				break;
			case "<=":
				if($value <= $with_val){
					return true;
				}
				break;
			case "<":
				if($value < $with_val){
					return true;
				}
				break;
		}
		return false;
	}

	public function number($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'integerOnly' => true,
			'max' => null,
			'min' => null,
			'intPattern' => '/^\s*[+-]?\d+\s*$/',
			'numPattern' => '/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/'
		), $params));

		//var_dump($vlaue); exit();
		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		if($this->integerOnly){
			if(preg_match($this->intPattern, $value) > 0){
			}else{
				return false;
			}
		}elseif(preg_match($this->numPattern, $value) < 0 || !preg_match($this->numPattern, $value)){
			return false;
		}

		if($this->min){
			if($value < $this->min){
				return false;
			}
		}

		if($this->max){
			if($value > $this->max){
				return false;
			}
		}
		return true;
	}

	public function url($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$parsed_url = parse_url($value);

		if(!$parsed_url){
			return false;
		}

		if(isset($parsed_url['scheme'])){
			if(!isset($parsed_url['host'])){
				return false;
			}else{
				return true;
			}
		}

		return false;
	}

	public function file($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'ext' => null,
			'size' => null,
			'type' => null
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$fieldValue = $value;

		if($fieldValue['error'] === UPLOAD_ERR_OK){
			if(isset($this->ext)){
				$path = pathinfo($fieldValue['name']);

				$found = false;
				foreach($this->ext as $ext){
					if($ext == $path['extension'])
						$found = true;
				}

				if(!$found){
					return false;
				}
			}

			if(isset($this->size)){
				if(isset($this->size['gt'])){
					if($fieldValue['size'] < $this->size['gt']){
						return false;
					}
				}elseif(isset($this->size['lt'])){
					if($fieldValue['size'] > $this->size['lt']){
						return false;
					}
				}
			}

			if(isset($this->type)){
				if(preg_match("/".$this->type."/i", $fieldValue['type']) === false || preg_match("/".$this->type."/i", $fieldValue['type']) < 0){
					return false;
				}
			}
		}else{
			switch ($fieldValue['error']) {

				// TODO add way to know which thing went wrong init

				case UPLOAD_ERR_INI_SIZE:
					return false;
				case UPLOAD_ERR_FORM_SIZE:
					return false;
				case UPLOAD_ERR_PARTIAL:
					return false;
				case UPLOAD_ERR_NO_FILE:
					return false;
				case UPLOAD_ERR_NO_TMP_DIR:
					return false;
				case UPLOAD_ERR_CANT_WRITE:
					return false;
				case UPLOAD_ERR_EXTENSION:
					return false;
				default:
					return false;
			}
		}
		return true;
	}

	public function tokenized($field, $value, $params){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
			'del' => '/[\s]*[,][\s]*/',
			'max' => null
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		$ex_val = preg_split($this->del, $value);

		if(isset($this->max)){
			if(sizeof($ex_val) > $this->max){
				return false;
			}
		}
		return true;
	}


	public function email($field, $value, $params = array()){

		$this->setAttributes(array_merge(array(
			'allowEmpty' => true,
		), $params));

		if($this->allowEmpty && $this->isEmpty($value)){
			return true;
		}elseif($this->isEmpty($value)){
			return false;
		}

		if(filter_var($value, FILTER_VALIDATE_EMAIL)){
			return true;
		}
		return false;
	}

	public function safe($field, $value, $params = array()){
		return true; // Just do this so the field gets sent through
	}

	public function date($field, $value, $params = array()){

		$this->setAttributes(array_merge(array(
			'format' => 'd/m/yyyy'
		), $params));

		// Lets tokenize the date field
		$date_parts = preg_split('/[-\/\s]+/', $value); // Accepted deliminators are -, / and space

		switch($this->format){
			case 'd/m/yyyy':
				if(sizeof($date_parts) != 3){
					return false;
				}

				if(preg_match('/[1-32]/', $date_parts[0]) > 0 && preg_match('/[1-12]/', $date_parts[1]) > 0 && preg_match('/[0-9]{4}/', $date_parts[2]) && $date_parts[2] <= date('Y')){
					// If date matches formation and is not in the future in this case
					return true;
				}
				break;
		}
		return false;
	}
}