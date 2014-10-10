<?php
/**
 * Created by Wir_Wolf.
 * Author: Andru Cherny
 * E-mail: wir_wolf@bk.ru
 * Date: 04.09.14
 * Time: 23:23
 */
//TODO Move this in config
$db = new \Mongo\Client();
$db->server = 'mongodb://localhost:27017';
$db->db = 'test';
$db->connect();
$username = 'test';
$password = 'test';

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
class MongoInRegistry extends Registry
{
	public static function setProperty($name, $value)
	{
		parent::setProperty($name, $value);
	}
}
MongoInRegistry::setProperty('MongoDB',$db);