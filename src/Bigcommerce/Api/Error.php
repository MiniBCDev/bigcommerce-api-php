<?php

namespace Bigcommerce\Api;

/**
 * Base class for API exceptions. Used if failOnError is true.
 */
class Error extends \Exception
{

	public function __construct($message, $code)
	{
		if (is_array($message)) {
			$message = $message[0]->message;
		}

		if (is_object($message)) {
			if (property_exists($message, 'title')) {
				$message = $message->title;
			}
			
			if (property_exists($message, 'errors')) {
				$errors = (array)$message->errors;
				$error = array_shift($errors);

				$message = $error;
			}
		}
		
		parent::__construct($message, $code);
	}

}
