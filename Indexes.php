<?php
/**
 * Index plan for MongoDB
 *
 * THIS FILE IS NOT REQUIRED
 *
 * This file is an optional helper for those that have a specific scenario where the indexes can be managed easily from the application
 * for all dbs and collections.
 *
 * This file contains all of the index plans for your database(s). Basically when you query a collection
 * it will search this array and understand if the indexes should exist on this collection.
 *
 * It will then run the collection (denoted by the indexes) within this array going through its indexes one by one
 * setting them. If an index is already set it should register as a no-op and should be almost instantaneous
 * at that.
 *
 * Once this index plan has been run once it will not be run again until the app starts up again. This will only run once
 * per connection, if that's easier to understand.
 */
return array(
	'sessions' => array(
		array(array('session_id' => 1), array("unique" => true))
	),
	'user' => array(
		array(array('_id' => 1, 'name' => 1)),
	)
);
