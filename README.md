# Mongoglue ORM

This is a very simple ORM designed for MongoDB.

It is very much designed a kind of "My First" of Active Record handling. Irrespective of that fact this ORM has been extensively tested in a live environment and has been
found to be quite fitting to security needs in all tested cases.

A lot of the documentation and examples can be found within the `tests` folder where PHPUnit tests are performed on each section of the ORM. The file `document.php`
and the models in `tests/documents` would be of particular interest to new users.

## The Why and How of Fry?

I built this as a personal project to understand how other frameworks do Active Record. This originally started out as a project to work along side Yiis own
Active Record (and does take quite a lot of inspiration from there) to help me to understand how to debug and fix any Yii errors I get.

When I decided to incorporate this as a separate module from that previous project the intention I had in mind was to create an Active Record model slimmer and more transparent
to the driver than Doctrine 2. I suppose you could say I designed this to sit in the middle between the driver and Doctrine 2.

## The Core

It is important to note that not all the files you see in this repository are not actually needed.

Most of the files contained in here are actually helpers or add-ons for the main core of the ORM. The only files you really need in order to use the core of this ORM are:

	/mongoglue/Server.php
	/mongoglue/Database.php
	/mongoglue/Cursor.php
	/mongoglue/Document.php
	/mongoglue/validators/Base.php

If you intend to use behaviours and/or validators it might be good to keep:

	/mongoglue/Validator.php
	/mongoglue/Behaviour.php

Since these files act as parent classes that your own behaviours etc can inherit and if you end up downloading a behaviour and/or validator from other individuals they might require
this class.

Everything else on top is either helpers or just there to make your life a little easier. I will describe most of them here for you.

### behaviours/Timestamp.php

A default behaviour you can use to add timestamps to your records. Also shows how behaviours are actually made up and designed.

Note: This is also used by the PHPUnit tests.

### validators/tester.php

A default username tester that uses Regex to test for a alpha numeric username that is 3 - 20 characters in length.

A good example of how a validator is built.

Note: This is also used by the PHPUnit tests.

### tests

The tests folder contains a set of tests based around PHPUnit.

This folder is invaluable to understanding how to use mongoglue and I would recommend looking through the tests performed in this folder.

### helpers

Exactly what it says on the tin. This folder has its own readme to tell you what each file does.

## Using it

As I said, I have designed this to quite transparent to the driver itself so lets get an example out:

	require 'mongoglue/Server.php';

	$mongo = new mongoglue\Server(new Mongo()/new MongoClient(), array(
		'documentDir' => dirname(__FILE__).'/mongoglue/tests/documents',
		'documentns' => '\\mongoglue\\tests\\documents'
	));
	$db = $mongo->mydb;
	$test = $db->select('test');

Note: Please do not copy this block of code. There is an error in it, intentionally.

You will see that the first file I include is the `Server` class within the `mongoglue` root. Once this file is there I cna make a new instance of it passing a connection object
(`new Mongo()` and `new MongoClient()` both shown here) with some parameters. I should note that even though the `documentDir` is needed the `documentns` is not unless you have
namespaced you models, then it is.

The `documentDir` should go from the root `/` or `C:\` (or whatever drive letter you are on) depending on the OS you are on. As an example instead of passing `app/documents` as my
`documentDir` I would actually pass `/srv/workspace/mydomain.co.uk/htdocs/documents`.

Once you have your server class you can then use it like you would the native MongoDB driver. Accessing a `__get` on the `Server` class will get a database and accessing a `__get`
on the returned `Database` class will get a RAW MongoDB collection (straight from the driver, no active record). In order to use Active Record you can either, as I show above,
use the `select($myModelName)` function within the `Database` class or you cna use the `__call` to get the model, i.e. `$testCursor = $db->User()->find()`.

When you use the `select` or `__call` abilities wthin the `Database` class both functions will return an instance of `\mongoglue\Document` which represents the document itself.

### Using the Document

## Documentation notes

As I said earlier. A lot of the documentation and examples can be found in various files within the `tests` folder. The `tests` folder is designed to provide a set of standard
tests with full examples of using 99% of the ORMs functionality. The file `document.php` and the models in `tests/documents` would be of particular interest to new users.
