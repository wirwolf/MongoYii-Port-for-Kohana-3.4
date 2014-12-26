<?php
namespace Mongo;
/**
 * The MongoYii representation of a helper for uploading files to GridFS.
 *
 * It can accept an input file from $_FILES via ::populate and can also do find() and findOne() on the files collection.
 * This file is specifically designed for uploading files from a form to GridFS and is merely a helper, IT IS IN NO WAY REQUIRED.
 */
class File extends \Mongo\Document
{
	/**
	 * Our file object, can be either the MongoGridFSFile or CUploadFile
	 */
	private $_file;

	// Helper functions to get some common functionality on this class

	/**
	 * @return string|bool
	 */
	public function getFilename()
	{
		if($this->getFile() instanceof MongoGridFSFile){
			return $this->getFile()->getFilename();
		}
		if($this->getFile() instanceof CUploadedFile){
			return $this->getFile()->getTempName();
		}
		if(is_string($this->getFile()) && is_file($this->getFile())){
			return $this->getFile();
		}
		return false;
	}


	/**
	 * @return int|bool
	 */
	public function getSize()
	{
		if($this->getFile() instanceof MongoGridFSFile || $this->getFile() instanceof CUploadedFile){
			return $this->getFile()->getSize();
		}
		if(is_file($this->getFile())){
			return filesize($this->getFile());
		}
		return false;
	}


	/**
	 * @return string|bool
	 */
	public function getBytes()
	{
		if($this->getFile() instanceof MongoGridFSFile){
			return $this->getFile()->getBytes();
		}
		if($this->getFile() instanceof CUploadedFile || (is_file($this->getFile()) && is_readable($this->getFile()))){
			return file_get_contents($this->getFilename());
		}
		return false;
	}

	/**
	 * Gets the file object
	 */
	public function getFile()
	{
		// This if statement allows for you to continue using this class AFTER insert
		// basically it will only get the file if you plan on using it further which means that
		// otherwise it omits at least one database call each time
		if($this->_id instanceof MongoId && !$this->_file instanceof MongoGridFSFile){
			return $this->_file = $this->getCollection()->get($this->_id);
		}
		return $this->_file;
	}

	/**
	 * Sets the file object
	 * @param $v
	 */
	public function setFile($v)
	{
		$this->_file = $v;
	}

	/**
	 * This denotes the prefix to all gridfs collections set by this class
	 * @return string
	 */
	public function collectionPrefix()
	{
		return 'fs';
	}

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className
	 * @return \Mongo\Document - User the static model class
	 */
	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * Magic will either call a function on the file if it exists or bubble to parent
	 * @see \Mongo\Document::__call()
	 * @param string $name
	 * @param array $parameters
	 * @return mixed|Document
	 */
	public function __call($name, $parameters)
	{
		if($this->getFile() instanceof MongoGridFSFile && method_exists($this->getFile(), $name)){
			return call_user_func_array([$this->getFile(), $name], $parameters);
		}
		return parent::__call($name, $parameters);
	}

	/**
	 * This can populate from a $_FILES instance
	 * @param CModel $model
	 * @param string $attribute
	 * @return boolean|\Mongo\File|null
	 */
	public static function populate($model, $attribute)
	{
		if($file = CUploadedFile::getInstance($model, $attribute)){
			$model=new \Mongo\File();
			$model->setFile($file);
			return $model;
		}
		return null;
	}

	/**
	 * This function populates from a stream
	 *
	 * You must unlink the tempfile yourself by calling unlink($file->getFilename())
	 * @param string $stream
	 * @return \Mongo\File the new file generated from the stream

	 public static function stream($stream){
		$tempFile = tempnam(null, 'tmp'); // returns a temporary filename

		$fp = fopen($tempFile, 'wb');     // open temporary file
		$putData = fopen($stream, 'rb'); // open input stream

		stream_copy_to_stream($putData, $fp);  // write input stream directly into file

		fclose($putData);
		fclose($fp);

		$file = new \Mongo\File();
		$file->setFile($tempFile);
		return $file;
		}
	*/

	/**
	 * Replaces the normal populateRecord specfically for GridFS by setting the attributes from the
	 * MongoGridFsFile object correctly and other file details like size and name.
	 * @see \Mongo\Document::populateRecord()
	 * @param array $attributes
	 * @param bool $callAfterFind
	 * @param bool $partial
	 * @return \Mongo\Document|null
	 */
	public function populateRecord($attributes, $callAfterFind = true, $partial = false)
	{
		if($attributes === false){
			return null;
		}
		// the cursor will actually input a MongoGridFSFile object as the "document"
		// so what we wanna do is get the attributes or metadata attached to the file object
		// set it as our attributes and then set this classes file as the first param we got
		$file = $attributes;
		$attributes = $file->file;
		$record = $this->instantiate($attributes);
		$record->setFile($file);
		$record->setScenario('update');
		$record->setIsNewRecord(false);
		$record->init();

		$labels = [];
		foreach($attributes as $name => $value){
			$labels[$name] = 1;
			$record->$name = $value;
		}

		if($partial){
			$record->setIsPartial(true);
			$record->setProjectedFields($labels);
		}
		//$record->_pk=$record->primaryKey();
		$record->attachBehaviors($record->behaviors());
		if($callAfterFind){
			$record->afterFind();
		}
		return $record;
	}

	/**
	 * Inserts the file.
	 *
	 * The only difference between the normal insert is that this uses the storeFile function on the GridFS object
	 * @see \Mongo\Document::insert()
	 * @param array $attributes
	 * @return bool
	 * @throws \Mongo\Exception
	 */
	public function insert($attributes = null)
	{
		if(!$this->getIsNewRecord()){
			throw new \Mongo\Exception(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
		}
		if(!$this->beforeSave()){
			return false;
		}

		$this->trace(__FUNCTION__);
		if($attributes === null){
			$document=$this->getRawDocument();
		}else{
			$document=$this->filterRawDocument($this->getAttributes($attributes));
		}

		if(YII_DEBUG){
			// we're actually physically testing for Yii debug mode here to stop us from
			// having to do the serialisation on the update doc normally.
			Yii::trace('Executing storeFile: {$document:' . json_encode($document) . '}', 'extensions.MongoYii.\Mongo\Document');
		}
		if($this->getDbConnection()->enableProfiling){
			$this->profile('extensions.MongoYii.\Mongo\File.insert({$document:' . json_encode($document) . '})', 'extensions.MongoYii.\Mongo\File.insert');
		}

		if($_id = $this->getCollection()->storeFile($this->getFilename(), $document)){ // The key change
			$this->_id = $_id;
			$this->afterSave();
			$this->setIsNewRecord(false);
			$this->setScenario('update');
			return true;
		}
		return false;
	}

	/**
	 * Get collection will now return the GridFS object from the driver
	 * @see \Mongo\Document::getCollection()
	 */
	public function getCollection()
	{
		return $this->getDbConnection()->getDB()->getGridFS($this->collectionPrefix());
	}

	/**
	 * Produces a trace message for functions in this class
	 * @param string $func
	 */
	public function trace($func)
	{
		Yii::trace(get_class($this) . '.' . $func.'()', 'extensions.MongoYii.\Mongo\File');
	}
}