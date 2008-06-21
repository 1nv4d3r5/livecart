<?php

ClassLoader::import('application.controller.backend.abstract.ActiveGridController');
ClassLoader::import('application.model.category.Category');
ClassLoader::import('application.model.filter.FilterGroup');
ClassLoader::import('application.model.product.Product');
ClassLoader::import('application.model.product.ProductSpecification');
ClassLoader::import('application.helper.ActiveGrid');
ClassLoader::import('application.helper.massAction.MassActionInterface');

/**
 * Controller for handling product based actions performed by store administrators
 *
 * @package application.controller.backend
 * @author Integry Systems
 * @role product
 */
class ProductController extends ActiveGridController implements MassActionInterface
{
	public function index()
	{
		ClassLoader::import('application.LiveCartRenderer');

		$category = Category::getInstanceByID($this->request->get("id"), Category::LOAD_DATA);

		$response = new ActionResponse();
		$response->set('categoryID', $category->getID());
		$response->set('currency', $this->application->getDefaultCurrency()->getID());
		$response->set('themes', array_merge(array(''), LiveCartRenderer::getThemeList()));

		$this->setGridResponse($response);

		$path = $this->getCategoryPathArray($category);
		$response->set('path', $path);

		return $response;
	}

	public function changeColumns()
	{
		parent::changeColumns();

		return new ActionRedirectResponse('backend.product', 'index', array('id' => $this->request->get('id')));
	}

	protected function getClassName()
	{
		return 'Product';
	}

	protected function getCSVFileName()
	{
		return 'products.csv';
	}

	protected function getRequestColumns()
	{
		return $this->getDisplayedColumns(Category::getInstanceByID(substr($this->request->get("id"), 9), Category::LOAD_DATA));
	}

	protected function getAvailableRequestColumns()
	{
		return $this->getAvailableColumns(Category::getInstanceByID(substr($this->request->get("id"), 9), Category::LOAD_DATA));
	}

	protected function getReferencedData()
	{
		return array('Category', 'Manufacturer', 'DefaultImage' => 'ProductImage');
	}

	protected function getColumnValue($product, $class, $field)
	{
		if ($class == 'hiddenType')
		{
			return $product['type'];
		}

		$value = '';

		if ('Product' == $class)
		{
			$value = isset($product[$field . '_lang']) ?
						$product[$field . '_lang'] : (isset($product[$field]) ? $product[$field] : '');
		}
		else if ('ProductPrice' == $class)
		{
			$currency = $this->application->getDefaultCurrency()->getID();
			$value = isset($product['price_' . $currency]) ? $product['price_' . $currency] : 0;
		}
		else if ('specField' == $class)
		{
			$value = isset($product['attributes'][$field]['value_lang']) ? $product['attributes'][$field]['value_lang'] : '';
		}
		else if ('ProductImage' == $class)
		{
			if (!empty($product['DefaultImage']['urls']))
			{
				$value = $product['DefaultImage']['urls'][1];
			}
		}
		else
		{
			$value = parent::getColumnValue($product, $class, $field);
		}

		return $value;
	}

	protected function getSelectFilter()
	{
		$id = $this->request->get("id");
		$id = is_numeric($id) ? $id : substr($this->request->get("id"), 9);
		$category = Category::getInstanceByID($id, Category::LOAD_DATA);

		$filter = new ARSelectFilter();

		$cond = new EqualsOrMoreCond(new ARFieldHandle('Category', 'lft'), $category->lft->get());
		$cond->addAND(new EqualsOrLessCond(new ARFieldHandle('Category', 'rgt'), $category->rgt->get()));
		$filter->setCondition($cond);

		$filter->joinTable('ProductPrice', 'Product', 'productID AND (ProductPrice.currencyID = "' . $this->application->getDefaultCurrencyCode() . '")', 'ID');

		return $filter;
	}

	protected function processDataArray($productArray, $displayedColumns)
	{
		// load specification data
		foreach ($displayedColumns as $column => $type)
		{
			if($column == 'hiddenType') continue;

			list($class, $field) = explode('.', $column, 2);
			if ('specField' == $class)
			{
				ProductSpecification::loadSpecificationForRecordSetArray($productArray);
				break;
			}
		}

		// load price data
		ProductPrice::loadPricesForRecordSetArray($productArray);

		return $productArray;
	}

	/**
	 * @role mass
	 */
	public function processMass()
	{
		$filter = $this->getSelectFilter();

		$act = $this->request->get('act');
		$field = array_pop(explode('_', $act, 2));

		if ('move' == $act)
		{
			new ActiveGrid($this->application, $filter, $this->getClassName());

			$cat = Category::getInstanceById($this->request->get('categoryID'), Category::LOAD_DATA);
			$update = new ARUpdateFilter();

			$update->setCondition($filter->getCondition());
			$update->addModifier('Product.categoryID', $cat->getID());
			$update->joinTable('ProductPrice', 'Product', 'productID AND (ProductPrice.currencyID = "' . $this->application->getDefaultCurrencyCode() . '")', 'ID');

			ActiveRecord::beginTransaction();
			ActiveRecord::updateRecordSet('Product', $update, Product::LOAD_REFERENCES);
			Category::recalculateProductsCount();
			ActiveRecord::commit();

			return new JSONResponse(array('act' => $this->request->get('act')), 'success', $this->translate('_move_succeeded'));
		}

		// remove design themes
		if (('theme' == $act) && !$this->request->get('theme'))
		{
			ClassLoader::import('application.model.presentation.ProductPresentation');
			ActiveRecord::deleteRecordSet('ProductPresentation', new ARDeleteFilter($filter->getCondition()), null, array('Product', 'Category'));

			return new JSONResponse(array('act' => $this->request->get('act')), 'success', $this->translate('_themes_removed'));
		}

		$params = array();
		if ('manufacturer' == $act)
		{
			$params['manufacturer'] = Manufacturer::getInstanceByName($this->request->get('manufacturer'));
		}
		else if ('price' == $act || 'inc_price' == $act)
		{
			$params['baseCurrency'] = $this->application->getDefaultCurrencyCode();
			$params['price'] = $this->request->get($act);
			$params['currencies'] = $this->application->getCurrencySet();
		}
		else if ('addRelated' == $act)
		{
			$params['relatedProduct'] = Product::getInstanceBySKU($this->request->get('related'));
			if (!$params['relatedProduct'])
			{
				return new JSONResponse(0);
			}
		}
		else if ('theme' == $act)
		{
			ClassLoader::import('application.model.presentation.ProductPresentation');
			$params['theme'] = $this->request->get('theme');
		}

		$response = parent::processMass($params);

		if ('delete' == $act)
		{
			Category::recalculateProductsCount();
		}

		return $response;
	}

	protected function getMassActionProcessor()
	{
		 ClassLoader::import('application.helper.massAction.ProductMassActionProcessor');
		 return 'ProductMassActionProcessor';
	}

	protected function getMassCompletionMessage()
	{
		return $this->translate('_mass_action_succeed');
	}

	protected function getMassValidator()
	{
		$validator = parent::getMassValidator();

		$validator->addFilter('set_price', new NumericFilter(''));
		$validator->addFilter('set_stock', new NumericFilter(''));
		$validator->addFilter('inc_price', new NumericFilter(''));
		$validator->addFilter('inc_stock', new NumericFilter(''));
		$validator->addFilter('set_minimumQuantity', new NumericFilter(''));
		$validator->addFilter('set_shippingSurchargeAmount', new NumericFilter(''));

		return $validator;
	}

	public function getAvailableColumns(Category $category, $specField = false)
	{
		$availableColumns = parent::getAvailableColumns();

		// specField columns
		if ($specField)
		{
			$fields = $category->getSpecificationFieldSet(Category::INCLUDE_PARENT);
			foreach ($fields as $field)
			{
				if (!$field->isMultiValue->get())
				{
					$fieldArray = $field->toArray();
					$availableColumns['specField.' . $field->getID()] = array
						(
							'name' => $fieldArray['name_lang'],
							'type' => $field->isSimpleNumbers() ? 'numeric' : 'text'
						);
				}
			}
		}

		$availableColumns['ProductImage.url'] = array
			(
				'name' => $this->translate('ProductImage.url'),
				'type' => 'text'
			);

		unset($availableColumns['Product.voteSum']);
		unset($availableColumns['Product.voteCount']);
		unset($availableColumns['Product.rating']);
		unset($availableColumns['Product.salesRank']);

		return $availableColumns;
	}

	protected function getCustomColumns()
	{
		$availableColumns['Manufacturer.name'] = 'text';
		$availableColumns['ProductPrice.price'] = 'numeric';
		$availableColumns['hiddenType'] = 'numeric';

		return $availableColumns;
	}

	protected function getDisplayedColumns(Category $category)
	{
		// product ID is always passed as the first column
		return parent::getDisplayedColumns($category, array('hiddenType' => 'numeric'));
	}

	protected function getDefaultColumns()
	{
		return array('Product.ID', 'hiddenType','Product.sku', 'Product.name', 'Manufacturer.name', 'ProductPrice.price', 'Product.stockCount', 'Product.isEnabled');
	}

	public function autoComplete()
	{
	  	$f = new ARSelectFilter();
		$f->setLimit(20);

		$resp = array();

		$field = $this->request->get('field');

		if (in_array($field, array('sku', 'URL', 'keywords')))
		{
		  	$c = new LikeCond(new ARFieldHandle('Product', $field), $this->request->get($field) . '%');
		  	$f->setCondition($c);

			$f->setOrder(new ARFieldHandle('Product', $field), 'ASC');

		  	$query = new ARSelectQueryBuilder();
		  	$query->setFilter($f);
		  	$query->includeTable('Product');
		  	$query->addField('DISTINCT(Product.' . $field . ')');

		  	$results = ActiveRecordModel::getDataBySQL($query->createString());

		  	foreach ($results as $value)
		  	{
				$resp[] = $value[$field];
			}
		}

		else if ('name' == $field)
		{
		  	$c = new LikeCond(new ARFieldHandle('Product', $field), '%:"' . $this->request->get($field) . '%');
		  	$f->setCondition($c);

			$locale = $this->locale->getLocaleCode();
			$langCond = new LikeCond(Product::getLangSearchHandle(new ARFieldHandle('Product', 'name'), $locale), $this->request->get($field) . '%');
			$c->addAND($langCond);

		  	$f->setOrder(Product::getLangSearchHandle(new ARFieldHandle('Product', 'name'), $locale), 'ASC');

		  	$results = ActiveRecordModel::getRecordSet('Product', $f);

		  	foreach ($results as $value)
		  	{
				$resp[$value->getValueByLang('name', $locale, Product::NO_DEFAULT_VALUE)] = true;
			}

			$resp = array_keys($resp);
		}

		else if ('specField_' == substr($field, 0, 10))
		{
			list($foo, $id) = explode('_', $field);

			$handle = new ARFieldHandle('SpecificationStringValue', 'value');
			$locale = $this->locale->getLocaleCode();
			$searchHandle = MultiLingualObject::getLangSearchHandle($handle, $locale);

		  	$f->setCondition(new EqualsCond(new ARFieldHandle('SpecificationStringValue', 'specFieldID'), $id));
			$f->mergeCondition(new LikeCond($handle, '%:"' . $this->request->get($field) . '%'));
			$f->mergeCondition(new LikeCond($searchHandle, $this->request->get($field) . '%'));

		  	$f->setOrder($searchHandle, 'ASC');

		  	$results = ActiveRecordModel::getRecordSet('SpecificationStringValue', $f);

		  	foreach ($results as $value)
		  	{
				$resp[$value->getValueByLang('value', $locale, Product::NO_DEFAULT_VALUE)] = true;
			}

			$resp = array_keys($resp);
		}

		return new AutoCompleteResponse($resp);
	}

	/**
	 * Displays main product information form
	 *
	 * @role create
	 *
	 * @return ActionResponse
	 */
	public function add()
	{
		$category = Category::getInstanceByID($this->request->get("id"), ActiveRecordModel::LOAD_DATA);

		$response = $this->productForm(Product::getNewInstance($category, ''));
		if ($this->config->get('AUTO_GENERATE_SKU'))
		{
			$response->get('productForm')->set('autosku', true);
		}

		$response->get('productForm')->set('isEnabled', true);

		return $response;
	}

	/**
	 * @role create
	 */
	public function create()
	{
		$product = Product::getNewInstance(Category::getInstanceByID($this->request->get('categoryID')), $this->translate('_new_product'));

		$response = $this->save($product);

		if ($response instanceOf ActionResponse)
		{
			$response->get('productForm')->clearData();
			$response->set('id', $product->getID());
			return $response;
		}
		else
		{
			return $response;
		}
	}

	/**
	 * @role update
	 */
	public function update()
	{
	  	$product = Product::getInstanceByID($this->request->get('id'), ActiveRecordModel::LOAD_DATA);
	  	$product->loadPricing();
	  	$product->loadSpecification();

	  	return $this->save($product);
	}

	public function basicData()
	{
		ClassLoader::import('application.LiveCartRenderer');
		ClassLoader::import('application.model.presentation.ProductPresentation');

		$product = Product::getInstanceById($this->request->get('id'), ActiveRecord::LOAD_DATA, array('DefaultImage' => 'ProductImage', 'Manufacturer', 'Category'));
		$product->loadSpecification();

		$response = $this->productForm($product);
		$response->set('counters', $this->countTabsItems()->getData());
		$response->set('themes', array_merge(array(''), LiveCartRenderer::getThemeList()));

		$set = $product->getRelatedRecordSet('ProductPresentation', new ARSelectFilter());
		if ($set->size())
		{
			$response->get('productForm')->set('theme', $set->get(0)->getTheme());
		}

		return $response;
	}

	public function countTabsItems()
	{
	  	ClassLoader::import('application.model.product.*');
	  	$product = Product::getInstanceByID((int)$this->request->get('id'), ActiveRecord::LOAD_DATA);

	  	return new JSONResponse(array(
			'tabProductRelationship' => $product->getRelationships(false)->getTotalRecordCount(),
			'tabProductFiles' => $product->getFiles(false)->getTotalRecordCount(),
			'tabProductImages' => count($product->getImageArray()),
			'tabProductOptions' => $product->getOptions()->getTotalRecordCount(),
		));
	}

	public function info()
	{
		ClassLoader::import("application.helper.getDateFromString");
		ClassLoader::import("application.model.order.OrderedItem");

		$product = Product::getInstanceById($this->request->get('id'), ActiveRecord::LOAD_DATA, array('DefaultImage' => 'ProductImage', 'Manufacturer', 'Category'));

		$thisMonth = date('m');
		$lastMonth = date('Y-m', strtotime(date('m') . '/15 -1 month'));

		$periods = array(

			'_last_1_h' => "-1 hours | now",
			'_last_3_h' => "-3 hours | now",
			'_last_6_h' => "-6 hours | now",
			'_last_12_h' => "-12 hours | now",
			'_last_24_h' => "-24 hours | now",
			'_last_3_d' => "-3 days | now",
			'_this_week' => "w:Monday | now",
			'_last_week' => "w:Monday ~ -1 week | w:Monday",
			'_this_month' => $thisMonth . "/1 | now",
			'_last_month' => $lastMonth . "-1 | " . $lastMonth . "/1",
			'_this_year' => "January 1 | now",
			'_last_year' => "January 1 last year | January 1",
			'_overall' => "now | now"

		);

		$purchaseStats = array();
		$prevCount = 0;
		foreach ($periods as $key => $period)
		{
			list($from, $to) = explode(' | ', $period);

			$cond = new EqualsCond(new ARFieldHandle('OrderedItem', 'productID'), $product->getID());

			if ('now' != $from)
			{
				$cond->addAND(new EqualsOrMoreCond(new ARFieldHandle('CustomerOrder', 'dateCompleted'), getDateFromString($from)));
			}

			if ('now' != $to)
			{
				$cond->addAnd(new EqualsOrLessCond(new ARFieldHandle('CustomerOrder', 'dateCompleted'), getDateFromString($to)));
			}

			$f = new ARSelectFilter($cond);
			$f->mergeCondition(new EqualsCond(new ARFieldHandle('CustomerOrder', 'isFinalized'), true));
			$f->removeFieldList();
			$f->addField('SUM(OrderedItem.count)');

			$query = new ARSelectQueryBuilder();
			$query->setFilter($f);
			$query->includeTable('OrderedItem');
			$query->joinTable('CustomerOrder', 'OrderedItem', 'ID', 'customerOrderID');

			if (($count = array_shift(array_shift(ActiveRecordModel::getDataBySql($query->getPreparedStatement(ActiveRecord::getDBConnection()))))) && ($count > $prevCount || '_overall' == $key))
			{
				$purchaseStats[$key] = $count;
			}

			if ($count > $prevCount)
			{
				$prevCount = $count;
			}
		}

		$response = new ActionResponse();
		$response->set('together', $product->getProductsPurchasedTogether(10));
		$response->set('product', $product->toArray());
		$response->set('purchaseStats', $purchaseStats);
		return $response;
	}

	private function save(Product $product)
	{
		ClassLoader::import('application.model.presentation.ProductPresentation');
		$validator = $this->buildValidator($product);
		if ($validator->isValid())
		{
			$needReload = 0;

			// create new specField values
			if ($this->request->isValueSet('other'))
			{
				$other = $this->request->get('other');
				foreach ($other as $fieldID => $values)
				{
					$field = SpecField::getInstanceByID($fieldID);

					if (is_array($values))
					{
						// multiple select
						foreach ($values as $value)
						{
						  	if ($value)
						  	{
								$fieldValue = SpecFieldValue::getNewInstance($field);
							  	$fieldValue->setValueByLang('value', $this->application->getDefaultLanguageCode(), $value);
							  	$fieldValue->save();

							  	$this->request->set('specItem_' . $fieldValue->getID(), 'on');
								$needReload = 1;
							}
						}
					}
					else
					{
						// single select
						if ('other' == $this->request->get('specField_' . $fieldID))
						{
							$fieldValue = SpecFieldValue::getNewInstance($field);
						  	$fieldValue->setValueByLang('value', $this->application->getDefaultLanguageCode(), $values);
						  	$fieldValue->save();

						  	$this->request->set('specField_' . $fieldID, $fieldValue->getID());
							$needReload = 1;
						}
					}
				}
			}

			$product->loadRequestData($this->request);
			$product->save();

			// presentation
			if ($theme = $this->request->get('theme'))
			{
				$instance = ProductPresentation::getInstance($product);
				$instance->loadRequestData($this->request);
				$instance->save();
			}
			else
			{
				ActiveRecord::deleteByID('ProductPresentation', $product->getID());
			}

			$response = $this->productForm($product);

			$response->setHeader('Cache-Control', 'no-cache, must-revalidate');
			$response->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
			$response->setHeader('Content-type', 'text/javascript');

			return $response;
		}
		else
		{
			// reset validator data (as we won't need to restore the form)
			$validator->restore();

			return new JSONResponse(array('errors' => $validator->getErrorList(), 'failure', $this->translate('_could_not_save_product_information')));
		}
	}

	private function productForm(Product $product)
	{
		$specFields = $product->getSpecificationFieldSet(ActiveRecordModel::LOAD_REFERENCES);
		$specFieldArray = $specFields->toArray();

		// set select values
		$selectors = SpecField::getSelectorValueTypes();
		foreach ($specFields as $key => $field)
		{
		  	if (in_array($field->type->get(), $selectors))
		  	{
				$values = $field->getValuesSet()->toArray();
				$specFieldArray[$key]['values'] = array('' => '');
				foreach ($values as $value)
				{
					$specFieldArray[$key]['values'][$value['ID']] = $value['value_lang'];
				}

				if (!$field->isMultiValue->get())
				{
					$specFieldArray[$key]['values']['other'] = $this->translate('_enter_other');
				}
			}
		}
		// get multi language spec fields
		$multiLingualSpecFields = array();
		foreach ($specFields as $key => $field)
		{
		  	if ($field->isTextField())
		  	{
		  		$multiLingualSpecFields[] = $field->toArray();
			}
		}

		$form = $this->buildForm($product);

		$productFormData = $product->toArray();

		if($product->isLoaded())
		{
			$product->loadSpecification();
			foreach($product->getSpecification()->toArray() as $attr)
			{
				if(in_array($attr['SpecField']['type'], SpecField::getSelectorValueTypes()))
				{
					if(1 == $attr['SpecField']['isMultiValue'])
					{
						foreach($attr['valueIDs'] as $valueID)
						{
							$productFormData['specItem_' . $valueID] = "on";
						}
					}
					else
					{
						$productFormData[$attr['SpecField']['fieldName']] = $attr['ID'];
					}
				}
				else if(in_array($attr['SpecField']['type'], SpecField::getMultilanguageTypes()))
				{
					$productFormData[$attr['SpecField']['fieldName']] = $attr['value'];
					foreach($this->application->getLanguageArray() as $lang)
					{
						if (isset($attr['value_' . $lang]))
						{
							$productFormData[$attr['SpecField']['fieldName'] . '_' . $lang] = $attr['value_' . $lang];
						}
					}
				}
				else
				{
					$productFormData[$attr['SpecField']['fieldName']] = $attr['value'];
				}
			}

			if (isset($productFormData['Manufacturer']['name']))
			{
				$productFormData['manufacturer'] = $productFormData['Manufacturer']['name'];
			}
		}

		$form->setData($productFormData);

		$languages = array();
		foreach ($this->application->getLanguageArray() as $lang)
		{
			$languages[$lang] = $this->locale->info()->getOriginalLanguageName($lang);
		}

		// status values
		$status = array(0 => $this->translate('_disabled'),
						1 => $this->translate('_enabled'),
					  );

		// product types
		$types = array(0 => $this->translate('_tangible'),
					   1 => $this->translate('_intangible'),
					  );

		// default product type
		if (!$product->isLoaded())
		{
			$product->type->set(substr($this->config->get('DEFAULT_PRODUCT_TYPE'), -1));
			$form->set('type', $product->type->get());
		}

		// arrange SpecFields's into groups
		$specFieldsByGroup = array();
		$prevGroupID = -1;

		foreach ($specFieldArray as $field)
		{
			$groupID = isset($field['SpecFieldGroup']['ID']) ? $field['SpecFieldGroup']['ID'] : '';
			if((int)$groupID && $prevGroupID != $groupID)
			{
				$prevGroupID = $groupID;
			}

			$specFieldsByGroup[$groupID][] = $field;
		}

		$response = new ActionResponse();
		$response->set("cat", $product->category->get()->getID());
		$response->set("hideFeedbackMessage", $this->request->get("afterAdding") == 'on');
		$response->set("specFieldList", $specFieldsByGroup);
		$response->set("productForm", $form);
		$response->set("path", $product->category->get()->getPathNodeArray(true));
		$response->set("multiLingualSpecFieldss", $multiLingualSpecFields);
		$response->set("productTypes", $types);
		$response->set("productStatuses", $status);
		$response->set("baseCurrency", $this->application->getDefaultCurrency()->getID());
		$response->set("otherCurrencies", $this->application->getCurrencyArray(LiveCart::EXCLUDE_DEFAULT_CURRENCY));

		$productData = $product->toArray();
		if (empty($productData['ID']))
		{
			$productData['ID'] = 0;
		}
		$response->set("product", $productData);

		return $response;
	}

	/**
	 * @return RequestValidator
	 */
	private function buildValidator(Product $product)
	{
		ClassLoader::import("framework.request.validator.RequestValidator");

		$validator = new RequestValidator("productFormValidator", $this->request);

		$validator->addCheck('name', new IsNotEmptyCheck($this->translate('_err_name_empty')));

		// check if SKU is entered if not autogenerating
		if ($this->request->get('save') && !$product->isExistingRecord() && !$this->request->get('autosku'))
		{
			$validator->addCheck('sku', new IsNotEmptyCheck($this->translate('_err_sku_empty')));
		}

		// check if entered SKU is unique
		if ($this->request->get('sku') && $this->request->get('save') && (!$product->isExistingRecord() || ($this->request->isValueSet('sku') && $product->getFieldValue('sku') != $this->request->get('sku'))))
		{
			ClassLoader::import('application.helper.check.IsUniqueSkuCheck');
			$validator->addCheck('sku', new IsUniqueSkuCheck($this->translate('_err_sku_not_unique'), $product));
		}

		// spec field validator
		$specFields = $product->getSpecificationFieldSet(ActiveRecordModel::LOAD_REFERENCES);

		foreach ($specFields as $key => $field)
		{
			$fieldname = $field->getFormFieldName();

		  	// validate numeric values
			if (SpecField::TYPE_NUMBERS_SIMPLE == $field->type->get())
		  	{
				$validator->addCheck($fieldname, new IsNumericCheck($this->translate('_err_numeric')));
				$validator->addFilter($fieldname, new NumericFilter());
			}

		  	// validate required fields
			if ($field->isRequired->get())
		  	{
				if (!($field->isSelector() && $field->isMultiValue->get()))
				{
					$validator->addCheck($fieldname, new IsNotEmptyCheck($this->translate('_err_specfield_required')));
				}
				else
				{
					ClassLoader::import('application.helper.check.SpecFieldIsValueSelectedCheck');
					$validator->addCheck($fieldname, new SpecFieldIsValueSelectedCheck($this->translate('_err_specfield_multivaluerequired'), $field, $this->request));
				}
			}
		}

		// validate price input in all currencies
		if(!$product->isExistingRecord())
		{
			ClassLoader::import('application.controller.backend.ProductPriceController');
			ProductPriceController::addPricesValidator($validator);
			ProductPriceController::addShippingValidator($validator);
			ProductPriceController::addInventoryValidator($validator);
		}

		return $validator;
	}

	private function buildForm(Product $product)
	{
		ClassLoader::import("framework.request.validator.Form");
		return new Form($this->buildValidator($product));
	}

	/**
	 * Gets path to a current node (including current node)
	 *
	 * Overloads parent method
	 * @return array
	 */
	private function getCategoryPathArray(Category $category)
	{
		$path = array();
		$pathNodes = $category->getPathNodeSet(Category::INCLUDE_ROOT_NODE);
		$defaultLang = $this->application->getDefaultLanguageCode();

		foreach ($pathNodes as $node)
		{
			$path[] = $node->getValueByLang('name', $defaultLang);
		}
		return $path;
	}
}

?>