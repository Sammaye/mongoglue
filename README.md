# Mongoglue ORM

This is a very simple ORM designed for MongoDB.

It is very much designed as a kind of (http://www.fisher-price.com/en_US/products/55197)[Fisher-Price] "My First Active Record". Irrespective of that fact this ORM has 
been extensively tested in a live environment (as part of another project) and has been found to be quite fitting to most security needs.

A lot of the documentation and examples can be found within the `tests` folder where PHPUnit tests are performed on each section of the ORM. The file `document.php`
and the models in `tests/documents` would be of particular interest to new users.

## The Why and How of Fry?

I built this as a personal project to understand how other frameworks do Active Record. This ORM in fact comes from a MVC framework I built myself to learn how frameworks such as Yii, 
Lithium and CakePHP (etc) actually work.

When I decided to incorporate this as a separate module from that previous project the intention I had in mind was to create an Active Record model slimmer and more transparent
to the driver than Doctrine 2. I suppose you could say I designed this to sit in the middle area between the driver and Doctrine 2 in terms of usage.

## The Core

It is important to note that not all the files you see in this repository are actually needed.

Most of the files contained in here are actually helpers or add-ons for the main core of the ORM. 

The core only really consists of:

	/mongoglue/Server.php
	/mongoglue/Database.php
	/mongoglue/Cursor.php
	/mongoglue/Document.php
	/mongoglue/validators/Base.php

If you intend to use behaviours and/or validators it might be good to keep:

	/mongoglue/Validator.php
	/mongoglue/Behaviour.php
	
And their respective folders as `behaviours` and `validators`. The files listed above act as parent classes that your own behaviours etc can inherit and if you end up 
downloading a behaviour and/or validator from other individuals they might require these classes.

Everything else on top is either helpers or just there to make your life a little easier.

## Using it

As I said, I have designed this to be quite transparent to the driver itself so lets get an example out:

	require 'mongoglue/Server.php';

	$mongo = new mongoglue\Server(new MongoClient(), array(
		'documentDir' => dirname(__FILE__).'/mongoglue/tests/documents',
		'documentns' => '\\mongoglue\\tests\\documents'
	));
	$db = $mongo->mydb;
	$test = $db->select('test');

You will see that the first file I include is the `Server` class within the `mongoglue` root. Once this file is there I can make a new instance of it passing a connection object
(`new Mongo()` and `new MongoClient()` both shown here) with some parameters. I should note that even though the `documentDir` is needed the `documentns` is not unless you have
namespaced you models, then it is.

The `documentDir` should go from the root `/` or `C:\` (or whatever drive letter you are on) depending on the OS you are on. As an example, instead of passing `app/documents` as my
`documentDir` I would actually pass `/srv/workspace/mydomain.co.uk/htdocs/documents`.

Once you have your server class you can then use it like you would the native MongoDB driver. Accessing a `__get` on the `Server` class will get a database and accessing a `__get`
on the returned `Database` class will get a RAW MongoDB collection (straight from the driver, no active record). In order to use Active Record you can either, as I show above,
use the `select($myModelName)` function within the `Database` class or you can use the `__call` to get the model, i.e. `$testCursor = $db->User()->find()`.

When you use the `select` or `__call` abilities within the `Database` class both functions will return an instance of `\mongoglue\Document` which represents the document itself.

## The Document

A simple, bog basic document looks like this:

	namespace mongoglue\tests\documents;
	
	class test extends \mongoglue\Document{
	
		function collectionName(){
			return 'test';
		}
	
		public static function model($mongo, $dbname = null, $class = __CLASS__){
			return parent::model($mongo, $dbname, $class);
		}
	}
	
All documents must extend `\mongoglue\Document` and implement the `model` function.
	
Here I provide a collection name and a model function. The model function, like in Yii, allows you to access the model from almost anywhere almost immediately by only supplying a 
`\mongoglue\Database` object or alternatively a `\mongoglue\Server` object.

Note: if you use the server object you must also supply a database name in the `$dbname` parameter.

There are numerous little pieces of information and detail you can add to your model to make it do exactly what you and this is what we will be covering next.

### Fields and Virtual Attributes

There is no requirement to define a schema within the model.

By default every variable within the model is declared a database attribute however there are ways to define virtual attributes:

	class test extends \mongoglue\Document{
	
		/** @virtual */
		public $lastRunEvent;
		
	}
	
Using the `@virtual` annotation in PHP Doc blocks you can actually assign virtual attributes to your model that will not be saved but can be treated like any other document variable, 
i.e. they can be validated.

You can define defaults for any of your schema fields by simply adding them to your class and, in PHP, just assign a default within the class definition:

	class test extends \mongoglue\Document{
	
		public $lastRunEvent = 'None';
		
	}

Note: Unless you are knowledgable above this stuff it is best to stick to making all variables of the `public` scope.

### Events

The document class supports a number of events that can be used by not only you but alos other addons such as behaviours:

If you return false from a `beforeX()` function, such as `beforeValidate()`, it will actually halt current processing stop further action within the model.

Doing the same within a `afterX()` function will not have the same effect and further processing will continue regardless. 

- `afterConstruct()` is quite self explanatory really, it runs after the class constructor but before setting attributes in the model
- `beforeFind()` run as the first thing before finding a document
- `afterFind()` runs as the last thing after finding a document
- `beforeValidate()` runs prior to validaton of the model
- `afterValidate()` runs after validation of the model
- `beforesave()` runs before the save of a document, whether it be insert or update (take heed of that last sentence when adding functionality there)
- `aftersave()` run after the save of a document, again, whether it be an insert or an update
- `beforeDelete()` runs before the deletion of the document
- `afterDelete()` runs after deletion of the document

### Scenarios

Scenarios enable different actions at different times. The scenarios can apply in both manual coding and in the validation of a model.

By default a new model will have a scenairo of `insert` and a saved model will have a scenario of `update`.

Getters and setters are provided for the scenarios as part of the models public API:

	function beforesave(){
		$scenario = $this->getScenario();
		$this->setScenario($scenario);
	}

### Relations

Relations are vey useful if you intend to have a relational model of some kind.

You can define a set of relations via the `relations` function within the model:

	function relations(){
		return array(
			'testDetail' => array('one', 'testDetail', 'test_id'),
			'testDetails' => array('many', 'testDetail', 'test_id'),
			'embeddedDetails' => array('many', 'testDetail', '_id', 'on' => 'test_ids'),
			'conditionalDetails' => array('many', 'testDetail', 'test_id', 'where' => array(
				'name' => 'Programming'
			))
		);
	}
	
As seen from the examples above you can set a variety of different options on a relation however the relation can only consist of:

- A type as the first array position, `one` or `many`
- A model as the second array position, i.e. `User`
- A foreign key in the third position. By default mongoglue will attempt to connect the `_id` of the current document to the field you specify in the child document
- A `on` clause, incase the `_id` is not the foreign key
- A `where` clause to limit a relation

It should be noted that the relational behaviour can support either a single `ObjectId` or a `DBRef` or an array of `OjbectId`s as the key for what information use from the parent 
model to gather the children. As example, from the above code, `test_ids` is in fact an array of `ObjectId`s that denote all the `testDetail`s that are connected to this model.

The realtions of the model can be accessed as either variables of the class (i.e. `$model->testDetail`) or using the `with()` function. The `with()` function provides the ability for you 
to add a relation and then later down the line specify the `where` parameter of the relation depending upon a dynamic set of variables within your application, a good example being:

	$model->with('testdetail', array('name' => $nameOfInterest));
	
Using `with` this way will not overwrite the cached relation at the variable position in the class, instead it will make a whole new query to the database to retrieve this information 
specially for this case.
	
Note: If you provide a `where` clause within the `with` function it will in fact merge with the `where` clause already existing within the delcared relation in the model. 

Note: Automatic Cascading is not supported by default within the ORM

Note: MongoDB has no JOINs or relational integrity what-so-ever so you will need to take into account cascading etc on the application end.

### Behaviours

Behaviours are really useful if you want to add a common set of functions to many models. A good example of this is actually provided, as base, within this repository.

The `Timestamp.php` file in the `behaviours` folder shows a pefect example of how common functionality can exist between many models. As you can see it hooks into `beforeSave` and 
implements a couple of helper functions.

For an idea of what events the behaviour can implement look to the parent class in `\mongoglue\Behaviour`.

A model can transpose the functions within the behaviour onto itself allowing you, in this case, to call something like:

	$model->ago($model->created);
	
To get a user fiendly caption for how long ago the record was created.

Behavours within the model sit within a function called `behaviours()` which returns an array of behaviours. As an example:

	function behaviours(){
		return array('Timestamp');
	}
	
A behaviour can also be passed certain information by the model to tell it how it should run. This is done within the behaviour declaration within the models `behaviours` function 
like so:

	function behaviours(){
		return array('Timestamp' => array('dateFormat' => 0));
	}
	
The keys within the nested array whose key is the behaviour name represent class properties.

Note: behaviours have no requirement to extend from `\mongoglue\Behaviour` provided you have the functions in your own file as well. 

Note: A behaviours event hooks into the model will be run before your own, so tghe `Timestamp`s `beforesave()` hook will run before your own in model one.

### Setting Unsanitised Attributes

If you wish to set the attributes of the model ready for validation you can use the `_attributes()` function which will use the defined rules you either entered into the `validate()` 
function or into the `rules()` model method to judge what fields should be set within the model and which should not. As an example:

	$model->_attributes($_POST['user']);

Fields sent into this function that are not defined within the rules of the model (either through the `validate` or `rules` function) will be silently dropped. There will be no 
notification that they have been dropped.

Note: Setting the attributes and validating them are two completely different things.  

### Saving

A document will and should always call the `save()` function, whether it be new or not. The `save()` function will automatically detect if the record should be inserted or updated 
and will peform the needed action. An example of using `save` is:

	$model->save();
	
Note: By default validation is NOT set to run on everytime you call save. If you wish to run the models validation when you save you must pass `true` in as an additional parameter 
into the function signature like so:

	$model->save(true);	
	
### removing a Document

The model supports a `remove()` function which by default will deleted based on the `_id` of the document:

	$model->remove();

Note: Currently the `primaryKey` function has no effect on how the remove function removes a document.

### Checking if it is a new record

There are two ways to check if the model is a new record in mongoglue, both inside the model and outside:

- You can use the insert scenario using `getScenario()` to understand if it is that current scenario
- The model actually provides a dedicated `isNew()` function which you can evaluate to either true or false directly.

### Cleaning and Reloading

Once every so often you might need to either clean and document or reload it completely from database because of some special case whereby active record did not work so well.

The model has two functions: `clean()` and `refresh()`.

The `clean` does exactly what it says on the tin however the `refresh` will run a `clean` (will actually call the `clean` function) and then replace the documents attributes.

## Finding Documents

The document class acts as way to both find and save a record using active record. There are 3 methods for finding a record on the document class:

    $db->select('test')->find()

Is analogous to the `find()` command in the MongoDB driver and will return a `\mongoglue\Cursor` of results.

	$db->select('test')->findOne()

Is analogous to the `findOne()` command in the MongoDB driver and will return a `\mongoglue\Document` of the found document.

	$db->select('test')->findById();

This function is a special helper for `findOne`. It will take either a `MongoId` or the hexadecimal string representation of an `ObjectId` (`MongoId`) and will return the found
document.

### Searching

You can use a function within each model called `search()` to search for all documents in a full text manner using regexes.

The function has a signature of:

	search(an_array_of_fields_to_search, a_term, an_extra_query_piece);
	
And can be exampled by:

	$model->search(array('title', 'description'), 'sammaye', array('user_id' => new MongoId()));
	
Note: The search is very primative. It does not detect ranking nor relavance, merely just finds documents with those terms in the specified fields.

Note: With MongoDB 2.4 this function will become obsolete due to the new full text search abilities, use this if you are on an older version of MongoDB.

Note: Please refer to the documentation page on (http://docs.mongodb.org/manual/reference/operators/#_S_regex)[$regex] where by it states: 

> $regex can only use an index efficiently when the regular expression has an anchor for the beginning (i.e. ^) of a string and is a case-sensitive match. 
> Additionally, while /^a/, /^a.*/, and /^a.*$/ match equivalent strings, they have different performance characteristics. All of these expressions use an index if 
> an appropriate index exists; however, /^a.*/, and /^a.*$/ are slower. /^a/ can stop scanning after matching the prefix.

This function uses index unfriendly regexes to perform its search. Please ensure you have something else which limits the query first i.e.:

    $model->search(array('title', 'description'), 'sammaye', array('user_id' => new MongoId()));
    
Whereby I use the `user_id` to actually limit the query.

## Aggregation Framework

Mongoglue does not support the aggregation framework as such (aggregation and active record never goes well together) but it does have a helper with which to do aggregation on the model:

	$model->aggregate(array(//whatever))
	
It is basically a helper that ppoints directly to the drivers own `aggregate` function so it works exactly the same.

## Write Concern

The default write concern for mongoglue is `1` ( http://php.net/manual/en/mongo.writeconcerns.php ) with journal ack off. You can change these defaults using:

	$mongo->writeConcern = 'majority';
	$mongo->journaled = true;

You can also set the write concern per query, taking the previous example:

	$db->select('user')->setAttributes(array('name' => 'sammaye'))->save(array('w' => 'majority', 'j' => true));

## Documentation notes

As I said earlier. A lot of the documentation and examples can be found in various files within the `tests` folder. The `tests` folder is designed to provide a set of standard
tests with full examples of using 99% of the ORMs functionality. The file `document.php` and the models in `tests/documents` would be of particular interest to new users.
