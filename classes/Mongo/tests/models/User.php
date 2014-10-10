<?php

class User extends \Mongo\Document
{
	/** @virtual */
	public $avatar;
	
	public $username;
	
	public $addresses = [];
	
	public $url = null;
	
	public $interests = [];
	
	public $mainSkill;

	public $otherSkills;

	public function scopes()
	{
		return [
			'programmers' => [
				'condition' => ['job_title' => 'programmer'],
				'sort' => ['name' => 1],
				'skip' => 1,
				'limit' => 3
			]
		];
	}

	public function behaviors()
	{
		return [
			'EMongoTimestampBehaviour'
		];
	}

	public function rules()
	{
		return [
			['username', 'EMongoUniqueValidator', 'className' => 'User', 'attributeName' => 'username', 'on' => 'testUnqiue'],
			['addresses', 'subdocument', 'type' => 'many', 'rules' => [
				['road, town, county, post_code', 'safe'],
				['telephone', 'numerical', 'integerOnly' => true]
			]],
			['mainSkill, otherSkills', 'safe'],
			['url', 'subdocument', 'type' => 'one', 'class' => 'SocialUrl'],
			['_id, username, addresses', 'safe', 'on'=>'search'],
		];
	}

	public function collectionName()
	{
		return 'users';
	}

	public function relations()
	{
		return [
			'many_interests' => ['many', 'Interest', 'i_id'],
			'one_interest' => ['one', 'Interest', 'i_id'],
			'embedInterest' => ['many', 'Interest', '_id', 'on' => 'interests'],
			'where_interest' => ['many', 'Interest', 'i_id', 'where' => ['name' => 'jogging'], 'cache' => false],
			'primarySkill' => ['one', 'Skill', '_id', 'on' => 'mainSkill'],
			'secondarySkills' => ['many', 'Skill', '_id', 'on' => 'otherSkills'],
		];
	}

	public function attributeLabels()
	{
		return [
			'username' => 'name'
		];
	}

	/**
	 * Returns the static model of the specified AR class.
	 * @return User the static model class
	 */
	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}
}

class SocialUrl extends EMongoModel
{
	public function rules()
	{
		return [
			['url, caption', 'numerical', 'integerOnly' => true],
		];
	}
}