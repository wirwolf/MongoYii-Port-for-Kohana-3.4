<?php
return CMap::mergeArray(
	require ('../../../config/main.php'), 
	[
		'components' => [
			'mongodb' => array (
				'class' => 'EMongoClient',
				'server' => 'mongodb://localhost:27017',
				'db' => 'super_test' 
			),
			'authManager' => [
				'class' => 'EMongoAuthManager' 
			]
		]
	]
);
