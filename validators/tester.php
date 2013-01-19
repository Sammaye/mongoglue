<?php

namespace mongoglue\validators;

class tester extends \mongoglue\Validator{
	function validate($attribute, $value){
		// The regex basically says that if the name is less than 20 alpha numeric characters but 3+ then allow it
		// Of course you don't need a dedicated validator for this you can just use the regex validator but this is being used
		// for unit testing
		if(preg_match('/^[0-9a-zA-Z]{3,20}$/', $value) > 0){
			return true;
		}else{
			return false;
		}
	}
}