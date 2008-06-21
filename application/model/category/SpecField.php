<?php

ClassLoader::import("application.model.eav.EavFieldCommon");
ClassLoader::import("application.model.category.Category");
ClassLoader::import("application.model.category.SpecFieldValue");
ClassLoader::import("application.model.category.SpecFieldGroup");
ClassLoader::import('application.model.specification.*');

/**
 * Specification attributes allow to define specific product models with a specific set of features or parameters.
 *
 * Each SpecField is a separate attribute. For example, screen size for laptops, ISBN code for books,
 * horsepowers for cars, etc. Since SpecFields are linked to categories, products from different categories can
 * have different set of attributes.
 *
 * @package application.model.category
 * @author Integry Systems <http://integry.com>
 */
class SpecField extends EavFieldCommon
{
	/**
	 * Define SpecField database schema
	 */
	public static function defineSchema($className = __CLASS__)
	{
		$schema = parent::defineSchema($className);
		$schema->registerField(new ARForeignKeyField("categoryID", "Category", "ID", "Category", ARInteger::instance()));
	}

	public function getOwnerClass()
	{
		return 'Product';
	}

	public function getStringValueClass()
	{
		return 'SpecificationStringValue';
	}

	public function getNumericValueClass()
	{
		return 'SpecificationNumericValue';
	}

	public function getDateValueClass()
	{
		return 'SpecificationDateValue';
	}

	public function getSelectValueClass()
	{
		return 'SpecificationItem';
	}

	public function getMultiSelectValueClass()
	{
		return 'MultiValueSpecificationItem';
	}

	public function getFieldIDColumnName()
	{
		return 'specFieldID';
	}

	public function getObjectIDColumnName()
	{
		return 'productID';
	}

	protected function getParentCondition()
	{
		return new EqualsCond(new ARFieldHandle(get_class($this), 'categoryID'), $this->category->get()->getID());
	}

	/*####################  Static method implementations ####################*/

	/**
	 * Get instance SpecField record by id
	 *
	 * @param mixred $recordID Id
	 * @param bool $loadRecordData If true load data
	 * @param bool $loadReferencedRecords If true load references. And $loadRecordData is true load a data also
	 *
	 * @return  SpecField
	 */
	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false)
	{
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}

	/**
	 * Get a new SpecField instance
	 *
	 * @param Category $category Category instance
	 * @param int $dataType Data type code (ex: self::DATATYPE_TEXT)
	 * @param int $type Field type code (ex: self::TYPE_TEXT_SIMPLE)
	 *
	 * @return  SpecField
	 */
	public static function getNewInstance(Category $category, $dataType = false, $type = false)
	{
		$specField = parent::getNewInstance(__CLASS__, $dataType, $type);
		$specField->category->set($category);

		return $specField;
	}

	/*####################  Value retrieval and manipulation ####################*/

	/**
	 * Adds a "choice" value to this field
	 *
	 * @param SpecFieldValue $value
	 *
	 * @todo calculate value position if needed
	 */
	public function addValue(SpecFieldValue $value)
	{
		$value->specField->set($this);
		$value->save();
	}

	/*####################  Get related objects ####################*/

	/**
	 * Loads a set of spec field records in current category
	 *
	 * @return array
	 */
	public function getValuesList()
	{
		return SpecFieldValue::getRecordSetArray($this->getID());
	}

	/**
	 * Loads a set of spec field records in current category
	 *
	 * @return ARSet
	 */
	public function getValuesSet()
	{
		return SpecFieldValue::getRecordSet($this->getID());
	}
}

?>