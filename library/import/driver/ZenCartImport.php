<?php

include_once dirname(__file__) . '/OsCommerceImport.php';

class ZenCartImport extends OsCommerceImport
{
	public function getName()
	{
		return 'Zen Cart';
	}

	public function isPathValid()
	{
		// no path provided - won't be able to import images
		if (!$this->path)
		{
			return true;
		}	
		
		if (!parent::isPathValid())
		{
			return false;
		}
		
		return file_exists($this->path . '/admin/coupon_admin.php');
	}
	
	public function getNextProduct()
	{		
		if (!$product = parent::getNextProduct())
		{
			return null;
		}

		$data = $product->rawData;
		
		// set Zen Cart specific fields
		foreach ($this->languages as $code)
		{
			$product->setValueByLang('shortDescription', $code, $data['shortDescr_' . $code]);
			
			if (!empty($data['keywords_' . $code]))
			{
				$product->keywords->set($data['keywords_' . $code]);
			}
		}
		
		return $product;
	}	
		
	public function getNextCategory()
	{	
		if (!$category = parent::getNextCategory())
		{
			return null;
		}
		
		$data = $category->rawData;
		
		// set Zen Cart specific fields
		foreach ($this->languages as $code)
		{
			$category->setValueByLang('description', $code, $data['descr_' . $code]);
			
			if (!empty($data['keywords_' . $code]))
			{
				$category->keywords->set($data['keywords_' . $code]);
			}
		}
		
		return $category;
	}	

	protected function joinCategoryFields($id, $code)
	{
		return array('LEFT JOIN categories_description AS category_' . $code . ' ON category_' . $code . '.categories_id=categories.categories_id AND category_' . $code . '.language_id=' . $id,
					 'category_' . $code . '.categories_name AS name_' . $code . ', category_' . $code . '.categories_description AS descr_' . $code . ', category_' . $code . '.categories_meta_keywords AS keywords_' . $code
					);
	}
	
	protected function joinProductFields($id, $code)
	{
		return array('LEFT JOIN products_description AS product_' . $code . ' ON product_' . $code . '.products_id=products.products_id AND product_' . $code . '.language_id=' . $id,
					 'product_' . $code . '.products_name AS name_' . $code . ', ' . 'product_' . $code . '.products_description AS descr_' . $code . ', ' . 'product_' . $code . '.products_short_description AS shortDescr_' . $code . ', ' . 'product_' . $code . '.products_meta_keywords AS keywords_' . $code
					);
	}		
}

?>
