<?php

namespace mongoglue;

/**
 * The default scope of functions within this class is public
 */
class Document{

	/**
	 * Stores the raw document ready for saving
	 * @var array
	 */
	private $document;

	/**
	 * Stores a cache of the rules of the Document
	 * @var array
	 */
	private $rules = array();

	/**
	 * Stores a cache of all fetched relations
	 * @var array
	 */
	private $relations;

	/**
	 * The model scenario
	 * @var string
	 */
	Private $scenario;

	/**
	 * A cached list of attached behaviours for this model
	 * @var array
	 */
	private $behaviours = array();

	/**
	 * Is this a new record? True for yes and false for no
	 * @var boolean
	 */
	private $newRecord = true;

	/**
	 * When you perform a projected find() this variable holds the
	 * fields you selected to be projected so that the fields not contained
	 * in this class are not overwritten by some noobish coding
	 * @var array
	 */
	private $selectedFields;

	/**
	 * Stores all of the error messages that have been recieved from this round
	 * of validation. Each round of validation will reset this array.
	 * @var array
	 */
	private $errors;

	/**
	 * Has this document been validated?
	 * @var boolean
	 */
	private $validated = false;

	/**
	 * Is this document valid after validation?
	 * @var boolean
	 */
	private $valid;

	/**
	 * Stores the database object
	 * @var MongoDB|\mongoglue\Database
	 */
	private $db;

	/**
	 * Base validator object, this helps us load a default set of validators
	 */
	private $baseValidator;

	/**
	 * Any variable not explicity set within the model will be counted
	 * as a database of relational variable
	 *
	 * This function will first call a method of the variable as get$k or
	 * will continue processing the rest
	 *
	 * @param string $k
	 */
	function __get($k){

		$relations = $this->relations();

		if(method_exists($this, 'get'.$k)){
			return $this->{'get'.$k}();
		}elseif(property_exists($this, $k)){
			return $this->$k;
		}elseif(isset($relations[$k])){

			// Then this is a relation, now to decide whether to get the cache or refresh
			if(isset($this->relations[$k])){
				return $this->relations[$k];
			}
			$this->relations[$k] = $this->with($k, $relations[$k]);

			// If with() is called indirectly we cache the relation since it is the one
			// from the model itself as such a trusted configuration
			return $this->relations[$k];
		}
		return isset($this->document[$k]) ? $this->document[$k] : null;
	}

	/**
	 * This will try and get a function of set$k first or will set a relation
	 * or finally a document field
	 *
	 * @param string $k
	 * @param string $v
	 */
	function __set($k, $v){
		$relations = $this->relations();

		if(method_exists($this, 'set'.$k)){
			$this->{'set'.$k}($v);
		}elseif(property_exists($this, $k)){
			$this->$k = $v;
		}elseif(isset($relations[$k])){
			$this->relations[$k] = $v;
		}else{
			$this->document[$k] = $v;
		}
	}

	/**
	 * Magic unset allows for the unsetting for variables you normally couldn't in the
	 * relations and the document itself
	 * @param string $k
	 */
	public function __unset($k){
		if(property_exists($this, $k)){
			unset($this->$k);
		}elseif(isset($this->relations[$k])){
			unset($this->relations[$k]);
		}else{
			unset($this->document[$k]);
		}
	}

	/**
	 * Magic isset allows us to detect if relations and document fields are set as though
	 * they exist within the class itself.
	 * @param string $name
	 */
	public function __isset($name){
		if(property_exists($this, $name))
			return isset($this->$name);
		elseif(isset($this->relations[$name])){
			return isset($this->relations[$name]);
		}else{
			return isset($this->document[$name]);
		}
	}

	/**
	 * Will attempt to call one of the behaviours functions if none exist in here by that name
	 * @param string $name
	 * @param array $parameters
	 * @return mixed|boolean
	 */
	function __call($name, $parameters){
		foreach($this->behaviours as $k => $attr){
			if(is_object($attr)){
				if(method_exists($attr, $name)){
					return call_user_func_array(array($attr,$name),$parameters); // Call behaviour methods
				}
			}
		}
		return false;
	}

	/**
	 * Will attempt to find out for certain whether or not a method exists within the class
	 * by searching all links classes as well for it.
	 * @param string $f
	 * @return boolean
	 */
	function method_exists($f){
		if(method_exists($this, $f)){
			return true;
		}else{
			foreach($this->behaviours as $k => $attr){
				if(is_object($attr)){
					if(method_exists($attr, $f)){
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * This function returns the collection name for this model
	 *
	 * @return string
	 */
	function collectionName(){
		return false;
	}

	/**
	 * This is our primary key field name, normally _id
	 * @return string $primaryKey
	 */
	function primaryKey(){
		return '_id';
	}

	/**
	 * A schema is not required, it merely defines a set of base fields and their rules
	 *
	 * @example of an normal field declaration
	 *
	 * array(array('d' => array('embedMany', 'UserExtLinks')))
	 *
	 * array(array('d' => array('embedMany', array(
	 * 	array('username', 'string', 'max' => 160),
	 * 	array('inced', 'integer', 'length' => 2)
	 * ))))
	 *
	 */
	function rules(){ return array(); }

	/**
	 * These are the relations of your model.
	 *
	 * @example
	 *
	 * array(
	 * 	'user' => array('many', 'User', 'user_ids' [, 'the_other_document_key']);
	 * )
	 *
	 * array(
	 * 	'user' => array('one', 'User', 'user_id')
	 * )
	 *
	 * In these examples both relations will check for an array of _ids and act occordingly otherwise it will perform
	 * either a find or a findOne()
	 *
	 * @return array $something
	 */
	function relations(){ return array(); }

	/**
	 * The behaviours of the active record model
	 *
	 * @example array('timestamp' => array(//options))
	 * @example array('timestamp')
	 *
	 * @return array
	 */
	function behaviours(){ return array(); }

	/**
	 * The main construct to the Document. Will do set-up stuff for us
	 * @param string $scenario
	 */
	function __construct($db, $scenario = 'insert'){

		$this->db = $db;

		// Setup behaviours
		foreach($this->behaviours() as $name => $attr){

			// If the key is numeric it must be a single entry
			if(is_numeric($name)){
				$name = $attr;
				$attr = array();
			}

			$this->attachBehaviour($name, $attr);
		}

		if($scenario) $this->setScenario($scenario);

		// Run reflection and cache it if not already there
		if(!$this->db->getServer()->getObjCache(get_class($this)) && get_class($this) != 'Document' /* We can't cache document */){
			$virtualFields = array();
			$documentFields = array();

			$reflect = new \ReflectionClass(get_class($this));
			$class_vars = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED); // Pre-defined doc attributes

			foreach ($class_vars as $prop) {
				$docBlock = $prop->getDocComment();

				// If it is not public and it is not marked as virtual then assume it is document field
				if($prop->isProtected() || preg_match('/@virtual/i', $docBlock) <= 0){
					$documentFields[] = $prop->getName();
				}else{
					$virtualFields[] = $prop->getName();
				}
			}
			$this->db->getServer()->setObjectCache(get_class($this),
				sizeof($virtualFields) > 0 ? $virtualFields : null,
				sizeof($documentFields) > 0 ? $documentFields : null
			);
		}

		$this->onAfterConstruct();
		return $this; // maintain chain
	}


	/**
	 * The model function allows for static accession of a models behaviours and
	 * functions on the fly when needed. By default it will return the current class
	 * in $this context not self::
	 *
	 * @param string $class
	 */
	public static function model($mongo, $dbname = null, $class = __CLASS__){

		if($mongo instanceof \mongoglue\Server && $dbname !== null){
			$db = $mongo->$dbname;
		}elseif($mongo instanceof \mongoglue\Database){
			$db = $mongo;
		}else{
			trigger_error("The model function for $class could not be initialised due to being called wrong.");
		}

		$o = new $class($db);
		return $o;
	}

	/**
	 * An aggregate helper that does not support active record but it can be called from the model
	 */
	function aggregate($pipelines){
		if(method_exists('MongoCollection', 'aggregate')){
			return new mongoglue\Cursor($this->getCollection()->aggregate($pipelines));
		}else{
			trigger_error("You do not have the Mongo extension required to use the aggregation features. Please upgrade your driver using PECL or Github.");
		}
	}

	/**
	 * Allows for searching a subset of model fields; should not be used without a prior constraint.
	 *
	 * Will return an active cursor containing the search results.
	 *
	 * @param array $fields
	 * @param string|int $term
	 * @param array $extra
	 * @param string $class
	 *
	 * @return \mongoglue\Cursor of the results
	 */
	function search($fields = array(), $term = '', $extra = array()){

		$query = array();

		$working_term = trim(preg_replace('/(?:\s\s+|\n|\t)/', '', $term)); // Strip all whitespace to understand if there is actually characters in the string

		if(strlen($working_term) <= 0 || empty($fields)){ // I dont want to run the search if there is no term
			$result = $this->find($extra); // If no term is supplied just run the extra query placed in
			return $result;
		}

		$broken_term = explode(' ', $term);

		// Strip whitespace from query
		foreach($broken_term as $k => $term){
			$broken_term[$k] = trim(preg_replace('/(?:\s\s+|\n|\t)/', '', $term));
		}

		// Now lets build a regex query
		$sub_query = array();
		foreach($broken_term as $k => $term){

			// Foreach of the terms we wish to add a regex to the field.
			// All terms must exist in the document but they can exist across any and all fields
			$field_regexes = array();
			foreach($fields as $k => $field){
				$field_regexes[] = array($field => new MongoRegex('/'.$term.'/i'));
			}
			$sub_query[] = array('$or' => $field_regexes);
		}
		$query['$and'] = $sub_query; // Lets make the $and part so as to make sure all terms must exist
		$query = array_merge($query, $extra); // Lets add on the additional query to ensure we find only what we want to.

		// TODO Add relevancy sorting
		$result = $this->find($query);
		return $result;
	}

	/**
	 * Cleans a document of all variables and behaviours, effectively restructuring it
	 * @return boolean success
	 */
	function clean(){

		// Right lets null the document parts
		$this->document = array();
		$this->schema = array();

		foreach($this->behaviours as $k => $obj){
			unset($obj);
		}
		$this->behaviours = array();

		foreach($this->relations as $k => $obj){
			unset($obj);
		}
		$this->relations = array();

		// Lets rebind the behaviours
		foreach($this->behaviours() as $name => $attr){
			$this->attachBehaviour($name, $attr);
		}

		return true;
	}

	/**
	 * Refreshes the document from disk/memory when an operation(s) is commited
	 * to the model
	 */
	function refresh(){

		$rawDoc = $this->getCollection()->find(array('_id' => $this->document['_id']));

		if($this->clean() && isset($rawDoc['_id'])){
			$this->setAttributes($rawDoc);
			return true;
		}
		return false;
	}

	/**
	 * FindOne document and return it
	 * @param array $condition
	 * @return \mongoglue\Document|NULL
	 */
	function findOne($condition = array()){
		if($this->onBeforeFind()){

			$row = $this->getCollection()->findOne($condition);
			if(!$row)
				return null;

			$this->setAttributes($row);

			$this->setScenario('update');
			$this->setIsNew(false);

			$this->onAfterFind();
			return $this;
		}else{
			return null;
		}
	}

	/**
	 * Find many documents and return a cursor
	 * @param array $condition
	 * @return \mongoglue\Cursor|array
	 */
	function find($condition = array()){
		if($this->onBeforeFind()){
			$cursor = new \mongoglue\Cursor($this->getCollection()->find($condition), get_class($this), $this->db);
			$this->onAfterFind();

			return $cursor;
		}else{
			return array();
		}
	}

	/**
	 * Find a doc by _id, since you can't find more than one by it
	 * @param string|MongoId $id
	 * @return \mongoglue\Document|NULL
	 */
	function findById($id){
		if(!$id instanceof \MongoId)
			$id = new \MongoId($id);

		return $this->findOne(array('_id' => $id));
	}

	/**
	 * Insert a new Document
	 * @return boolean|array
	 */
	function insert($options = array()){
		if($this->isNew() && $this->onBeforeSave()){

			$document = $this->getRawDocument();

			// Sets a new MongoId
			if(!isset($document['_id'])) $document['_id'] = new \MongoId();

			$r = $this->getCollection()->insert($document, !empty($options) ? array_merge($this->getDatabase()->getDefaultWriteConcern(), $options) : array());
			$this->onAfterSave();

			$this->setIsNew(false);
			$this->setScenario('update');

			return $r;
		}
		return false;
	}

	/**
	 * This is a none-active record bound function. 90% of the time you do not want this
	 * please consider carefully whether or not you really need this. DO NOT PASS GO UNTIL YOU HAVE
	 * @param $query
	 * @param $queryOptions
	 */
	function update($query, $queryOptions = array()){
		return $this->getCollection()->update($query, array_merge($this->getDatabase()->getDefaultWriteConcern(), $queryOptions));
	}

	/**
	 * Saves a new or old document with added validation if told to do so
	 * @param boolean $runValidation
	 * @return boolean|array
	 */
	function save($runValidation = false, $options = array()){

		// Hmm should I be running validation before onBeforeSave() ?
		if($runValidation && !$this->validate())
			return false;

		if(!$this->onBeforeSave())
			return false;

		// Get the document as late as possible incase of event changes
		$document = $this->getRawDocument();

		if($this->isNew()){
			if(!isset($document['_id'])) $document['_id'] = $this->_id = new \MongoId();
		}

		$r = $this->getCollection()->save($document, !empty($options) ? array_merge($this->getDatabase()->getDefaultWriteConcern(), $options) : array());
		$this->onAfterSave();

		$this->setIsNew(false);
		$this->setScenario('update');

		return $r;
	}

	/**
	 * Removes a document from the collection
	 * @return array|boolean
	 */
	function remove($options = null){
		if($this->onBeforeDelete()){
			$r = $this->getCollection()->remove(array('_id' => $this->document['_id']),
					!empty($options) ? array_merge($this->getDatabase()->getDefaultWriteConcern(), $options) : array());
			$this->onAfterDelete();
			return $r;
		}
		return false;
	}

	/**
	 * This drops the collection associated with this active record. It will
	 * merely return true of false as to whether it succeed or not it will not do
	 * something clever like erase the document in PHP.
	 * @return boolean The database response
	 */
	function drop(){
		$response = $this->getCollection()->drop();
		return (bool)$response['ok'];
	}

	/**
	 * Returns boolean for whether the model is new or not
	 * @return boolean
	 */
	function isNew(){
		return $this->newRecord;
	}

	/**
	 * Sets whether or not the model is new
	 * @param unknown_type $new
	 */
	function setIsNew($new){
		$this->newRecord = $new;
	}

	/**
	 * Gets the scenario on the model
	 * @return string
	 */
	function getScenario(){
		return $this->scenario;
	}

	/**
	 * Sets the scenario on the model
	 * @param string $scenario
	 */
	function setScenario($scenario){
		$this->scenario = $scenario;
	}

	/**
	 * Just sets the attributes of the model whether or not they are in the schema, this function
	 * is designed to be used from trusted sources such as the database itself.
	 *
	 * DO NOT USE THIS FOR FORM DATA
	 *
	 * @param array $a
	 * @return Document
	 */
	function setAttributes($a){
		if(is_array($a)){
			foreach($a as $k => $v){
				$this->$k = $v;
			}
		}
		return $this; // Chain
	}

	/**
	 * This gets the attributes of our model, either db only or all attributes, default is for all
	 * @param boolean $dbOnly Should get only database attributes
	 */
	function getAttributes($dbOnly = false){

		$classAttrs = array();

		if(!$dbOnly){
			$cache = $this->db->getServer()->getVirtualObjCache(get_class($this));
			$virtualFields = $cache ? $cache : array();
			foreach($virtualFields as $k => $field) $classAttrs[$field] = $this->$field;
		}
		return array_merge($this->getDocument(), $classAttrs);
	}

	/**
	 * Same as __get really but dedicated, no magical stuff
	 * @param string $name
	 */
	function getAttribute($name){
		return $this->$name;
	}

	/**
	 * This function is used to set unsafe attributes according to the schema for validation etc.
	 * This function is designed to be used in conjunction with runValidation to validate unsanitised data to ensure it
	 * is safe.
	 * @param array $a the attributes
	 * @return boolean did it work?
	 */
	function _attributes($a, $schema = null){
		if($newDocument = $this->_setAttributeArray($a, $schema)){
			return $this->setAttributes($newDocument);
		}else{
			return false;
		}
	}

	/**
	 * This is designed to be an internal function which will set attributes for a specific array based upon
	 * a set number of rules and scenarios
	 *
	 * Using subdocuments through the automated manner is inheritently slow, please avoid it if you can. You
	 * can solve the speed problems by doing them yourself you lazy git.
	 *
	 * @param $a The new document
	 * @param $schema The rules
	 * @param $document The old document
	 */
	private function _setAttributeArray($a, $schema = null, $document = null /* Wait why do I put in the original document again? */){
		if(is_array($a)){

			// We must do this separately, even if it means using more resources
			// it means we can isolate what variables should be set outside of the
			// structural context of the rules, this is most advantageous for error messages
			$document = is_array($document) ? $document : $this->getAttributes();
			$schema = is_array($schema) ? $schema : $this->getRules(); // If we had a $schema incoming we set that

			$newDocument = array();

			foreach($schema as $k => $rule){

				// Now lets get the pieces of this rule
				$scope = isset($rule[0]) ? preg_split('/[\s]*[,][\s]*/', $rule[0]) : null;
				$validator = isset($rule[1]) ? $rule[1] : null;

				$params = $rule;
				unset($rule[0], $rule[1], $rule['message'], $rule['on'], $rule['label']);

				$scenario = isset($rule['on']) ? array_flip(preg_split('/[\s]*[,][\s]*/', $rule['on'])) : null;
				$message = isset($rule['message']) ? $rule['message'] : null;

				if(isset($scenario[$this->getScenario()]) || !$scenario){ // If the scenario key exists in the flipped $rule['on']
					foreach($scope as $k => $field){ // Foreach of the field lets check it out

						// If the field value is already set then lets not be forced to do this
						// shit again, honestly it is bad enough duplicating the code for setting
						// and validating, we don't need to make it worse
						if(isset($newDocument[$field]))
							continue;

						if(($validator == 'embedOne' || $validator == 'embedMany') && !empty($a[$field])){

							$schema = isset($params['rules']) ? $params['rules'] : array();

							if($validator == 'embedOne'){
								if($schema){
									$newDocument[$field] = $this->{__FUNCTION__}($a[$field], $schema, isset($document[$field]) ? $document[$field] : null);
								}else{

									// attempt to detect if this field is already an object it what we want
									if($document[$field] instanceof \mongoglue\Document){
										$newDocument[$field] = $document[$field];
									}elseif($this->db->getServer()->canLoadClass(isset($rule[2]) ? $rule[2] : '')){
										$o = $this->db->select($rule[2]);
										$o->setAttributes(isset($document[$field]) ? $document[$field] : null);
										$o->_attributes($a[$field]);
										$newDocument[$field] = $o;
									}else{
										trigger_error("You must specify either a schema or a class for subdocuments");
									}
								}
							}elseif($validator == 'embedMany'){

								// If no field is to be set here cos the incoming value is empty then lets set it null
								$newFieldValue = sizeof($a[$field]) > 0 ? array() : null;
								$aField = isset($a[$field]) ? $a[$field] : array();

								foreach($aField as $k => $v){

									if($schema){
										$newFieldValue[] = $this->{__FUNCTION__}($v, $schema, isset($document[$field], $document[$field][$k]) ? $document[$field][$k] : null);
									}else{

										// attempt to detect if this field is already an object it what we want
										if($v instanceof \mongoglue\Document){
											$newFieldValue[] = $v;
										}elseif($this->db->getServer()->canLoadClass(isset($rule[2]) ? $rule[2] : '')){
											$o = $this->db->select($rule[2]);
											$o->setAttributes(isset($document[$field], $document[$field][$k]) ? $document[$field][$k] : null);
											$o->_attributes($v);
											$newFieldValue[] = $o;
										}else{
											// Trigger a big fat error
											trigger_error("You must specify either a schema or a class for subdocuments");
										}
									}
								}

								// Finally set the new documents field value to the one we built
								$newDocument[$field] = $newFieldValue;
							}

						}elseif(isset($a[$field])){
							// if the field is actually an int then cast it to one otherwise all other field values are string for the min
							$newDocument[$field] = !is_array($a[$field]) && preg_match('/^[0-9]+$/', $a[$field]) > 0 ? (int)$a[$field] : $a[$field];
						}else{
							$newDocument[$field] = null; // Maybe best to not put the field in at all then the schema will shrink like true schemaless
						}
					}
				}
			}
			return $newDocument;
		}
		return false;
	}

	/**
	 * This gets and, if not called directly, sets our relationships for this model. If it is called directly
	 * it will just return the relation unto itself to be used immediately. This means you can get custom record sets
	 * of the relation without effecting your model
	 */
	function with($k, $where = array()){

		$relations = $this->relations();
		$cursor = array();

		if(array_key_exists($k, $relations)){

			$relation = $relations[$k];

			// Let's get the parts of the relation to understand it entirety of its context
			$cname = $relation[1];
			$fkey = $relation[2];
			$pk = isset($relation['on']) ? $this->{$relation['on']} : $this->{$this->primaryKey()};

			// Form the where clause
			$where = array();
			if(isset($relation['where'])) $where = array_merge($relation['where'], $where);

			// Find out what the pk is and what kind of condition I should apply to it
			if(is_array($pk)){

				// It is an array of _ids
				$clause = array_merge(array($fkey=>array('$in' => $pk)), $where);
			}elseif($pk instanceof MongoDBRef){

				// If it is a DBRef I can only get one doc so I should probably just return it here
				// otherwise I will continue on
				$row = $pk::get();
				if(isset($row['_id'])){
					$o = $this->db->select($cname);
					$o->setAttributes($row);
					return $o;
				}
				return null;

			}else{

				// It is just one _id
				$clause = array_merge(array($fkey=>$pk), $where);
			}

			if($relation[0]==='one'){

				// Lets find it and return it
				$cursor = $this->db->select($cname)->findOne($clause);
			}elseif($relation[0]==='many'){

				// Lets find them and return them
				$cursor = $this->db->select($cname)->find($clause);
			}
		}
		return $cursor;
	}

	/**
	 * This function runs the model validation and detects if it passes or not. It will
	 * return a boolean dependant upon the result of the validation and then it will have
	 * error messages accessible from AddErrorMessage, getErrors(), getFirstError()
	 *
	 * @param $fields The exclusive fields you want to validate, will not validate other attributes than these when
	 * used
	 */
	function validate($data = null, $rules = null){

		$valid = true;

		if(!$this->onBeforeValidate())
			return false;

		// We grab a static copy of the document now after beforeValidate()
		if(!$data)
			$document = $this->getDocument();
		else{
			$this->_attributes($data, $rules);
			$document = $this->getDocument();
		}
		$schema = is_array($rules) ? $rules : $this->getRules(); // If we had a $schema incoming we set that

		$errors = array();

		// Lets load our base validators
		if(!$this->baseValidator instanceof \mongoglue\validators\Base)
			$this->baseValidator = new \mongoglue\validators\Base;

		foreach($schema as $k => $rule){
			// Lets abstract this function off so we can call it easily for nested elements
			$ruleErrors = $this->validateRule($rule, $document);
			$errors = $this->db->getServer()->merge($errors, $ruleErrors);
		}

		$this->errors = $errors;

		if(sizeof($this->getErrors()) > 0){
			$valid = false; // NOT VALID
		}else{
			$valid = true; // VALID
		}

		$this->validated = true;
		$this->valid = $valid;

		$this->onAfterValidate($valid);
		return $valid; // Return whether valid or not
	}

	/**
	 * Validates a single rule to an inputted document
	 *
	 * @param $rule The rule in array form
	 * @param $document The document in array form
	 */
	function validateRule($rule, $document){

		// Now lets get the pieces of this rule
		$scope = isset($rule[0]) ? preg_split('/[\s]*[,][\s]*/', $rule[0]) : null;
		$validator = isset($rule[1]) ? $rule[1] : null;

		$scenario = isset($rule['on']) ? array_flip(preg_split('/[\s]*[,][\s]*/', $rule['on'])) : null;
		$message = isset($rule['message']) ? $rule['message'] : null;

		$params = $rule;
		unset($params[0], $params[1], $params['message'], $params['on'], $params['label']);

		$errors = array();
		$valid = true;

		if(isset($scenario[$this->getScenario()]) || !$scenario){ // If the scenario key exists in the flipped $rule['on']
			foreach($scope as $k => $field){ // Foreach of the field lets check it out

				$fieldErrors = array();

				if($validator == 'embedOne' || $validator == 'embedMany'){

					// Run the subdocument validator
					$schema = isset($params['rules']) ? $params['rules'] : array();

					if($validator == 'embedOne'){
						if($schema){
							$fieldErrors = $this->validateSubdocument($document[$field], $schema);
							if(sizeof($fieldErrors) > 0)
								$errors[$field] = $fieldErrors;
						}elseif($document[$field] instanceof \mongoglue\Document){
							$document[$field]->validate();
							if(sizeof($document[$field]->getErrors()) > 0)
								$errors[$field] = $document[$field]->getErrors();

							// This gives us only the values back for the field. I am unsure whether this is a good approach tbh
							$this->$field = $document[$field]->getRawDocument();
						}
					}elseif($validator == 'embedMany'){

						// If no field is to be set here cos the incoming value is empty then lets set it null
						foreach($document[$field] as $k => $v){
							$e = array();
							$f = array();

							if($schema){
								$e = $this->validateSubdocument($document[$field][$k], $schema);
								if(sizeof($e) > 0)
									$fieldErrors[$k] = $e;
							}elseif($document[$field][$k] instanceof \mongoglue\Document){
								$document[$field][$k]->validate();
								if(sizeof($document[$field][$k]->getErrors()) > 0)
									$fieldErrors[$k] = $document[$field][$k]->getErrors();

								$f[$k] = $document[$field][$k]->getRawDocument();
							}
						}

						// This gives us only the values back for the field. I am unsure whether this is a good approach tbh
						if(!empty($f))
							$this->$field = $f;

						if(sizeof($fieldErrors) > 0)
							$errors[$field] = $fieldErrors;
					}

					// If errors were encountered it is not valid
					if(isset($errors[$field]))
						$valid = false;

				}elseif(method_exists($this->baseValidator, $validator)){

					$this->baseValidator->model = $this;
					$valid = $this->baseValidator->validate($validator, $field, $document[$field], $params) && $valid;

				}elseif($this->db->getServer()->canLoadClass('\mongoglue\validators\\'.$validator)){

					$cname = '\mongoglue\validators\\'.$validator;
					$o = new $cname;
					$o->attributes($params);
					$o->owner = $this;
					$valid = $o->validate($field, $document[$field]) && $valid;
				}elseif($this->method_exists($validator)){
					$valid = $this->$validator($field, $document[$field], $params) && $valid;
				}else{
					trigger_error("The validator $validator could not be found in the ".get_class($this)." model");
				}
			}
		}

		$this->valid = $valid;

		// If there is only one field to this rule then we can actually apply it to that field
		if(!$valid && sizeof($scope) <= 1 && $message)
			$errors[$field][] = $message;
		elseif(!$valid && $message)
			$errors['global'][] = $message;

		return $errors;
	}

	/**
	 * Validates a subdocument within the model, not designed to be used outside of the model
	 * @param array $data
	 * @param array $rules
	 * @return NULL|array
	 */
	private function validateSubdocument($data = null, $rules = null){
		$errors = array();

		if(!is_array($rules))
			return null;
		foreach($rules as $k => $rule){
			// Lets abstract this function off so we can call it easily for nested elements
			$ruleErrors = $this->validateRule($rule, $data);
			$errors = $this->db->getServer()->merge($errors, $ruleErrors);
		}
		return $errors;
	}

	/**
	 * Returns whether or not this document has been validated
	 * @return boolean
	 */
	function isValidated(){
		return $this->validated;
	}

	/**
	 * Adds an error message to the model
	 * @param $message
	 * @param $field
	 */
	function addErrorMessage($message, $field = 'global' /* Global denotes where the error should apply to the form rather than a field */){
		$this->errors[$field][] = $message;
	}

	/**
	 * Gets all errors for this model or if $field is set
	 * gets only those fields errors
	 * @param string $field
	 */
	function getErrors($field = null){
		if($field){
			if(isset($this->errors[$field])){
				return $this->errors[$field];
			}
			return null;
		}else{
			return $this->errors;
		}
	}

	/**
	 * Gets the first global error if $field is not set or the first error for that field
	 * if it is set
	 * @param $field
	 */
	function getFirstError($field = null){
		$errors = $this->getErrors();

		if(!is_array($errors))
			return null;

		// If $field is not set it will take first global error
		if(!$field && isset($errors['global'])){
			return $errors['global'][0];
		}elseif(isset($errors[$field])){
			return $errors[$field][0];
		}
		return null;
	}

	/**
	 * Attaches a new validation rule to the set for this document
	 * to be run on validate()
	 * @param $rule
	 */
	function attachRule($rule){
		$this->rules[] = $rule;
	}

	/**
	 * This gets all validation rules for the document
	 */
	function getRules(){
		return array_merge($this->rules(), $this->rules);
	}

	/**
	 * This function is not currently implemented. Need more
	 * time to think of how this should function
	 */
	function removeRule(){
		// STUB
		trigger_error('removeRule in Document is not implemented');
	}

	/**
	 * Will get the raw collection object for use from the MongoDB Driver
	 * @return MongoCollection
	 */
	function getCollection(){
		return $this->db->selectCollection($this->collectionName());
	}

	/**
	 * Returns the MongoGlue Database class
	 * @return \mongoglue\Database
	 */
	function getDatabase(){
		return $this->db;
	}

	/**
	 * This just gets the document in normal PHP array format
	 * @return array
	 */
	function getDocument(){

		$classAttrs = array();

		$cache = $this->db->getServer()->getFieldObjCache(get_class($this));
		$documentFields = $cache ? $cache : array();
		foreach($documentFields as $k => $field) $classAttrs[$field] = $this->$field;

		return array_merge(is_array($this->document) ? $this->document : array(), $classAttrs);
	}

	/**
	 * Gets us the document without any sub classes
	 * @return multitype:
	 */
	function getRawDocument(){
		return $this->filterRawDocument($this->getDocument());
	}

	/**
	 * filters certain models attrbiutes out that might be stored within this doucment to give us a raw document.
	 * @param array $doc
	 * @return array
	 */
	function filterRawDocument($doc){
		if(is_array($doc)){
			foreach($doc as $k => $v){
				if(is_array($v)){
					$doc[$k] = $this->{__FUNCTION__}($doc[$k]);
				}elseif($v instanceof \mongoglue\document){
					$doc[$k] = $doc[$k]->getRawDocument();
				}
			}
		}
		return $doc;
	}

	/**
	 * Gets the document BSON encoded
	 * @return string
	 */
	function getBSONDocument(){
		return bson_encode($this->getRawDocument());
	}

	/**
	 * Gets the document in a JSON string
	 * @return string
	 */
	function getJSONDocument(){
		return json_encode($this->getRawDocument());
	}

	/**
	 * This asks and tells us if a field is virtual or not atm
	 * @return boolean
	 */
	function isVirtualField(){
		$cache = $this->db->getServer()->getObjCache();
		$virtualFields = isset($cache['virtual']) ? $cache['virtual'] : array();
		if(isset($virtualFields[$k])) return true;
		return false;
	}

	/**
	 * Raises an event for all other behaviours
	 * @param string $event
	 */
	function raiseEvent($event){
		foreach($this->behaviours as $behaviour => $obj){
			call_user_func_array(array($obj, $event), array()); // Lets call its
		}
	}

	/**
	 * These are the Model Events
	 */

	function onAfterConstruct(){
		$this->raiseEvent('afterConstruct');
		return $this->afterConstruct();
	}

	function onBeforeFind(){
		$this->raiseEvent('beforeFind');
		return $this->beforeFind();
	}

	function onAfterFind(){
		$this->raiseEvent('afterFind');
		return $this->afterFind();
	}

	function onBeforeValidate(){
		$this->raiseEvent('beforeValidate');
		return $this->beforeValidate();
	}

	function onAfterValidate(){
		$this->raiseEvent('afterValidate');
		return $this->afterValidate();
	}

	function onBeforeSave(){
		$this->raiseEvent('beforeSave');
		return $this->beforeSave();
	}

	function onAfterSave(){
		$this->raiseEvent('afterSave');
		return $this->afterSave();
	}

	function onBeforeDelete(){
		$this->raiseEvent('beforeDelete');
		return $this->beforeDelete();
	}

	function onAfterDelete(){
		$this->raiseEvent('afterDelete');
		return $this->afterDelete();
	}

	function afterConstruct(){
		return true;
	}

	function beforeFind(){
		return true;
	}

	function afterFind(){
		return true;
	}

	function beforeValidate(){
		return true;
	}

	function afterValidate(){
		return true;
	}

	function beforeSave(){
		return true;
	}

	function afterSave(){
		return true;
	}

	function beforeDelete(){
		return true;
	}

	function afterDelete(){
		return true;
	}

	/**
	 * Attaches a new behaviour or if already attached does nothing but at the same time
	 * will not return an error
	 *
	 * @param string $name
	 * @param array $options
	 */
	function attachBehaviour($name, $options = array()){
		if(!isset($this->behaviours[$name])){

			// The forms the namespace path we need to load the behaviour
			$namespace = "\\mongoglue\\behaviours\\".$name;

			// can we load this behaviour?
			if($this->db->getServer()->canLoadClass($namespace)){
				$behaviour = new $namespace;
				$behaviour->attributes($options);
				$behaviour->owner = $this;
				$this->behaviours[$name] = $behaviour;
			}else{
				trigger_error("The behaviour {$name} does not exist in the model ".get_class($this));
			}
		}
		return true;
	}

	/**
	 * Detaches a behaviour so it is no longer run within the active record.
	 * This will not revert already done operations by the behaviour only stop future ones.
	 * @param string $name
	 */
	function detachBehaviour($name){
		unset($this->behaviours[$name]);
	}
}