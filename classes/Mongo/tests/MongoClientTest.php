<?php

require_once 'bootstrap.php';

class MongoClientTest extends CTestCase
{
	/**
	 * @covers EMongoClient::getConnection
	 */
	public function testSettingUpConnection()
	{
		$mongo = Yii::app()->mongodb;
		$this->assertInstanceOf('EMongoClient', $mongo);

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertInstanceOf('Mongo', $mongo->getConnection());
		}else{
			$this->assertInstanceOf('MongoClient', $mongo->getConnection());
		}
	}

	/**
	 * @covers EMongoClient::selectCollection
	 */
	public function testSelectCollection()
	{
		$mongo = Yii::app()->mongodb;

		$this->assertTrue($mongo->new_collection instanceof MongoCollection);
		$this->assertInstanceOf('MongoCollection', $mongo->new_collection);
		$this->assertInstanceOf('MongoCollection', $mongo->selectCollection('new_collection'));
	}

	/**
	 * @covers EMongoClient::getDB
	 */
	public function testGetDB()
	{
		$mongo = Yii::app()->mongodb;
		$this->assertInstanceOf('MongoDB', $mongo->getDB());
	}

	/**
	 * @covers EMongoClient::getDefaultWriteConcern
	 */
	public function testWriteConcern()
	{
		$mongo = Yii::app()->mongodb;
		$w = $mongo->getDefaultWriteConcern();

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertTrue(isset($w['safe']));
		}else{
			$this->assertTrue(isset($w['w'], $w['j']));
		}

		$mongo->w = 1;
		$mongo->j = true;

		$w = null;
		$w = $mongo->getDefaultWriteConcern();

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertTrue($w['safe'] === true);
		}else{
			$this->assertTrue($w['w'] == 1 && $w['j'] === true);
		}
	}

	/**
	 * @covers EMongoClient::create\MongoIdFromTimestamp
	 */
	public function testCreate\MongoIdFromTimestamp()
	{
		$mongo = Yii::app()->mongodb;
		$id = $mongo->create\MongoIdFromTimestamp(time());
		$this->assertTrue($id instanceof \MongoId);
	}

	public function testArrayMerging()
	{
		$a = CMap::mergeArray(['a' => 1, 'b' => ['c' => 2]], ['a' => 1, 'b' => ['c' => 2, 'd' => 3]]);
		$this->assertTrue(isset($a['a'], $a['b'], $a['b']['c'], $a['b']['d']));
	}
}