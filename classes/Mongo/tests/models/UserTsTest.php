<?php

/**
* Testing behaviors/EMongoTimestampBehaviour
*/
class UserTsTest extends \Mongo\Document
{
	public $username;

	public function behaviors()
	{
		return [
			'EMongoTimestampBehaviour' => [
				'class' => 'EMongoTimestampBehaviour',
				'onScenario' => ['testMe'],
			]
		];
	}

	public function collectionName()
	{
		return 'users';
	}
}

/**
* Testing behaviors/EMongoTimestampBehaviour whereas here its broken
*/
class UserTsTestBroken extends \Mongo\Document
{
	public $username;

	public function behaviors()
	{
		return [
			'EMongoTimestampBehaviour' => [
				'class' => 'EMongoTimestampBehaviour',
				'onScenario' => 'testMeFalse',
			]
		];
	}

	public function collectionName()
	{
		return 'users';
	}
}

/**
* Testing behaviors/EMongoTimestampBehaviour whereas here its broken.
* This time onScenario and notOnScenario are defined
*/
class UserTsTestBroken2 extends \Mongo\Document
{
	public $username;

	public function behaviors()
	{
		return [
			'EMongoTimestampBehaviour' => [
				'class' => 'EMongoTimestampBehaviour',
				'onScenario' => ['testMeFalseOn'],
				'notOnScenario' => ['testMeFalseOn'],
			]
		];
	}

	public function collectionName()
	{
		return 'users';
	}
}