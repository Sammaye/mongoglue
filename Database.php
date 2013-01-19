<?php

namespace mongoglue;

/**
 * This class represents a database, it basically extends MongoDB class
 */
class database{

	/**
	 * Name of the database
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The server object
	 *
	 * @var \mongoglue\Server
	 */
	private $server;

	/**
	 * The raw database object
	 *
	 * @var MongoDB
	 */
	private $database;

	/**
	 * Contains a cache of all the indexes assigned to
	 * various collections
	 * @var array
	 */
	private $indexes;

	/**
	 * This denotes loaded indexes so that we don't have to keep
	 * pinging no-ops to the database server. This will go by collection.
	 *
	 * @example array('users' => 1, 'video' => 1, 'sessions' => 1) Will all denote
	 * collections whose indexes have been loaded
	 * @var array
	 */
	private $loadedIndexes = array();

	/**
	 * Will __get a collection from the database
	 *
	 * @param string $key
	 */
	public function __get($key){
		return $this->selectCollection($key);
	}

	/**
	 * Do setup stuff here. Atm the database class only takes an object of \mongoglue\Server. I am intending to make it work
	 * for all methods including straight from MongoClient however it seems like role confusion here and really the server should be
	 * confined to it's class and this class should take a server
	 *
	 * @param string $dbname
	 * @param MongoClient $server
	 * @return boolean success of the construct
	 */
	function __construct($dbname, $server){

		if($server instanceof \mongoglue\Server){

			// Then we can use this as normal
			$this->server = $server;
			$this->database = $server->connection()->selectDB($dbname);
			$this->name = $dbname;

			return true;
		}else{
			trigger_error("You must enter a valid mongoglue server.");
		}
	}

	/**
	 * Will call any function I do not need to override within the MongoDB class and if that function does
	 * not exist will try and call any model.
	 *
	 * @param string $method
	 * @param array $params
	 * @return mixed
	 */
	function __call($method, $params = array()){
		if(method_exists($this->database, $method)){
			return call_user_func_array(array($this->database, $method), $params);
		}elseif($class = $this->select($method)){
			return $class;
		}else{
			trigger_error("No such model or method exists on the Database class: {$method}");
			return false;
		}
	}

	/**
	 * Extends the MongoDB createCollection wrapping it with my own class but
	 * I don't atm use it because I don't think I need to. Collections can be created
	 * through the active record model
	 *
	 * @param string $name
	 * @param boolean $capped
	 * @param int $size
	 * @param int $max
	 */
	function createCollection($name, $capped = false, $size = 0, $max = 0){
		return $this->getDb()->createCollection($name, $capped, $size, $max);
	}

	/**
	 * This will get the raw collection and run any index OPs needed. Basically it does the same as
	 * the driver but it runs an index file if needed.
	 *
	 * @param string $collectionName
	 * @throws Exception
	 * @return MongoCollection $collection The collection found
	 */
	function selectCollection($collectionName){

		$db = $this->getDb();

		if($collectionName == '' || !$collectionName){
			trigger_error('The collection name was empty when trying to get a collection');
		}

		$collection = $db->selectCollection($collectionName);

		// Right now if we are the automated indexing abilities of this class lets use them good
		if(!array_key_exists($collectionName, $this->loadedIndexes) && array_key_exists($collectionName, $this->getIndexes())){
			$index_info = $this->indexes[$collectionName];
			foreach($index_info as $k=>$v){
				$collection->ensureIndex($v[0], isset($v[1]) ? $v[1] : array());
			}
			$this->loadedIndexes[$collectionName] = 1;
		}

		/* Return the mongo collection found */
		return $collection;
	}

	/**
	 * Selects a model
	 *
	 * @example $db->select('User')->find();
	 * @example $db->user()->find();
	 *
	 * @param string $modelName
	 * @return Model
	 */
	function select($modelName, $scenario = null){

		// If this is surrounded in a namespace then take it off, documents should load from only one namespace
		if(strpos($modelName, '\\')!==false){
			$parts = explode('\\', $modelName);
			$modelName = end($parts);
		}

		if($resolvedName = $this->server->canLoadClass($modelName, 'Document')){
			$classname = $this->getServer()->resolveDocumentClassName($modelName);
			$o = new $classname($this);
			if($scenario) $o->setScenario($scenario);
			return $o;
		}
		return false;
	}

	/**
	 * Get the raw DB object
	 *
	 * @return MongoDB $database
	 */
	function getDb(){

		if(empty($this->database))
			trigger_error('The database has not been initialised from MongoDB yet.');

		return $this->database;
	}

	function getServer(){
		return $this->server;
	}

	/**
	 * Will either include the indexes file or just do nothing, either way it will return
	 * the indexes we have on file whether they exist or not
	 */
	function getIndexes(){
		if(!is_array($this->indexes)){
			if($file_name = $this->getServer()->canLoadClass('\mongoglue\Indexes')){
				$this->indexes = include $file_name;
			}else{
				$this->indexes = array();
			}
		}elseif($this->indexes == null){
			$this->indexes = array();
		}
		return $this->indexes;
	}

	/**
	 * Gets the default write concern options for all queries
	 */
	function getDefaultWriteConcern(){
		return $this->getServer()->getDefaultWriteConcern();
	}
}