<?php

namespace mongoglue;

class Cursor implements \Iterator, \Countable{

	public $condition;
	public $sort;
	public $skip = 0;
	public $limit;

	public $class;

	private $cursor;
	private $current;
	private $ok;

	private $queried = false;

	private $database;

	private $position = 0;

    public function __construct($cursor, $className = false /* Don't use Active Record */, $db = null) {
    	$this->class = $className;
    	$this->database = $db;

    	if($cursor instanceof \MongoCursor){
    		$this->cursor = $cursor;
        	$this->cursor->reset();
    	}else{

			// If we are not dealing with a \MongoCursor then let's find out what we are supposed to do with it
			//$this->cursor = array();

    		if($className){
				// Then we are doing an active query
				$this->condition = $cursor;
				$o = $db->select($className);
				$this->cursor = $o->getCollection()->find($cursor);
    		}elseif(is_array($cursor)){

        		// If we have just been provided without a class name it might be a result
        		if(isset($cursor['result']) && isset($cursor['ok'])){

        			// This is an aggregation result
        			$this->ok = $cursor['ok'];
        			$this->cursor = $cursor['result'];

        		}else{
					$this->cursor = $cursor; // otherwise we assume this is an array of docs
        		}
        	}
        }

        return $this; // Maintain chainability
    }

    /**
     * If we call a function that is not implemented here we try and pass the method onto
     * the \MongoCursor class, otherwise we produce the error that normally appears
     *
     * @param $method
     * @param $params
     */
    function __call($method, $params = array()){
		if($this->cursor() instanceof \MongoCursor && method_exists($this->cursor(), $method)){
			return call_user_func_array(array($this->cursor(), $method), $params);
		}
		trigger_error("Call to undefined function {$method} on the cursor");
    }

    function cursor(){
    	return $this->cursor;
    }

    function count(){
    	if($this->cursor() instanceof \MongoCursor)
    		return $this->cursor()->count();
    	elseif($this->cursor())
    		return sizeof($this->cursor);
    }

    /**
     * I refuse to do client side sorting at this minute
     *
     * @param $fields
     */
    function sort(array $fields){
    	if($this->cursor() instanceof \MongoCursor)
			$this->cursor()->sort($fields);
		return $this;
    }

    /**
     * If the cursor is not a server-side cursor this will perform an in-memory
     * slice of the array
	 *
     * @param int $num
     */
    function skip($num = 0){
    	if($this->cursor() instanceof \MongoCursor)
			$this->cursor()->skip($num);
		elseif($this->cursor())
			$this->skip = $num;

		return $this;
    }

    /**
     * This will either perform a limit on the MongoDB cursor or a
     * in-memory limit
     *
     * @param int $num
     */
    function limit($num = 0){
    	if($this->cursor() instanceof \MongoCursor)
			$this->cursor()->limit($num);
		elseif($this->cursor())
			$this->limit = $num;

		return $this;
    }

    function rewind() {
    	if($this->cursor() instanceof \MongoCursor)
        	$this->cursor()->rewind();
        elseif($this->cursor()){
        	reset($this->cursor);
        }

        return $this;
    }

    function current() {
    	if($this->class !== false){
    		$this->current = $this->database->select($this->class);
    	}else{
    		return $this->cursor() instanceof \MongoCursor ? $this->cursor()->current() : current($this->cursor);
    	}

    	$this->current->setIsNew(false);
    	$this->current->setScenario('update');

    	if(!$this->current->onBeforeFind()) return null;

    	// We get the current result from either the MongoDB cursor or our own cursor
    	$this->current->setAttributes($this->cursor() instanceof \MongoCursor ? $this->cursor()->current() : current($this->cursor));
    	$this->current->onAfterFind();

        return $this->current;
    }

    function key() {
    	if($this->cursor() instanceof \MongoCursor)
        	return $this->cursor()->key();
        elseif($this->cursor())
        	return key($this->cursor);
    }

    function next() {
    	if($this->cursor() instanceof \MongoCursor)
        	return $this->cursor()->next();
        elseif($this->cursor())
        	return next($this->cursor);
    }

    /**
     * This is the first function always run when you start to iterate through a foreach as such
     * this is the natural place to put code that can be used to lazy load certain processing like
     * the slicing of arrays after in-memory operators were added
     */
    function valid() {
    	if($this->cursor() instanceof \MongoCursor)
        	return $this->cursor()->valid();
        elseif($this->cursor()){

        	// If this is the first time we have run this iterator then let us do in memory aggregation operations now
        	if(!$this->queried){
        		if($this->skip > 0)
        			$this->cursor = array_values(array_slice($this->cursor, $this->skip, $this->limit));
        		else
        			$this->cursor = array_slice($this->cursor, $this->skip, $this->limit);
        	}

        	$this->queried = true;
        	return !is_null(key($this->cursor));
        }
    }
}