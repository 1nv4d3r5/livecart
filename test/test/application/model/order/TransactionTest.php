<?php

require_once dirname(__FILE__) . '/OrderTestCommon.php';

/**
 *	Test Transaction model
 *
 *  @author Integry Systems
 *  @package test.model.order
 */
class TransactionTest extends OrderTestCommon
{
	function testTransactionCurrencyConverting()
	{
		$eur = Currency::getNewInstance('EUR');
		$eur->rate->set('3.4528');
		$eur->save();

		$this->products[0]->setPrice($this->usd, '9.99');
		$this->order->addProduct($this->products[0], 1);
		$this->order->save();
		$this->order->changeCurrency($this->usd);

		//$this->order->finalize();

		ActiveRecord::clearPool();
		$order = CustomerOrder::getInstanceByID($this->order->getID(), true);
		$order->loadAll();
		$details = new LiveCartTransaction($order, $eur);

		ActiveRecord::clearPool();
		$order = CustomerOrder::getInstanceByID($this->order->getID(), true);
		$order->loadAll();

		$this->assertEquals($details->amount->get(), '2.89');

		$result = new TransactionResult();
		$result->amount->set($details->amount->get());
		$result->currency->set($details->currency->get());

		$transaction = Transaction::getNewInstance($order, $result);
		$transaction->type->set(Transaction::TYPE_SALE);

		$this->assertEquals($transaction->amount->get(), '9.99');
		$this->assertEquals($transaction->realAmount->get(), '2.89');

		$transaction->save();

		$this->assertFalse((bool)$order->isFinalized->get());
		$order->finalize();
		$this->assertTrue((bool)$order->isFinalized->get());

		$this->assertEquals($order->getPaidAmount(), '9.99');
		$this->assertEquals($order->totalAmount->get(), '9.99');

		$this->assertTrue((bool)$order->isPaid->get());
	}

}