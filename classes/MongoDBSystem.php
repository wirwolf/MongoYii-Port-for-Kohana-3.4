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
}