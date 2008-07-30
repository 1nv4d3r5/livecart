<?php

ClassLoader::import('application.controller.FrontendController');
ClassLoader::import('application.model.product.Manufacturer');
ClassLoader::import('application.model.product.ProductFilter');
ClassLoader::import('application.model.category.Category');

/**
 * Manufacturer list
 *
 * @author Integry Systems
 * @package application.controller
 */
class ManufacturersController extends FrontendController
{
	public function index()
	{
		// get filter to select manufacturers of active products only
		$rootCat = Category::getRootNode();
		$f = new ARSelectFilter();
		$productFilter = new ProductFilter($rootCat, $f);

		$ids = $counts = array();
		foreach (ActiveRecordModel::getDataBySQL('SELECT DISTINCT(manufacturerID), COUNT(*) AS cnt FROM Product ' . $f->createString() . ' GROUP BY manufacturerID') as $row)
		{
			$ids[] = $row['manufacturerID'];
			$counts[$row['manufacturerID']] = $row['cnt'];
		}

		$f = new ARSelectFilter(new InCond(new ARFieldHandle('Manufacturer', 'ID'), $ids));
		$f->setOrder(new ARFieldHandle('Manufacturer', 'name'));
		$manufacturers = ActiveRecordModel::getRecordSetArray('Manufacturer', $f);
		ActiveRecordModel::addArrayToEavQueue('Manufacturer', $manufacturers);

		foreach ($manufacturers as &$manufacturer)
		{
			$manufacturer['filter'] = new ManufacturerFilter($manufacturer['ID'], $manufacturer['name']);
		}

		$this->addBreadCrumb($this->translate('_manufacturers'), '');

		$response = new ActionResponse();
		$response->setReference('manufacturers', $manufacturers);
		$response->set('counts', $counts);
		$response->set('rootCat', $rootCat->toArray());
		return $response;
	}
}