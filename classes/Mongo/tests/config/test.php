<?php
return CMap::mergeArray(
	require ('../../../config/main.php'), 
	[
		'components' => [
			'mongodb' => [
				'class' => 'EMongoClient',
				'server' => 'mongodb://localhost:27017',
				'db' => 'super_test'
			],
			'authManager' => [
				'class' => 'EMongoAuthManager'
			]
		]
	]
);
