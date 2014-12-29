<?php
namespace Mongo;
/**
 * \Mongo\Document
 *
 * The active record itself
 */
class Document extends \Mongo\Model
{
	/**
	 * Holds a set of cached models for the active record to instantiate from
	 *
	 * Whenever you call ::model() it will either find the class in this cache array and use it or will
	 * make a whole new class and cache it into this array
	 *
	 * @var array
	 */
	private static $_models = [];

	/**
	 * Whether or not the document is new
	 * @var boolean
	*/
	private $_new = false;

	/**
	 * Holds criteria information for scopes
	 * @var array|null
	 */
	private $_criteria;

	/**
	 * Contains a list of fields that were projected, will only be taken into consideration
	 * should _partial be true
	 * @var array|string[]
	 */
	private $_projected_fields = [];

	/**
	 * A bit deceptive, this var actually holds the last response from the server. The reason why it is called this
	 * is because this is what MongoDB calls it.
	 * @var array
	*/
	private $lastError;

	/**
	 * Sets up our model and set the field cache just like in \Mongo\Model
	 *
	 * It will also set the default scope on the model so be aware that if you want the default scope to not be applied you will
	 * need to run resetScope() straight after making this model
	 *
	 * @param string $scenario
	 */
	public function __construct($scenario = 'insert')
	{
		$this->getDbConnection()->setDocumentCache($this);

		if($scenario === null){ // internally used by populateRecord() and model()
			return;
		}

		$this->setScenario($scenario);
		$this->setIsNewRecord(true);

		$this->init();

		//$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	/**
	 * This, in addition to \Mongo\Models edition, will also call scopes on the model
	 * @see \Mongo\Model::__call()
	 * @param string $name
	 * @param array $parameters
	 * @return \Mongo\Document|mixed
	 */
	public function __call($name, $parameters){
		if(array_key_exists($name, $this->relations())){
			if(empty($parameters)){
				return $this->getRelated($name, false);
			}
			return $this->getRelated($name, false, $parameters[0]);
		}

		$scopes = $this->scopes();
		if(isset($scopes[$name])){
			$this->setDbCriteria($this->mergeCriteria($this->getDbCriteria(), $scopes[$name]));
			return $this;
		}
		return parent::__call($name, $parameters);
	}

	/**
	 * The scope attached to this model
	 *
	 * It is very much like how Yii normally uses scopes except the params are slightly different.
	 *
	 * @example
	 *
	 * array(
	 * 	'ten_recently_published' => array(
	 * 		'condition' => array('published' => 1),
	 * 		'sort' => array('date_published' => -1),
	 * 		'skip' => 5,
	 * 		'limit' => 10
	 * 	)
	 * )
	 *
	 * Not all params need to be defined they are all just there above to give an indea of how to use this
	 *
	 * @return array
	 */
	public function scopes()
	{
		return [];
	}

	/**
	 * Sets the default scope
	 *
	 * @example
	 *
	 * array(
	 * 	'condition' => array('published' => 1),
	 * 	'sort' => array('date_published' => -1),
	 * 	'skip' => 5,
	 * 	'limit' => 10
	 * )
	 *
	 * @return array - An array which represents a single scope within the scope() function
	 */
	public function defaultScope()
	{
		return [];
	}

	/**
	 * Resets the scopes applied to the model clearing the _criteria variable
	 * @param bool $resetDefault
	 * @return \Mongo\Document
	 */
	public function resetScope($resetDefault = true)
	{
		$this->_criteria = ($resetDefault ? [] : null);
		return $this;
	}

	/**
	 * Returns the collection name as a string
	 *
	 * @example
	 *
	 * return 'users';
	 *
	 * @return string
	 */
	public function collectionName()
	{
		return get_class($this);
	}

	/**
	 * Denotes whether or not this document is versioned
	 * @return boolean
	 */
	public function versioned()
	{
		return false;
	}

	/**
	 * Denotes the field tob e used to house the version number
	 * @return string
	 */
	public function versionField()
	{
		return '_v';
	}

	/**
	 * Returns MongoId based on $value
	 *
	 * @deprecated This function will become deprecated in favour of consistently
	 * using the getPrimaryKey() function instead. Atm, however, the getPrimaryKey
	 * function actually chains onto this method. If you see this and are wondering
	 * about what you should do if you want custom primary keys etc just use the getPrimaryKey
	 * function as you would the getMongoId function. These two functions should never have been separate
	 * for they are the same essentially.
	 *
	 * As to what version this will become deprecated:- I dunno. It will not be soon since it will be a
	 * functionality breaker...
	 *
	 * @param string|\MongoId $value
	 * @return \MongoId
	 */
	public function getMongoId($value = null)
	{
		return $value instanceof \MongoId ? $value : new \MongoId($value);
	}

	/**
	 * Returns the value of the primary key
	 * @param string|\MongoId $value
	 * @return \MongoId
	 */
	public function getPrimaryKey($value = null)
	{
		if($value === null){
			$value = $this->{$this->primaryKey()};
		}
		return $this->getMongoId($value);
	}

	/**
	 * Returns if the current record is new.
	 * Whether the record is new and should be inserted when calling {@link save}.
	 * This property is automatically set in constructor and {@link populateRecord}.
	 * Defaults to false, but it will be set to true if the instance is created using
	 * the new operator.
	 * @return boolean
	 */
	public function getIsNewRecord()
	{
		return $this->_new;
	}

	/**
	 * Sets if the record is new.
	 * Whether the record is new and should be inserted when calling {@link save}.
	 * @see \Mongo\Document::getIsNewRecord()
	 * @param boolean $value
	 */
	public function setIsNewRecord($value)
	{
		$this->_new = (bool)$value;
	}

	/**
	 * Gets a list of the projected fields for the model
	 * @return array|string[]
	 */
	public function getProjectedFields()
	{
		return $this->_projected_fields;
	}

	/**
	 * Sets the projected fields of the model
	 * @param array|string[] $fields
	 */
	public function setProjectedFields(array $fields)
	{
		$this->_projected_fields = $fields;
	}

	/**
	 * Gets the version of this document
	 * @return string
	 */
	public function version()
	{
		return $this->{$this->versionField()};
	}

	/**
	 * Forceably increments the version of this document
	 * @return bool
	 */
	public function incrementVersion()
	{
		$resp = $this->updateByPk($this->getPrimaryKey(), ['$inc' => [$this->versionField() => 1]]);
		if($resp['n'] <= 0){
			return false;
		}
		$this->{$this->versionField()} += 1;
		return true;
	}

	/**
	 * Forceably sets the version of this document
	 * @param mixed $n
	 * @return bool
	 */
	public function setVersion($n)
	{
		$resp = $this->updateByPk($this->getPrimaryKey(), ['$set' => [$this->versionField() => $n]]);
		if($resp['n'] <= 0){
			return false;
		}
		$this->{$this->versionField()} = $n;
		return true;
	}

	/**
	 * Sets the attribute of the model
	 * @param string $name
	 * @param mixed $value
	 * @return bool
	 */
	public function setAttribute($name, $value)
	{
		// At the moment the projection is restricted to only fields returned in result set
		// Uncomment this to change that
		//if($this->getIsPartial())
		//	$this->_projected_fields[$name] = 1;
		return parent::setAttribute($name, $value);
	}

	/**
	 * Returns the static model of the specified AR class.
	 * The model returned is a static instance of the AR class.
	 * It is provided for invoking class-level methods (something similar to static class methods.)
	 *
	 * EVERY derived AR class must override this method as follows,
	 * <pre>
	 * public static function model($className=__CLASS__)
	 * {
	 *     return parent::model($className);
	 * }
	 * </pre>
	 *
	 * @param string $className
	 * @return \Mongo\Document
	 */
	public static function model($className = __CLASS__)
	{
		if(isset(self::$_models[$className])){
			return self::$_models[$className];
		}
		/** @var \Mongo\Document $model */
		$model = self::$_models[$className] = new $className(null);
		//$model->attachBehaviors($model->behaviors());
		return $model;
	}

	/**
	 * Instantiates a model from an array
	 * @param array $document
	 * @return \Mongo\Document
	 */
	protected function instantiate($document)
	{
		$class = get_class($this);
		return new $class(null);
	}

	/**
	 * Returns the text label for the specified attribute.
	 * This method overrides the parent implementation by supporting
	 * returning the label defined in relational object.
	 * In particular, if the attribute name is in the form of "post.author.name",
	 * then this method will derive the label from the "author" relation's "name" attribute.
	 * @see \Model::generateAttributeLabel()
	 * @param string $attribute - the attribute name
	 * @return string - the attribute label
	 */
	public function getAttributeLabel($attribute)
	{
		$labels = $this->attributeLabels();
		if(isset($labels[$attribute])){
			return $labels[$attribute];
		}
		if(strpos($attribute, '.') === false){
			return $this->generateAttributeLabel($attribute);
		}
		$segs = explode('.', $attribute);
		$name = array_pop($segs);
		$model = $this;
		foreach($segs as $seg){
			$relations = $model->relations();
			if(!isset($relations[$seg])){
				break;
			}
			$model = \Mongo\Document::model($relations[$seg][1]);
		}
		return $model->getAttributeLabel($name);
	}

	/**
	 * Creates an active record with the given attributes.
	 * This method is internally used by the find methods.
	 * Null is returned if the input data is false.
	 *
	 * @param array $attributes - attribute values (column name=>column value)
	 * @param boolean $callAfterFind whether to call {@link afterFind} after the record is populated.
	 * @param bool $partial
	 * @return \Mongo\Document|null - the newly created active record. The class of the object is the same as the model class.
	 */
	public function populateRecord($attributes, $callAfterFind = true, $partial = false)
	{
		if($attributes === false){
			return null;
		}
		$record = $this->instantiate($attributes);
		$record->setScenario('update');
		$record->setIsNewRecord(false);
		$record->init();

		$labels = [];
		foreach($attributes as $name=>$value){
			$labels[$name] = 1;
			$record->setAttribute($name, $value);
		}

		if($partial){
			$record->setIsPartial(true);
			$record->setProjectedFields($labels);
		}
		//$record->_pk=$record->primaryKey();
		//$record->attachBehaviors($record->behaviors());
		if($callAfterFind){
			$record->afterFind();
		}
		return $record;
	}

	/**
	 * Returns an array of records populated by incoming data
	 * @param array $data
	 * @param bool $callAfterFind
	 * @param string $index
	 * @return array - Array of the records
	 */
	public function populateRecords(array $data, $callAfterFind = true, $index = null)
	{
		$records = [];
		foreach($data as $attributes){
			if(($record = $this->populateRecord($attributes, $callAfterFind)) !== null){
				if($index === null){
					$records[] = $record;
				}else{
					$records[$record->$index] = $record;
				}
			}
		}
		return $records;
	}

	/**********/
	/* Events */
	/**********/

	/**
	 * @param CEvent $event
	 */
	public function onBeforeSave($event)
	{
		//$this->raiseEvent('onBeforeSave', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onAfterSave($event)
	{
		//$this->raiseEvent('onAfterSave', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onBeforeDelete($event)
	{
		//$this->raiseEvent('onBeforeDelete', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onAfterDelete($event)
	{
		//$this->raiseEvent('onAfterDelete', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onBeforeFind($event)
	{
		//$this->raiseEvent('onBeforeFind', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onAfterFind($event)
	{
		//$this->raiseEvent('onAfterFind', $event);
	}


	/**
	 * @return bool
	 */
	protected function beforeSave()
	{
		/*if($this->hasEventHandler('onBeforeSave')){
			$event = new \ModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}*/
		return true;
	}

	protected function afterSave()
	{
		/*if($this->hasEventHandler('onAfterSave')){
			$this->onAfterSave(new CEvent($this));
		}*/
	}


	/**
	 * @return bool
	 */
	protected function beforeDelete()
	{
		/*if($this->hasEventHandler('onBeforeDelete')){
			$event = new \ModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}*/
		return true;
	}

	protected function afterDelete()
	{
		/*if($this->hasEventHandler('onAfterDelete')){
			$this->onAfterDelete(new CEvent($this));
		}*/
	}

	protected function beforeFind()
	{
		/*if($this->hasEventHandler('onBeforeFind')){
			$event = new \ModelEvent($this);
			$this->onBeforeFind($event);
		}*/
	}

	protected function afterFind()
	{
		/*if($this->hasEventHandler('onAfterFind')){
			$this->onAfterFind(new CEvent($this));
		}*/
	}

	/**
	 * Saves this record
	 *
	 * If an attributes specification is sent in it will only validate and save those attributes
	 *
	 * @param boolean $runValidation
	 * @param array $attributes
	 * @return bool
	 */
	public function save($runValidation = true, $attributes = null){
		if(!$runValidation || $this->validate($attributes)){
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		}
		return false;
	}

	/**
	 * Saves only a specific subset of attributes as defined by the param
	 * @param array $attributes
	 * @return bool
	 * @throws \Mongo\Exception
	 */
	public function saveAttributes($attributes)
	{
		if($this->getIsNewRecord()){
			throw new \Mongo\Exception( 'The active record cannot be updated because it is new.');
		}
		
		$this->trace(__FUNCTION__);
		$values = [];
		foreach($attributes as $name => $value){
			if(is_integer($name)){
				$v = $this->$value;
				if(is_array($this->$value)){
					$v = $this->filterRawDocument($this->$value);
				}
				$values[$value] = $v;
			}else{
				$values[$name] = $this->$name = $value;
			}
		}
		if(!isset($this->{$this->primaryKey()}) || $this->getPrimaryKey() === null){
			throw new \Mongo\Exception('The active record cannot be updated because its _id is not set!');
		}
		return $this->lastError = $this->updateByPk($this->getPrimaryKey(), ['$set' => $values]);
	}

	/**
	 * Inserts this record
	 * @param array $attributes
	 * @return bool
	 * @throws \Mongo\Exception
	 */
	public function insert($attributes = null)
	{
		if(!$this->getIsNewRecord()){
			throw new \Mongo\Exception('The active record cannot be inserted to database because it is not new.');
		}
		if(!$this->beforeSave()){
			return false;
		}
		$this->trace(__FUNCTION__);

		if($attributes !== null){
			$document = $this->filterRawDocument($this->getAttributes($attributes));
		}else{
			$document = $this->getRawDocument();
		}
		if($this->versioned()){
			$document[$this->versionField()] = $this->{$this->versionField()} = 1;
		}

		if(!isset($this->{$this->primaryKey()})){
			$document['_id'] = $this->{$this->primaryKey()} = $this->getPrimaryKey();
		}
		if(\MongoDBSystem::factory()->isDebug()){
			// we're actually physically testing for Yii debug mode here to stop us from
			// having to do the serialisation on the update doc normally.
			\MongoDBSystem::trace('Executing insert: {$document:' . json_encode($document) . '}', 'extensions.MongoYii.\Mongo\Document');
		}
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::beginProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.insert(' . '{$document:' . json_encode($document) . '})',
				'extensions.MongoYii.\Mongo\Document.insert'
			);
		}
		$this->lastError = $this->getCollection()->insert($document, $this->getDbConnection()->getDefaultWriteConcern());
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.insert(' . '{$document:' . json_encode($document) . '})',
				'extensions.MongoYii.\Mongo\Document.insert'
			);
		}

		if($this->lastError){
			$this->afterSave();
			$this->setIsNewRecord(false);
			$this->setScenario('update');
			return true;
		}
		return false;
	}

	/**
	 * Updates this record
	 * @param array $attributes
	 * @return bool
	 * @throws \Mongo\Exception
	 * @throws \Mongo\Exception
	 */
	public function update($attributes = null)
	{
		if($this->getIsNewRecord()){
			throw new \Mongo\Exception( 'The active record cannot be updated because it is new.');
		}
		if(!$this->beforeSave()){
			return false;
		}
		$this->trace(__FUNCTION__);
		if($this->getPrimaryKey() === null){ // An _id is required
			throw new \Mongo\Exception( 'The active record cannot be updated because it has primary key set.');
		}

		$partial = false;
		if($attributes !== null){
			$attributes = $this->filterRawDocument($this->getAttributes($attributes));
			$partial = true;
		}elseif($this->getIsPartial()){
			foreach($this->_projected_fields as $field => $v){
				$attributes[$field] = $this->$field;
			}
			$attributes = $this->filterRawDocument($attributes);
			$partial = true;
		}else{
			$attributes = $this->getRawDocument();
		}
		if(isset($attributes['_id'])){
			unset($attributes['_id']); // Unset the _id before update
		}

		if($this->versioned()){

			$version = $this->{$this->versionField()};
			$attributes[$this->versionField()] = $this->{$this->versionField()} = $this->{$this->versionField()} > 0 ? $this->{$this->versionField()} + 1 : 1;

			if($partial === true){ // If this is a partial docuemnt we use $set to replace that partial view
				$attributes = ['$set' => $attributes];
				if(!isset($this->_projected_fields[$this->versionField()])){
					// We cannot rely on a partial document containing the version
					// as such it has been disabled for partial documents
					throw new \Mongo\Exception("You cannot update a versioned partial document unless you project out the version field as well");
				}
			}
			$this->lastError = $this->updateAll(
				[$this->primaryKey() => $this->getPrimaryKey(), $this->versionField() => $version],
				$attributes, 
				['multiple' => false]
			);
		}else{
			if($partial === true){ // If this is a partial docuemnt we use $set to replace that partial view
				$attributes = ['$set' => $attributes];
			}
			$this->lastError = $this->updateByPk($this->getPrimaryKey(), $attributes);
		}

		if($this->versioned() && $this->lastError['n'] <= 0){
			return false;
		}
		$this->afterSave();
		return true;
	}

	/**
	 * Deletes this record
	 * @return bool
	 * @throws \Mongo\Exception
	 */
	public function delete()
	{
		if($this->getIsNewRecord()){
			throw new \Mongo\Exception( 'The active record cannot be deleted because it is new.');
		}
		$this->trace(__FUNCTION__);
		if(!$this->beforeDelete()){
			return false;
		}
		$result = $this->deleteByPk($this->getPrimaryKey());
		$this->afterDelete();
		return $result;
	}

	/**
	 * Checks if a record exists in the database
	 * @param array $criteria
	 * @return bool
	 */
	public function exists($criteria = [])
	{
		$this->trace(__FUNCTION__);

		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		return $this->getCollection()->findOne($criteria) !== null;
	}

	/**
	 * Compares current active record with another one.
	 * The comparison is made by comparing table name and the primary key values of the two active records.
	 * @param \Mongo\Document $record - record to compare to
	 * @return boolean - whether the two active records refer to the same row in the database table.
	 */
	public function equals($record)
	{
		return $this->collectionName() === $record->collectionName() && (string)$this->getPrimaryKey() === (string)$record->getPrimaryKey();
	}

	/**
	 * Is basically a find one of the last version to be saved
	 * @return null|\Mongo\Document
	 */
	public function getLatest()
	{
		$c = $this->find([$this->primaryKey() => $this->getPrimaryKey()]);
		if($c->count() <= 0){
			return null;
		}
		foreach($c as $row){
			return $this->populateRecord($row);
		}
		return null;
	}

	/**
	 * Find one record
	 * @param array|\Mongo\Criteria $criteria
	 * @param array|string[] $fields
	 * @return \Mongo\Document|null
	 */
	public function findOne($criteria = [], $fields = [])
	{
		$this->trace(__FUNCTION__);

		$this->beforeFind(); // Apparently this is applied before even scopes...

		if($criteria instanceof \Mongo\Criteria)
		{
			$criteria = $criteria->getCondition();
		}
		$c = $this->getDbCriteria();

		$query = $this->mergeCriteria(isset($c['condition']) ? $c['condition'] : [], $criteria);
		$project = $this->mergeCriteria(isset($c['project']) ? $c['project'] : [], $fields);

		\MongoDBSystem::trace(
			'Executing findOne: '.'{$query:' . json_encode($query) . ',$project:' . json_encode($project) . '}',
			'extensions.MongoYii.\Mongo\Document'
		);
		
		if(
			$this->getDbConnection()->queryCachingCount > 0 
			&& $this->getDbConnection()->queryCachingDuration > 0
			&& $this->getDbConnection()->queryCacheID !== false
			&& ($cache = \MongoDBSystem::app()->getComponent($this->getDbConnection()->queryCacheID)) !== null
		){
			$this->getDbConnection()->queryCachingCount--;
			$cacheKey = 'yii:dbquery' . $this->getDbConnection()->server . ':' . $this->getDbConnection()->db;
			$cacheKey .= ':' . $this->getDbConnection()->getSerialisedQuery($query, $project) . ':' . $this->getCollection();
			if(($result = $cache->get($cacheKey)) !== false){
				\MongoDBSystem::trace('Query result found in cache', 'extensions.MongoYii.\Mongo\Document');
				$record = $result[0];
			}
		}else{
			if($this->getDbConnection()->enableProfiling){
				\MongoDBSystem::beginProfile(
					'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.findOne(' . '{$query:' . json_encode($query) . ',$project:' . json_encode($project) . '}' . ')',
					'extensions.MongoYii.\Mongo\Document.findOne'
				);
			}
	
			$record = $this->getCollection()->findOne($query, $project);
	
			if($this->getDbConnection()->enableProfiling){
				\MongoDBSystem::endProfile(
					'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.findOne(' . '{$query:' . json_encode($query) . ',$project:' . json_encode($project) . '}' . ')',
					'extensions.MongoYii.\Mongo\Document.findOne'
				);
			}
			
			if(isset($cache, $cacheKey)){
				$cache->set($cacheKey, [$record], $this->getDbConnection()->queryCachingDuration, $this->getDbConnection()->queryCachingDependency);
			}
		}
		
		if($record === null){
			return null;
		}
		$this->resetScope(false);
		return $this->populateRecord($record, true, $project === [] ? false : true);
	}

	/**
	 * Alias of find
	 * @param array $criteria
	 * @param array|string[] $fields
	 * @return \Mongo\Cursor|\Mongo\Document[]
	 */
	public function findAll($criteria = [], $fields = [])
	{
		return $this->find($criteria, $fields);
	}
	
	/**
	 * Alias of find
	 * @param array $criteria
	 * @param array|string[] $fields
	 * @return \Mongo\Cursor|\Mongo\Document[]
	 */
	public function findAllByAttributes($criteria = [], $fields = [])
	{
		return $this->find($criteria, $fields);
	}

	/**
	 * Finds all records based on $pk
	 * @param mixed $pk - String, MongoID or array of strings or MongoID values (one can mix strings and MongoID in the array)
	 * @param array|string[] $fields
	 * @return \Mongo\Cursor|\Mongo\Document[]
	 * @throws \Mongo\Exception
	 */
	public function findAllByPk($pk, $fields = [])
	{
		if(is_string($pk) || $pk instanceof \MongoId){
			return $this->find([$this->primaryKey() => $this->getPrimaryKey($pk)], $fields);
		}
		if(!is_array($pk)){
			throw new \Mongo\Exception( 'Set an incorrect primary key.');
		}
		foreach($pk as $key => $value){
			$pk[$key] = $this->getPrimaryKey($value);
		}
		return $this->find([$this->primaryKey() => ['$in' => $pk]], $fields);
	}

	/**
	 * Find some records
	 * @param array|\Mongo\Criteria $criteria
	 * @param array|string[] $fields
	 * @return \Mongo\Cursor|\Mongo\Document[]
	 */
	public function find($criteria = [], $fields = [])
	{
		$this->trace(__FUNCTION__);

		$this->beforeFind(); // Apparently this is applied before even scopes...

		if($criteria instanceof \Mongo\Criteria){
			$c = $criteria->mergeWith($this->getDbCriteria())->toArray();
			$criteria = [];
		}else{
			$c = $this->getDbCriteria();
		}

		$query = $this->mergeCriteria(isset($c['condition']) ? $c['condition'] : [], $criteria);
		$project = $this->mergeCriteria(isset($c['project']) ? $c['project'] : [], $fields);



		\MongoDBSystem::trace(
			'Executing find: ' . '{$query:'.json_encode($query) . ',$project:'.json_encode($project)
			 . (isset($c['sort']) ? ',$sort:'.json_encode($c['sort']).',' : '')
			 . (isset($c['skip']) ? ',$skip:'.json_encode($c['skip']).',' : '')
			 . (isset($c['limit']) ? ',$limit:'.json_encode($c['limit']).',' : '')
			.'}', 
			'extensions.MongoYii.\Mongo\Document'
		);
		
		if($this->getDbConnection()->enableProfiling){
			$token = 'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.find(' . '{$query:' . json_encode($query)
			 . ',$project:'.json_encode($project)
			 . (isset($c['sort']) ? ',$sort:'.json_encode($c['sort']).',' : '')
			 . (isset($c['skip']) ? ',$skip:'.json_encode($c['skip']).',' : '')
			 . (isset($c['limit']) ? ',$limit:'.json_encode($c['limit']).',' : '')
			 .'})';
			\MongoDBSystem::beginProfile($token, 'extensions.MongoYii.\Mongo\Document.find');
		}

		if($c !== []){
			$cursor = new \Mongo\Cursor($this, $query, $project);
			if(isset($c['sort'])){
				$cursor->sort($c['sort']);
			}
			if(isset($c['skip'])){
				$cursor->skip($c['skip']);
			}
			if(isset($c['limit'])){
				$cursor->limit($c['limit']);
			}
			$this->resetScope(false);
		}else{
			$cursor = new \Mongo\Cursor($this, $criteria, $project);
		}
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile($token, 'extensions.MongoYii.\Mongo\Document.find');
		}
		return $cursor;
	}

	/**
	 * Finds one by _id
	 * @param string|\MongoId $_id
	 * @param array|string[] $fields
	 * @return \Mongo\Document|null
	 */
	public function findBy_id($_id, $fields = [])
	{
		$this->trace(__FUNCTION__);
		$_id = $this->getPrimaryKey($_id);
		return $this->findOne([$this->primaryKey() => $_id], $fields);
	}

	/**
	 * An alias for findBy_id() that relates to Yiis own findByPk
	 * @param string|\MongoId $pk
	 * @param array|string[] $fields
	 * @return \Mongo\Document|null
	 */
	public function findByPk($pk, $fields = [])
	{
		$this->trace(__FUNCTION__);
		return $this->findBy_id($pk, $fields);
	}

	/**
	 * Delete record by pk
	 * @param string|\MongoId $pk
	 * @param array|\Mongo\Criteria $criteria
	 * @param array $options
	 * @return mixed
	 */
	public function deleteByPk($pk, $criteria = [], $options = [])
	{
		$this->trace(__FUNCTION__);

		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		$pk = $this->getPrimaryKey($pk);

		$query=array_merge([$this->primaryKey() => $pk], $criteria);

		\MongoDBSystem::trace('Executing deleteByPk: ' . '{$query:' . json_encode($query) . '}', 'extensions.MongoYii.\Mongo\Document');

		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::beginProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.deleteByPk({$query:' . json_encode($query) . '})',
				'extensions.MongoYii.\Mongo\Document.deleteByPk'
			);
		}
		$result = $this->getCollection()->remove($query, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.deleteByPk({$query:' . json_encode($query) . '})',
				'extensions.MongoYii.\Mongo\Document.deleteByPk'
			);
		}
		return $result;
	}

	/**
	 * Update record by PK
	 * @param string|\MongoId $pk
	 * @param array $updateDoc
	 * @param array|\Mongo\Criteria $criteria
	 * @param array $options
	 * @return bool
	 */
	public function updateByPk($pk, $updateDoc = [], $criteria = [], $options = [])
	{
		$this->trace(__FUNCTION__);

		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		$pk = $this->getPrimaryKey($pk);

		$query=$this->mergeCriteria($criteria, [$this->primaryKey() => $pk]);

		if(\MongoDBSystem::factory()->isDebug()){
			// we're actually physically testing for Yii debug mode here to stop us from
			// having to do the serialisation on the update doc normally.
			\MongoDBSystem::trace(
				'Executing updateByPk: {$query:' . json_encode($query) . ',$document:' . json_encode($updateDoc) . '}', 
				'extensions.MongoYii.\Mongo\Document'
			);
		}
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::beginProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.updateByPk({$query:' . json_encode($query) . ',$document:' . json_encode($updateDoc) . '})',
				'extensions.MongoYii.\Mongo\Document.updateByPk'
			);
		}

		$result = $this->getCollection()->update(
			$query, 
			$updateDoc,
			array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options)
		);

		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.updateByPk({$query:' . json_encode($query) . ',$document:' . json_encode($updateDoc) . '})',
				'extensions.MongoYii.\Mongo\Document.updateByPk'
			);
		}
		return $result;
	}

	/**
	 * Update all records matching a criteria
	 * @param array|\Mongo\Criteria $criteria
	 * @param array $updateDoc
	 * @param array $options
	 * @return bool
	 */
	public function updateAll($criteria = [], $updateDoc = [], $options = ['multiple' => true])
	{
		$this->trace(__FUNCTION__);

		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		$options = array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options);

		if(\MongoDBSystem::factory()->isDebug()){
			// we're actually physically testing for Yii debug mode here to stop us from
			// having to do the serialisation on the update doc normally.
			\MongoDBSystem::trace(
				'Executing updateAll: {$query:' . json_encode($criteria)
				 . ',$document:' . json_encode($updateDoc) . ',$options:' . json_encode($options) . '}', 
				'extensions.MongoYii.\Mongo\Document'
			);
		}
		if($this->getDbConnection()->enableProfiling){
			$token = 'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.updateAll({$query:' . json_encode($criteria)
			 . ',$document:'.json_encode($updateDoc)
			 . ',$options:'.json_encode($options).'})';
			\MongoDBSystem::beginProfile($token, 'extensions.MongoYii.\Mongo\Document.updateAll');
		}

		$result = $this->getCollection()->update($criteria, $updateDoc, $options);

		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile($token, 'extensions.MongoYii.\Mongo\Document.updateAll');
		}
		return $result;
	}

	/**
	 * Delete all records matching a criteria
	 * @param array|\Mongo\Criteria $criteria
	 * @param array $options
	 * @return mixed
	 */
	public function deleteAll($criteria = [], $options = [])
	{
		$this->trace(__FUNCTION__);

		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		
		\MongoDBSystem::trace(
			'Executing deleteAll: {$query:' . json_encode($criteria) . '}', 
			'extensions.MongoYii.\Mongo\Document'
		);
		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::beginProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.deleteAll({$query:' . json_encode($criteria) . '})',
				'extensions.MongoYii.\Mongo\Document.deleteAll'
			);
		}
		
		$result = $this->getCollection()->remove($criteria, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));

		if($this->getDbConnection()->enableProfiling){
			\MongoDBSystem::endProfile(
				'extensions.MongoYii.\Mongo\Document.query.' . $this->collectionName() . '.deleteAll({$query:' . json_encode($criteria) . '})',
				'extensions.MongoYii.\Mongo\Document.deleteAll'
			);
		}
		return $result;
	}

	/**
	 * @see http://www.yiiframework.com/doc/api/1.1/CActiveRecord#saveCounters-detail
	 * @param array $counters
	 * @param null $lower - define a lower that the counter should not pass. IS NOT ATOMIC
	 * @param null $upper
	 * @return bool
	 * @throws \Mongo\Exception
	 */
	public function saveCounters(array $counters, $lower = null, $upper = null)
	{
		$this->trace(__FUNCTION__);

		if($this->getIsNewRecord()){
			throw new \Mongo\Exception( 'The active record cannot be updated because it is new.');
		}
		if(sizeof($counters) > 0){
			foreach($counters as $key => $value){
				if(
					($lower !== null && (($this->$key + $value) >= $lower)) ||
					($upper !== null && (($this->$key + $value) <= $upper)) ||
					($lower === null && $upper === null)
				){
					$this->$key = $this->$key + $value;
				}else{
					unset($counters[$key]);
				}
			}
			if(count($counters) > 0){
				return $this->updateByPk($this->getPrimaryKey(), ['$inc' => $counters]);
			}
		}
		return true; // Assume true since the action did run it just had nothing to update...
	}

	/**
	 * Count() allows you to count all the documents returned by a certain condition, it is analogous
	 * to $db->collection->find()->count() and basically does exactly that...
	 * @param \Mongo\Criteria|array $criteria
	 * @return int
	 */
	public function count($criteria = [])
	{
		$this->trace(__FUNCTION__);

		// If we provide a manual criteria via \Mongo\Criteria or an array we do not use the models own DbCriteria
		if (is_array($criteria) && empty($criteria)){
			$criteria = $this->getDbCriteria();
			$criteria = (isset($criteria['condition']) ? $criteria['condition'] : []);
		}
		if($criteria instanceof \Mongo\Criteria){
			$criteria = $criteria->getCondition();
		}
		return $this->getCollection()->find($criteria)->count();
	}

	/**
	 * Gives basic searching abilities for things like CGridView
	 * @param array $query - allows you to specify a query which should always take hold along with the searched fields
	 * @param array $project - search fields
	 * @param bool $partialMatch
	 * @param array $sort
	 * @return \Mongo\DataProvider
	 */
	public function search($query = [], $project = [], $partialMatch=false, $sort = [])
	{
		$this->trace(__FUNCTION__);

		foreach($this->getSafeAttributeNames() as $attribute){

			$value = $this->{$attribute};
			if($value !== null && $value !== ''){
				if((is_array($value) && count($value)) || is_object($value) || is_bool($value)){
					$query[$attribute] = $value;
				}elseif(preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)){
					$value = $matches[2];
					$op = $matches[1];
					if($partialMatch === true){
						$value = new \MongoRegex("/$value/i");
					}else{
						if(
							!is_bool($value) && !is_array($value) && preg_match('/^([0-9]|[1-9]{1}\d+)$/' /* Will only match real integers, unsigned */, $value) > 0
							&& ( 
								(PHP_INT_MAX > 2147483647 && (string)$value < '9223372036854775807') /* If it is a 64 bit system and the value is under the long max */
								|| (string)$value < '2147483647' /* value is under 32bit limit */
							)
						){
							$value = (int)$value;
						}
					}

					switch($op){
						case '<>':
							$query[$attribute] = ['$ne' => $value];
							break;
						case '<=':
							$query[$attribute] = ['$lte' => $value];
							break;
						case '>=':
							$query[$attribute] = ['$gte' => $value];
							break;
						case '<':
							$query[$attribute] = ['$lt' => $value];
							break;
						case '>':
							$query[$attribute] = ['$gt' => $value];
							break;
						case '=':
						default:
							$query[$attribute] = $value;
							break;
					}
				}
			}
		}
		return new \Mongo\DataProvider($this, ['criteria' => ['condition' => $query, 'project' => $project, 'sort' => $sort]]);
	}

	/**
	 * This is an aggregate helper on the model
	 * Note: This does not return the model but instead the result array directly from MongoDB.
	 * @param array $pipeline
	 * @return mixed
	 */
	public function aggregate($pipeline)
	{
		$this->trace(__FUNCTION__);
		return $this->getDbConnection()->aggregate($this->collectionName(), $pipeline);
	}

	/**
	 * A distinct helper on the model, this is not the same as the aggregation framework
	 * distinct
	 * @link http://docs.mongodb.org/manual/reference/command/distinct/
	 * @param string $key
	 * @param array $query
	 * @return mixed
	 */
	public function distinct($key, $query = [])
	{
		$this->trace(__FUNCTION__);
		$c = $this->getDbCriteria();
		if(is_array($c) && isset($c['condition']) && !empty($c['condition'])){
			//$query = CMap::mergeArray($query, $c['condition']);
		}


		return \MongoDBSystem::factory()->getMongoComponent()->command([
			'distinct' => $this->collectionName(),
			'key' => $key,
			'query' => $query
		]);
	}

	/**
	 * A mapreduce helper for this model
	 * @param \MongoCode $map
	 * @param \MongoCode $reduce
	 * @param \MongoCode $finalize
	 * @param array $out
	 * @param array $query
	 * @param array $options // All other options for input to the command
	 * @return mixed
	 */
	public function mapreduce($map, $reduce, $finalize = null, $out, $query = [], $options = [])
	{
		return $this->getDbConnection()->getDB()->command(array_merge([
			'mapreduce' => $this->collectionName(),
			'map' => $map,
			'reduce' => $reduce,
			'finalize' => $finalize,
			'query' => $query,
			'out' => $out
		],$options));
	}

	/**
	 * Allows a user to ensure a number of indexes using the format:
	 * ensureIndexes(array(
	 *    array(array('email' => 1), array('unique' => true))
	 * ))
	 * where the 0 offset in each nested array is the fields for the index and the 1 offset
	 * is the options. You don't have to define options for the index.
	 * or:
	 * ensureIndexes(array(
	 *    array('email' => 1, 'address' => -1)
	 * ))
	 * @param unknown $indexes
	 * @return bool
	 */
	public function ensureIndexes($indexes)
	{
		foreach($indexes as $index){
			if(isset($index[0])){
				$this->getCollection()->ensureIndex($index[0], isset($index[1]) ? $index[1] : []);
			}else{
				$this->getCollection()->ensureIndex($index, []);
			}
		}
		return true;
	}
	
	/**
	 * Sets the parameters for query caching.
	 * This is a shortcut method to {@link CDbConnection::cache()}.
	 * It changes the query caching parameter of the {@link dbConnection} instance.
	 * @param integer $duration the number of seconds that query results may remain valid in cache.
	 * If this is 0, the caching will be disabled.
	 * @param CCacheDependency|ICacheDependency $dependency the dependency that will be used when saving
	 * the query results into cache.
	 * @param integer $queryCount number of MongoDB queries that need to be cached after calling this method. Defaults to 1,
	 * meaning that the next MongoDB query will be cached.
	 * @return \Mongo\Document the active record instance itself.
	 */
	public function cache($duration, $dependency=null, $queryCount=1)
	{
		$this->getDbConnection()->cache($duration, $dependency, $queryCount);
		return $this;
	}

	/**
	 * Refreshes the data from the database
	 * @return bool
	 */
	public function refresh()
	{
		$this->trace(__FUNCTION__);
		if(
			!$this->getIsNewRecord() && 
			($record = $this->getCollection()->findOne([$this->primaryKey() => $this->getPrimaryKey()])) !== null
		){
			$this->clean();
			foreach($record as $name => $column){
				$this->$name = $record[$name];
			}
			return true;
		}
		return false;
	}

	/**
	 * A bit deceptive, this actually gets the last response from either save() or update(). The reason it is called this
	 * is because MongoDB calls it this and so it seems better to have unity on that front.
	 * @return array
	 */
	public function getLastError()
	{
		return $this->lastError;
	}

	/**
	 * gets and if null sets the db criteria for this model
	 * @param bool $createIfNull
	 * @return array
	 */
	public function getDbCriteria($createIfNull = true)
	{
		if($this->_criteria === null){
			if(($c = $this->defaultScope()) !== [] || $createIfNull){
				$this->_criteria = $c;
			}else{
				return [];
			}
		}
		return $this->_criteria;
	}

	/**
	 * Sets the db criteria for this model
	 * @param array $criteria
	 * @return array
	 */
	public function setDbCriteria(array $criteria)
	{
		return $this->_criteria = $criteria;
	}

	/**
	 * Merges the current DB Criteria with the inputted one
	 * @param array|\Mongo\Criteria $newCriteria
	 * @return array
	 */
	public function mergeDbCriteria($newCriteria)
	{
		if($newCriteria instanceof \Mongo\Criteria){
			$newCriteria = $newCriteria->toArray();
		}
		return $this->setDbCriteria($this->mergeCriteria($this->getDbCriteria(), $newCriteria));
	}

	/**
	 * Gets the collection for this model
	 * @return \MongoCollection
	 */
	public function getCollection()
	{
		return $this->getDbConnection()->{$this->collectionName()};
	}

	/**
	 * Merges two criteria objects. Best used for scopes
	 * @param array $oldCriteria
	 * @param array $newCriteria
	 * @return array
	 */
	public function mergeCriteria($oldCriteria, $newCriteria)
	{
		return self::mergeArray($oldCriteria, $newCriteria);
	}

	/**
	 * Produces a trace message for functions in this class
	 * @param string $func
	 */
	public function trace($func)
	{
		\MongoDBSystem::trace(get_class($this) . '.' . $func . '()', 'extensions.MongoYii.\Mongo\Document');
	}

	public static function mergeArray($a,$b)
	{
		foreach($b as $k=>$v)
		{
			if(is_integer($k))
				$a[]=$v;
			else if(is_array($v) && isset($a[$k]) && is_array($a[$k]))
				$a[$k]=self::mergeArray($a[$k],$v);
			else
				$a[$k]=$v;
		}
		return $a;
	}
}