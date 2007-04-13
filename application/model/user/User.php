<?php

ClassLoader::import("application.model.ActiveRecordModel");
ClassLoader::import("application.model.user.UserBillingAddress");
ClassLoader::import("application.model.user.UserShippingAddress");

/**
 * Store user base class (including frontend and backend)
 *
 * @package application.model.user
 * @author Integry Systems
 *
 */
class User extends ActiveRecordModel
{
	/**
	 * ID of anonymous user that is not authorized
	 *
	 */
	const ANONYMOUS_USER_ID = 0;

	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName("User");

		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField("defaultShippingAddressID", "defaultShippingAddress", "ID", 'UserShippingAddress', ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField("defaultBillingAddressID", "defaultBillingAddress", "ID", 'UserBillingAddress', ARInteger::instance()));

		$schema->registerField(new ARField("email", ARVarchar::instance(60)));
		$schema->registerField(new ARField("password", ARVarchar::instance(16)));
		$schema->registerField(new ARField("firstName", ARVarchar::instance(60)));
		$schema->registerField(new ARField("lastName", ARVarchar::instance(60)));
		$schema->registerField(new ARField("dateCreated", ARDateTime::instance()));
		$schema->registerField(new ARField("isEnabled", ARBool::instance()));		
	}

    public static function getCurrentUser()
    {
        $user = Session::getInstance()->getObject('User');
    
        if (!$user)
        {
            $user = self::getNewInstance();
            $user->setID(self::ANONYMOUS_USER_ID);
        }
        
        return $user;
    }
    
    public function setAsCurrentUser()
    {
		Session::getInstance()->setValue('User', $this);
	}

    public function save()
    {
        // auto-generate password if not set
        if (!$this->password->get())
        {
            $this->password->set(md5($this->getAutoGeneratedPassword()));    
        }
        
        return parent::save();
    }

    public static function getNewInstance()
    {
        $instance = parent::getNewInstance(__CLASS__);    
        
        return $instance;
    }

	/**
	 * Gets an instance of user by using login information
	 *
	 * @param string $email
	 * @param string $password
	 * @return mixed User instance or null if user is not found
	 */
	public static function getInstanceByLogin($email, $password)
	{
		$filter = new ARSelectFilter();
		$loginCond = new EqualsCond("User.email", $email);
		$loginCond->addAND(new EqualsCond("User.password", md5($password)));
		$filter->setCondition(new EqualsCond("User.email", $loginCond));
		$recordSet = User::getRecordSet($filter);

		if ($recordSet->size() == 0)
		{
			return null;
		}
		else
		{
			return $recordSet->get(0);
		}
	}

	/**
	 * Checks if a user can access a particular controller/action identified by a role string (handle)
	 *
	 * Role string represents hierarchial role, that grants access to a given node:
	 * rootNode.someNode.lastNode
	 *
	 * (i.e. admin.store.catalog) this role string identifies that user has access to
	 * all actions/controller that are mapped to this string (admin.store.catalog.*)
	 *
	 * @param string $roleName
	 * @return bool
	 */
	public function hasAccess($roleName)
	{
		// disable all login protected content from deactivated users
		if ($roleName && !$this->isEnabled->get())
		{
			return false;	
		}
		
		if ('login' == $roleName)
		{
			return $this->getID > 0;	
		}
		
		return true;
		
		// pseudo check
		if ($this->getID() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

    public function isLoggedIn()
    {
        return ($this->getID() != self::ANONYMOUS_USER_ID);
    }

	/**
	 * Gets a user related config value (persisted)
	 *
	 * @param string $varName
	 * @return mixed
	 */
	public function getConfigValue($varName)
	{
		ClassLoader::import("application.model.user.UserConfigValue");
		$filter = new ARSelectFilter();
		$filter->setCondition(new EqualsCond("UserConfigValue.name", $varName));

		$recordSet = $this->getRelatedRecordSet("UserConfigValue", $filter);
		if ($recordSet->size() == 0)
		{
			return null;
		}
		else
		{
			return $recordSet->get(0);
		}
	}

	/**
	 * Sets a user related config value (persisted)
	 *
	 * @param string $varName
	 * @param mixed $value
	 */
	public function setConfigValue($varName, $value)
	{
		$configVariable = $this->getConfigValue($varName);
		if ($configVariable == null)
		{
			// creating new var
			$configVariable = ActiveRecord::getNewInstance("UserConfigValue");
			$configVariable->user->set($this);
			$configVariable->name->set($varName);
			$configVariable->value->set($value);
		}
		else
		{
			// updating value
			$configVariable->value->set($value);
			$configVariable->save();
		}
	}

	/**
	 * Gets a language code from a config that is active now
	 *
	 * @return Language
	 */
	public function getActiveLang()
	{
		ClassLoader::import("application.model.Language");
		return Language::getInstanceByID("en", ActiveRecord::LOAD_DATA);
	}

	/**
	 * Gets user default (native) language
	 *
	 * @return Language
	 */
	public function getDefaultLang()
	{
		ClassLoader::import("application.model.Language");
		return ActiveRecord::getInstanceByID("Language", "lt", ActiveRecord::LOAD_DATA);
	}

	/**
	 * Sets active language that is used to fill multilingual store data
	 *
	 * @param Language $lang
	 */
	public function setActiveLang(Language $lang)
	{
		$this->setConfigValue("active_lang", $lang->getID());
	}

	/**
	 * Sets default (native) user language
	 *
	 * @param Language $lang
	 */
	public function setDefaultLang(Language $lang)
	{
		$this->setConfigValue("default_lang", $lang->getID());
	}

    public function getName()
    {
        return $this->firstName->get() . ' ' . $this->lastName->get();
    }

	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false)
	{
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}

	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords)
	{
		return ActiveRecord::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}

    private function getAutoGeneratedPassword($length = 8)
    {        
        $chars = array();
        for ($k = 1; $k <= $length; $k++)
        {
            $chars[] = chr(rand(97, 122));
        }        
        
        return implode('', $chars);
    }
}

?>