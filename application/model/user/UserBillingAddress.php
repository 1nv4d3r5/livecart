<?php

ClassLoader::import('application.model.user.UserAddressType');

/**
 * Customer billing address
 *
 * @package application.model.category
 */
class UserBillingAddress extends UserAddressType
{
    /**
     * Define database schema
     */
	public static function defineSchema($className = __CLASS__)
	{
		parent::defineSchema($className);
	}
    
    public static function getNewInstance(User $user, UserAddress $userAddress)
    {
        return parent::getNewInstance(__CLASS__, $user, $userAddress);
    }    
    
    public function save()
    {
        parent::save();
        
        $user = $this->user->get();
        $user->load();        
        if (!$user->defaultBillingAddress->get())
        {
            $user->defaultBillingAddress->set($this);
            $user->save();
        }
    }    
}
	
?>