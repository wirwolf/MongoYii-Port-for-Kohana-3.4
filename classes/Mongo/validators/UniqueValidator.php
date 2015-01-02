<?php
namespace Mongo\validators;
/**
 * EMongoUniqueValidator validates that the attribute value is unique in the corresponding database table.
 *
 * When using the {@link message} property to define a custom error message, the message
 * may contain additional placeholders that will be replaced with the actual content. In addition
 * to the "{attribute}" placeholder, recognized by all validators (see {@link CValidator}),
 * EMongoUniqueValidator allows for the following placeholders to be specified:
 * <ul>
 * <li>{value}: replaced with current value of the attribute.</li>
 * </ul>
 */
class UniqueValidator extends \Validator\Validator
{
	/**
	 * @var boolean whether the comparison is case sensitive. Defaults to true.
	 * Note, by setting it to false, you are assuming the attribute type is string.
	 */
	public $caseSensitive = true;
	
	/**
	 * @var boolean whether the attribute value can be null or empty. Defaults to true,
	 * meaning that if the attribute is empty, it is considered valid.
	 */
	public $allowEmpty = true;
	
	/**
	 * @var string the ActiveRecord class name that should be used to
	 * look for the attribute value being validated. Defaults to null, meaning using
	 * the class of the object currently being validated.
	 * You may use path alias to reference a class name here.
	 * @see attributeName
	 */
	public $className;
	
	/**
	 * @var string the ActiveRecord class attribute name that should be
	 * used to look for the attribute value being validated. Defaults to null,
	 * meaning using the name of the attribute being validated.
	 * @see className
	 */
	public $attributeName;
	
	/**
	 * @var mixed additional query criteria. Either an array or CDbCriteria.
	 * This will be combined with the condition that checks if the attribute
	 * value exists in the corresponding table column.
	 * This array will be used to instantiate a {@link CDbCriteria} object.
	 */
	public $criteria = [];
	
	/**
	 * @var string the user-defined error message. The placeholders "{attribute}" and "{value}"
	 * are recognized, which will be replaced with the actual attribute name and value, respectively.
	 */
	public $message;
	
	/**
	 * @var boolean whether this validation rule should be skipped if when there is already a validation
	 * error for the current attribute. Defaults to true.
	 */
	public $skipOnError = true;

	/**
	 * Validates the attribute of the object.
	 * If there is any error, the error message is added to the object.
	 * @param \Model $object the object being validated
	 * @param string $attribute the attribute being validated
	 */
	protected function validateAttribute($object,$attribute)
	{
		$value = $object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value)){
			return;
		}
		
		$className = $this->className === null ? get_class($object) : Yii::import($this->className);
		$attributeName = $this->attributeName === null ? $attribute : $this->attributeName;

		// We get a RAW document here to prevent the need to make yet another active record instance
		$doc = \Mongo\Document::model($className)
			->getCollection()
			->findOne(
				array_merge(
					$this->criteria, 
					[$attributeName => $this->caseSensitive ? $value : new \MongoRegex('/' . $value . '/i')]
				)
			);

		// If a doc was fund and it isn't this doc, as decided by the primnary key
		if($doc && (string)$doc[$object->primaryKey()] != (string)$object->getPrimaryKey()){
			// Then it ain't unique
			$message = $this->message !== null ? $this->message : '{attribute} "{'.$value.'}" has already been taken.';
			$this->addError($object, $attribute, $message, ['{value}' => CHtml::encode($value)]);
		}else{}
	}
}