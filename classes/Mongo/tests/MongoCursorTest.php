<?php

require_once 'bootstrap.php';

class MongoCursorTest extends CTestCase
{
	public function testFind()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = User::model()->find();

		$this->assertInstanceOf('\Mongo\Cursor', $c);
		$this->assertTrue($c->count() > 0);

		foreach($c as $doc){
			$this->assertTrue($doc instanceof \Mongo\Document);
			$this->assertEquals('update', $doc->getScenario());
			$this->assertFalse($doc->getIsNewRecord());
			$this->assertInstanceOf('\MongoId', $doc->_id);
			break;
		}
	}

	/**
	 * @covers \Mongo\Cursor::__construct
	 */
	public function testDirectInstantiation()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = new \Mongo\Cursor('User', ['username' => 'sammaye']);

		$this->assertInstanceOf('\Mongo\Cursor', $c);
		$this->assertTrue($c->count() > 0);
	}

	/**
	 * @covers \Mongo\Criteria
	 */
	public function test\Mongo\Criteria()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$criteria = new \Mongo\Criteria(['condition' => ['username' => 'sammaye'], 'limit' => 3, 'skip' => 1]);
		$c = new \Mongo\Cursor('User', $criteria);
		$this->assertInstanceOf('\Mongo\Cursor', $c);
		$this->assertTrue($c->count() > 0);
		// see also $this->testSkipLimit()
		$this->assertEquals(3, $c->count(true));

	}

	public function testSkipLimit()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = User::model()->find()->skip(1)->limit(3);

		$this->assertInstanceOf('\Mongo\Cursor', $c);
		$this->assertTrue($c->count(true) == 3);
	}

	public function tearDown()
	{
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
}