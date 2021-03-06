<?php

ClassLoader::import('application.model.ActiveRecordModel');

/**
 *
 * @package application.model.product
 * @author Integry Systems <http://integry.com>
 */
class RecurringItem extends ActiveRecordModel
{
	public static function defineSchema()
	{
		$schema = self::getSchemaInstance(__CLASS__);
		$schema->setName(__CLASS__);
		$schema->registerField(new ARPrimaryKeyField('ID', ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField('recurringID', 'RecurringProductPeriod', 'ID', null, ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField('orderedItemID', 'OrderedItem', 'ID', 'OrderedItem', ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField('lastInvoiceID', 'CustomerOrder', 'ID', 'CustomerOrder', ARInteger::instance()));

		$schema->registerField(new ARField('setupPrice', ARInteger::instance()));
		$schema->registerField(new ARField('periodPrice', ARInteger::instance()));
		$schema->registerField(new ARField('rebillCount', ARInteger::instance()));
		$schema->registerField(new ARField('processedRebillCount', ARInteger::instance()));
		$schema->registerField(new ARField('periodType', ARInteger::instance()));
		$schema->registerField(new ARField('periodLength', ARInteger::instance()));

	}

	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		return parent::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}

	public static function getRecordSetArray(ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		return parent::getRecordSetArray(__CLASS__, $filter, $loadReferencedRecords);
	}

	public static function getInstanceByOrderedItem(OrderedItem $item, $load=false)
	{
		$recurringItems = self::getRecordSetByOrderedItem($item, $load);
		if ($recurringItems->size() >= 1)
		{
			return $recurringItems->shift();
		}
		else
		{
			return null;
		}
	}

	public static function getNewInstance(RecurringProductPeriod $recurringProductPeriod,
		OrderedItem $item, $setupPrice = null, $periodPrice = null, $rebillCount = null)
	{
		$instance = ActiveRecord::getNewInstance(__CLASS__);
		$instance->orderedItem->set($item);
		$instance->setRecurringProductPeriod($recurringProductPeriod); // call after orderedItem is added!
		$instance->periodLength->set($recurringProductPeriod->periodLength->get());
		$instance->periodType->set($recurringProductPeriod->periodType->get());
		if ($setupPrice !== null)
		{
			$instance->setupPrice->set($setupPrice);
		}
		
		if ($periodPrice !== null)
		{
			$instance->periodPrice->set($periodPrice);
		}

		if ($rebillCount !== null)
		{
			$instance->rebillCount->set($rebillCount);
		}

		return $instance;
	}

	public static function getRecordSetByOrderedItem(OrderedItem $item, $load = false)
	{
		return self::getRecordSet(self::getARSelectFilterByOrderedItem($item), $load);
	}

	public static function getRecordSetArrayByOrderedItem(OrderedItem $item, $load = false)
	{
		return self::getRecordSetArray(self::getARSelectFilterByOrderedItem($item), $load);
	}

	private static function getARSelectFilterByOrderedItem(OrderedItem $item)
	{
		$filter = new ARSelectFilter();
		$filter->setCondition(new EqualsCond(new ARFieldHandle(__CLASS__, 'orderedItemID'), $item->getID()));
		return $filter;
	}

	public function setRecurringProductPeriod(RecurringProductPeriod $recurringProductPeriod)
	{
		$order = $this->orderedItem->get()->customerOrder->get();
		$order->load();
		$currencyID = $order->currencyID->get()->getID();
		$this->recurring->set($recurringProductPeriod);
		if ($recurringProductPeriod->isLoaded() == false)
		{
			$recurringProductPeriod->load();
		}
		$this->rebillCount->set($recurringProductPeriod->rebillCount->get());
		$rppa = $recurringProductPeriod->toArray($currencyID);
		if (array_key_exists('ProductPrice_setup', $rppa) && @isset($rppa['ProductPrice_setup'][$currencyID]['price']))
		{
			$this->setupPrice->set($rppa['ProductPrice_setup'][$currencyID]['price']);
		}
		if (array_key_exists('ProductPrice_period', $rppa) && @isset($rppa['ProductPrice_period'][$currencyID]['price']))
		{
			$this->periodPrice->set($rppa['ProductPrice_period'][$currencyID]['price']);
		}
		if (array_key_exists('periodType', $rppa) )
		{
			$this->periodType->set($rppa['periodType']);
		}
		if (array_key_exists('periodLength', $rppa) )
		{
			$this->periodLength->set($rppa['periodLength']);
		}
		if (array_key_exists('rebillCount', $rppa) )
		{
			$this->rebillCount->set($rppa['rebillCount']);
		}
	}

	public static function batchIncreaseProcessedRebillCount($ids)
	{
		if (count($ids) == 0)
		{
			return false;
		}
		ActiveRecord::executeUpdate('UPDATE '.__CLASS__. ' SET 
			processedRebillCount = IF(processedRebillCount IS NULL, 1, processedRebillCount+1)
		WHERE
			ID IN('.implode(',', $ids).')');
			
		//ActiveRecordModel::ClearPool();
		
		return true;
	}

	public function saveLastInvoice(CustomerOrder $order)
	{
		$this->lastInvoiceID->set($order);
		$this->save();
	}

	public function toArray()
	{
		$array = parent::toArray();
		$currencyID = $array['OrderedItem']['CustomerOrder']['currencyID'];
		$currency = Currency::getInstanceByID($currencyID);
		$array['ProductPrice_setup']['formated_price'][$currencyID] = $currency->getFormattedPrice($array['setupPrice']);
		$array['ProductPrice_period']['formated_price'][$currencyID] = $currency->getFormattedPrice($array['periodPrice']);
		
		return $array;
	}
}

?>