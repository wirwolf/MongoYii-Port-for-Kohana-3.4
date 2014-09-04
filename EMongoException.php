<?php

/**
 * EMongoException
 * The exception class that is used by this extension
 */
class MongoException extends \Exception
{
	public $errorInfo;
	
	public function __construct($message, $code = 0, $errorInfo = null)
	{
		$this->errorInfo = $errorInfo;
		parent::__construct($message, $code);
	}
}