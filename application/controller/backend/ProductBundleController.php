<?php

ClassLoader::import("application.controller.backend.abstract.ProductListControllerCommon");
ClassLoader::import("application.model.product.Product");
ClassLoader::import("application.model.product.ProductBundle");

/**
 * Product bundles
 *
 * @package application.controller.backend
 * @author Integry Systems
 * @role product
 */
class ProductBundleController extends ProductListControllerCommon
{
	public function index()
	{
		$productID = (int)$this->request->get('id');
		$product = Product::getInstanceByID($productID, ActiveRecord::LOAD_DATA);

		$response = new ActionResponse();
		$response->set('ownerID', $productID);
		$response->set('categoryID', $product->category->get()->getID());
		$response->set('items', ProductBundle::getBundledProductArray($product));

		$currency = $this->application->getDefaultCurrency();
		$response->set('total', $currency->getFormattedPrice(ProductBundle::getTotalBundlePrice($product, $currency)));

		return $response;
	}

	protected function getOwnerClassName()
	{
		return 'Product';
	}

	protected function getGroupClassName()
	{
		return null;
	}

	/**
	 * @role update
	 */
	public function create()
	{
		return parent::create();
	}

	/**
	 * @role update
	 */
	public function update()
	{
		return parent::update();
	}

	/**
	 * @role update
	 */
	public function delete()
	{
		return parent::delete();
	}

	/**
	 * @role update
	 */
	public function sort()
	{
		return parent::sort();
	}

	public function edit()
	{
		return parent::edit();
	}
}

?>