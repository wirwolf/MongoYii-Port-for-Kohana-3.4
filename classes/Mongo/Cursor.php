<?php
namespace Mongo;
use Mongo\Criteria;
use Mongo\Document;

/**
 * \Mongo\Cursor
 *
 * Represents the Yii edition to the MongoCursor and allows for lazy loading of objects.
 *
 * This class does not support eager loading by default, in order to use eager loading you should look into using this
 * classes reponse with iterator_to_array().
 *
 * I did try originally to make this into a active data provider and use this for two fold operations but the cactivedataprovider would extend
 * a lot for the cursor and the two took quite different constructors.
 */
class Cursor implements \Iterator, \Countable
{
	/**
	 * @var array|\Mongo\Criteria
	 */
	public $criteria = [];
	
	/**
	 * @var string
	 */
	public $modelClass;
	
	/**
	 * @var Document
	 */
	public $model;
	
	/**
	 * @var array|Cursor|Document[]
	 */
	private $cursor = [];
	
	/**
	 * @var Document
	 */
	private $current;

	/**
	 * This denotes a partial cursor which in turn will transpose onto the active record
	 * to state a partial document. If any projection is supplied this will result in true since
	 * I cannot detect if you are projecting the whole document or not...THERE IS NO PRE-DEFINED SCHEMA
	 * @var boolean
	 */
	private $partial = false;
	
	private $run = false;
	
	private $fromCache = false;
	
	private $cachedArray = [];

	/**
	 * The cursor constructor
	 * @param string|Document $modelClass - The class name for the active record
	 * @param array|Cursor|\Mongo\Criteria $criteria -  Either a condition array (without sort,limit and skip) or a \Mongo\Cursor Object
	 * @param array $fields
	 */
	public function __construct($modelClass, $criteria = [], $fields = [])
	{
		// If $fields has something in it
		if(!empty($fields)){
			$this->partial = true;
		}

		if(is_string($modelClass)){
			$this->modelClass = $modelClass;
			$this->model = Document::model($this->modelClass);
		}elseif($modelClass instanceof Document){
			$this->modelClass = get_class($modelClass);
			$this->model = $modelClass;
		}

		if($criteria instanceof Cursor){
			$this->cursor = $criteria;
			$this->cursor->reset();
		}elseif($criteria instanceof Criteria){
			$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection()->find($criteria->condition, $criteria->project)->sort($criteria->sort);
			if($criteria->skip > 0){
				$this->cursor->skip($criteria->skip);
			}
			if($criteria->limit > 0){
				$this->cursor->limit($criteria->limit);
			}
		}else{
			// Then we are doing an active query
			$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection()->find($criteria, $fields);
		}
	}

	/**
	 * If we call a function that is not implemented here we try and pass the method onto
	 * the \Mongo\Cursor class, otherwise we produce the error that normally appears

	 * @param string $method
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
	public function __call($method, $params = [])
	{
		if($this->cursor() instanceof Cursor && method_exists($this->cursor(), $method)){
			return call_user_func_array([$this->cursor(), $method], $params);
		}
		throw new Exception('Call to undefined function {method} on the cursor', ['{method}' => $method]);
	}

	/**
	 * Holds the \Mongo\Cursor
	 * @return array|Cursor
	 */
	public function cursor()
	{
		return $this->cursor;
	}

	/**
	 * Get next doc in cursor
	 * @return Document|null
	 */
	public function getNext()
	{
		if(!$this->fromCache){
			if($c = $this->cursor()->getNext()){
				return $this->current = $this->model->populateRecord($c, true, $this->partial);
			}
		}else{
			if($c = $this->next()){
				return $this->current = $this->model->populateRecord($c, true, $this->partial);
			}
		}
		return null;
	}

	/**
	 * Gets the active record for the current row
	 * @return Document|mixed
	 * @throws Exception
	 */
	public function current()
	{
		if(!$this->run){
			if(
				$this->model->getDbConnection()->queryCachingCount > 0
				&& $this->model->getDbConnection()->queryCachingDuration > 0
				&& $this->model->getDbConnection()->queryCacheID !== false
				&& ($cache = Yii::app()->getComponent($this->model->getDbConnection()->queryCacheID)) !== null
			){
				$this->model->getDbConnection()->queryCachingCount--;
				$info = $this->cursor()->info();

				$cacheKey =
				'yii:dbquery' . $this->model->getDbConnection()->server . ':' . $this->model->getDbConnection()->db
				. ':' . $this->model->getDbConnection()->getSerialisedQuery(
					is_array($info['query']) && isset($info['query']['$query']) ? $info['query']['$query'] : [],
					$info['fields'], 
					is_array($info['query']) && isset($info['query']['$orderby']) ? $info['query']['$orderby'] : [],
					$info['skip'], 
					$info['limit']
				)
				. ':' . $this->model->getCollection();

				if(($result = $cache->get($cacheKey)) !== false){
					//Yii::trace('Query result found in cache', 'extensions.MongoYii.\Mongo\Document');
					$this->cachedArray = $result;
					$this->fromCache = true;
				}else{
					$this->cachedArray = iterator_to_array($this->cursor);
				}
			}
				
			if(isset($cache, $cacheKey)){
				$cache->set(
					$cacheKey,
					$this->cachedArray,
					$this->model->getDbConnection()->queryCachingDuration,
					$this->model->getDbConnection()->queryCachingDependency
				);
				$this->fromCache = true;
			}
				
			$this->run = true;
		}
		
		if($this->model === null){
			throw new Exception('The \Mongo\Cursor must have a model');
		}
		if($this->fromCache){
			return $this->current = $this->model->populateRecord(current($this->cachedArray), true, $this->partial);
		}
		return $this->current = $this->model->populateRecord($this->cursor()->current(), true, $this->partial);
	}

	/**
	 * Counts the records returned by the criteria. By default this will not take skip and limit into account 
	 * you can add inject true as the first and only parameter to enable MongoDB to take those offsets into 
	 * consideration.
	 *  
	 * @param bool $takeSkip
	 * @return int
	 */
	public function count($takeSkip = false /* Was true originally but it was to change the way the driver worked which seemed wrong */)
	{
		if($this->fromCache){
			return count($this->cachedArray);
		}
		return $this->cursor()->count($takeSkip);
	}

	/**
	 * Set SlaveOkay
	 * @param bool $val
	 * @return Cursor
	 */
	public function slaveOkay($val = true)
	{
		$this->cursor()->slaveOkay($val);
		return $this;
	}

	/**
	 * Set sort fields
	 * @param array $fields
	 * @return Cursor
	 */
	public function sort(array $fields)
	{
		$this->cursor()->sort($fields);
		return $this;
	}

	/**
	 * Set skip
	 * @param int $num
	 * @return Cursor
	 */
	public function skip($num = 0)
	{
		$this->cursor()->skip($num);
		return $this;
	}

	/**
	 * Set limit
	 * @param int $num
	 * @return Cursor
	 */
	public function limit($num = 0)
	{
		$this->cursor()->limit($num);
		return $this;
	}
	
	public function timeout($ms)
	{
		$this->cursor()->timeout($ms);
		return $this;
	}

	/**
	 * Reset the \Mongo\Cursor to the beginning
	 * @return Cursor
	 */
	public function rewind()
	{
		$this->run = false;
		$this->cursor()->rewind();
		return $this;
	}

	/**
	 * Get the current key (_id)
	 * @return mixed|string
	 */
	public function key()
	{
		if($this->fromCache){
			return key($this->cachedArray);
		}
		return $this->cursor()->key();
	}

	/**
	 * Move the pointer forward
	 */
	public function next()
	{
		if($this->fromCache){
			return next($this->cachedArray);
		}
		$this->cursor()->next();
	}

	/**
	 * Check if this position is a valid one in the cursor
	 * @return bool
	 */
	public function valid()
	{
		if($this->fromCache){
			return array_key_exists(key($this->cachedArray), $this->cachedArray);
		}
		return $this->cursor()->valid();
	}
}