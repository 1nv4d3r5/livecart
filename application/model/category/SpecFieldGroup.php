<?php

ClassLoader::import("application.model.system.MultilingualObject");
ClassLoader::import("application.model.category.Category");

/**
 * Specification field class
 *
 * @package application.model.category
 */
class SpecFieldGroup extends MultilingualObject
{
	/**
	 * Define SpecFieldGroup database schema
	 */
    public static function defineSchema()
	{
		$schema = self::getSchemaInstance(__CLASS__);
		$schema->setName(__CLASS__);
		
		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField("categoryID", "Category", "ID", "Category", ARInteger::instance()));
		$schema->registerField(new ARField("name", ARArray::instance()));
		$schema->registerField(new ARField("position", ARInteger::instance(2)));
	}
	
	/**
	 * Loads a set of active record instances of SpecFieldGroup by using a filter
	 *
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 * @return ARSet
	 */
	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		return parent::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}

	/**
	 * Get specification group item instance
	 *
	 * @param int|array $recordID Record id
	 * @param bool $loadRecordData If true loads record's structure and data
	 * @param bool $loadReferencedRecords If true loads all referenced records
	 * @return SpecFieldGroup
	 */
	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false)
	{
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}

	/**
	 * Get a set of SpecField records
	 *
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords Load referenced tables data
	 * @return array
	 */
	public static function getRecordSetArray(ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		return parent::getRecordSetArray(__CLASS__, $filter, $loadReferencedRecords);
	}
	
	/**
	 * Loads a set of spec field records for a group.
	 *
	 * @param boolean $includeParentFields 
	 * @param boolean $$loadReferencedRecords 
	 * @return ARSet
	 */
	public function getSpecificationFieldSet($includeParentFields = false, $loadReferencedRecords = false)
	{
		ClassLoader::import("application.model.category.SpecField");
		return SpecField::getRecordSet($this->getSpecificationFilter($includeParentFields), $loadReferencedRecords);
	}

	/**
	 * Loads a set of spec field records for a group as array.
	 *
	 * @param boolean $includeParentFields 
	 * @param boolean $$loadReferencedRecords 
	 * @return array
	 */
	public function getSpecificationFieldArray($includeParentFields = false, $loadReferencedRecords = false)
	{
		ClassLoader::import("application.model.category.SpecField");
		return SpecField::getRecordSetArray($this->getSpecificationFilter($includeParentFields), $loadReferencedRecords);
	}

	/**
	 * Get new SpecFieldGroup active record instance
	 *
	 * @return SpecFieldGroup
	 */
	public static function getNewInstance()
	{
		return parent::getNewInstance(__CLASS__);
	}
	
	/**
	 * Crates a select filter for specification fields related to group
	 *
	 * @param bool $includeParentFields
	 * @return ARSelectFilter
	 */
	private function getSpecificationFilter($includeParentFields)
	{
		$filter = new ARSelectFilter();
		$filter->setOrder(new ARFieldHandle("SpecField", "position"));
		$filter->setCondition(new EqualsCond(new ARFieldHandle("SpecField", "specFieldGroupID"), $this->getID()));

		return $filter;
	}

	/**
	 * Delete spec field group from database
	 * 
	 * @param integer $id Spec field id
	 * @return boolean status
	 */
	public static function deleteById($id)
	{
	    return parent::deleteByID(__CLASS__, (int)$id);
	}

	/**
	 * Validate submitted specification group
	 *
	 * @param unknown_type $values
	 * @param unknown_type $config
	 * @return unknown
	 */
    public static function validate($values = array(), $languageCodes)
    {
        $errors = array();
        
        if(!isset($values['name'][$languageCodes[0]]) || $values['name'][$languageCodes[0]] == '')
        {
            $errors['name'] = '_error_you_should_provide_default_group_name';
        }
        
        return $errors;
    }
}
?>