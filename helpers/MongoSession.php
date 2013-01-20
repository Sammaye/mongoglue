<?php

namespace mongoglue\helpers;

/**
 * The Mongo Session Store
 *
 *
 * This is the session store Mongo plugin. This allows us to have
 * database sessions within PHP. If you do not wish for database
 * sessions then within the configuration ini just return false
 * on $session_store.
 *
 * @example new MongoSession(); start_session();
 *
 */
class MongoSession{

	/**
	 * The collection that sessions will be stored in
	 * @var string
	 */
	public $collection = 'sessions';
	
	/**
	 * The write concern, 1 means acked write
	 * @var int|string
	 */
	public $w = 1;
	
	/**
	 * The journal part of write concern, normally never need this to be true
	 * @var boolean
	 */
	public $journaled = false;
	
	/**
	 * Houses the database object that we are currently using
	 * @var \mongoglue\Database
	 */
	public $db;	

	/**
	 * This decides the lifetime (in seconds) of the session
	 *
	 * @access private
	 * @var int
	 */
	private $life_time;

	/**
	 * This stores the found session collection so that we don't
	 * waste resources by constantly going back for it
	 *
	 * @access private
	 * @var sessions
	 */
	private $_sessions = array();

	/**
	 * Constructor
	 */
	function __construct($connection, $dbname = null) {

		// No active record for this bad boy
		if($connection instanceof \mongoglue\Database || $connection instanceof \MongoDB){
			$this->db = $db;
		}elseif(($connection instanceof \mongoglue\Server || $connection instanceof Mongo || $connection instanceof MongoClient) && $dbname){
			$this->db = $connection->$dbname;
		}else{
			// Throw error that this class isn't being used right
			trigger_error("Please call the MongoSession class with either a mongoglue/mongo database or server");
		}

		// Ensure index on Session ID, this is handled by the Indexes file if your using that
		// $this->db->sessions->ensureIndex(array('session_id' => 1), array("unique" => true));

		// Register this object as the session handler
		session_set_save_handler(
			array( $this, "open" ),
			array( $this, "close" ),
			array( $this, "read" ),
			array( $this, "write"),
			array( $this, "destroy"),
			array( $this, "gc" )
		);
	}

	/**
	 * Open session
	 *
	 * This function opens a session from a save path.
	 * The save path can be changed the method of opening also can
	 * but we do not change that we just do the basics and return
	 *
	 * @param string $save_path
	 * @param string $session_name
	 */
	function open( $save_path, $session_name ) {

		global $sess_save_path;

		$sess_save_path = $save_path;

		// Don't need to do anything. Just return TRUE.
		return true;

	}

	/**
	 * This function closes the session (end of session)
	 */
	function close() {

		// Return true to indicate session closed
		return true;

	}

	/**
	 * This is the read function that is called when we open a session.
	 * This function attempts to find a session from the Db. If it cannot then
	 * the session class variable will remain null.
	 *
	 * @param string $id
	 */
	function read( $id ) {

		// Set empty result
		$data = '';

		// Fetch session data from the selected database
		$time = time();

		$this->_sessions = $this->getCollection()->findOne(array("session_id"=>$id));

		if (!empty($this->_sessions)) {
			$data = $this->_sessions['session_data'];
		}

		return $data;

	}

	/**
	 * This is the write function. It is called when the session closes and
	 * writes all new data to the Db. It will do two actions depending on whether or not
	 * a session already exists. If the session does exist it will just update the session
	 * otherwise it will insert a new session.
	 *
	 * @param string $id
	 * @param mixed $data
	 *
	 * @todo Need to make this function aware of other users since php sessions are not always unique maybe delete all old sessions.
	 */
	function write( $id, $data ) {

		//Write details to session table
		$time = strtotime('+2 weeks');

		// If the user is logged in record their uid
		$uid = $_SESSION['logged'] ? $_SESSION['uid'] : 0;

		$fields = array(
			"session_id"=>$id,
			"user_id"=>$uid,
			"session_data"=>$data,
			"expires"=>$time,
			"active"=>1
		);

		$fg = $this->getCollection()->update(array("session_id"=>$id), array('$set'=>$fields), 
				array_merge($this->getDefaultWriteConcern(), array("upsert"=>true)));

		// DONE
		return true;
	}

	/**
	 * This function is called when a user calls session_destroy(). It
	 * kills the session and removes it.
	 *
	 * @param string $id
	 */
	function destroy( $id ) {

		// Remove from Db
		$this->getCollection()->remove(array("session_id" => $id), $this->getDefaultWriteConcern());

		return true;
	}

	/**
	 * This function GCs (Garbage Collection) all old and out of date sessions
	 * which still exist in the Db. It will remove by comparing the current to the time of
	 * expiring on the session record.
	 *
	 * @todo Make a cronjob to delete all sessions after about a day old and are still inactive
	 */
	function gc() {
		$this->getCollection()->remove(array('expires' => array('$lt' => strtotime('+2 weeks'))), $this->getDefaultWriteConcern());
		return true;
	}
	
	/**
	 * Gets the RAW collection for us to work on
	 */
	function getCollection(){
		return $this->db->{$this->collection};
	}
	
	/**
	 * Gets the default write concern options for all queries through active record
	 * @return array
	 */
	function getDefaultWriteConcern(){
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			if((bool)$this->w){
				return array('safe' => true);
			}
		}else{
			return array('w' => $this->w, 'j' => $this->journaled);
		}
		return array();
	}
}
