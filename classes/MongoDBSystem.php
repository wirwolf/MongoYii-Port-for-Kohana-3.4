<?php
/**
 * Created by Wir_Wolf.
 * Author: Andru Cherny
 * E-mail: wir_wolf@bk.ru
 * Date: 26.12.14
 * Time: 19:52
 */

/**
 * Class MongoDB
 */
class MongoDBSystem {

	private $debug = false;
	/**
	 * @var MongoDBSystem
	 */
	private static $localObj;

	private $mongoConnect;

	/**
	 * @return MongoDBSystem
	 */
	public static function factory()
	{
		if(!self::$localObj)
		{
			self::$localObj = new self();
		}
		return self::$localObj;
	}


	public function connect()
	{
		$db = new \Mongo\Client();
		$db->server = 'mongodb://localhost:27017';
		$db->db = 'OGame';
		$db->enableProfiling = true;
		$db->connect();
		$username = 'ogame';
		$password = 'ogame';

		$salted = "${username}:mongo:${password}";
		$hash = md5($salted);

		$nonce = $db->command(["getnonce" => 1]);

		$saltedHash = md5($nonce["nonce"] . "${username}${hash}");

		$result = $db->command([
			"authenticate" => 1,
			"user"         => $username,
			"nonce"        => $nonce["nonce"],
			"key"          => $saltedHash
		]);
		$this->mongoConnect = $db;
	}

	/**
	 * @return MongoCollection
	 */
	public function getMongoComponent()
	{
		return $this->mongoConnect;
	}

	public function __construct()
	{
		if(class_exists('\\DebugBar', true))
		{
			\DebugBar::instance()->addCollector(new DebugBar\DataCollector\MessagesCollector('MongoDB'));
		}
	}
	/**********************Debug Functions***************************************************************************/
	public static function trace($text,$key)
	{
		\DebugBar::instance()->MongoDB->addMessage($text,$key);
	}


	public static function beginProfile($data, $key)
	{
		\DebugBar::instance()->time->startMeasure($data, $key);
	}

	public static function endProfile($data, $key)
	{
		\DebugBar::instance()->time->stopMeasure($data, $key);
	}


	/**
	 * @return boolean
	 */
	public function isDebug()
	{
		return $this->debug;
	}

	/**
	 * @param boolean $debug
	 */
	public function setDebug($debug)
	{
		$this->debug = $debug;
	}
}