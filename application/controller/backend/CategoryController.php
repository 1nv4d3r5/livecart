<?php

ClassLoader::import("application.controller.backend.abstract.StoreManagementController");
ClassLoader::import("application.model.category.Category");

/**
 * Product Category controller
 *
 * @package application.controller.backend
 * @author Saulius Rupainis <saulius@integry.net>
 *
 * @role product
 */
class CategoryController extends StoreManagementController
{
	public function index()
	{
		$categoryList = Category::getRootNode()->getDirectChildNodes();
		$categoryList->unshift(Category::getRootNode());
		
		$response = new ActionResponse();
		$response->set('categoryList', $categoryList->toArray($this->store->getDefaultLanguageCode()));        
		return $response;
	}

	/**
	 * Displays category form (for creating a new category or modifying an existing one)
	 *
	 * @role !category
	 * 
	 * @return ActionResponse
	 */
	public function form()
	{
		ClassLoader::import("framework.request.validator.Form");

		$response = new ActionResponse();
		$form = $this->buildForm();
		$response->set("catalogForm", $form);

		$categoryArr = Category::getInstanceByID($this->request->get("id"), Category::LOAD_DATA)->toArray();
		$form->setData($categoryArr);
		$response->set("categoryId", $categoryArr['ID']);

		return $response;
	}

	/**
	 * Creates a new category record
	 *
	 * @role !category.create
	 * 
	 * @return ActionRedirectResponse
	 */
	public function create()
	{
		$parent = Category::getInstanceByID((int)$this->request->get("id"));
		
		$categoryNode = Category::getNewInstance($parent);
		$categoryNode->setValueByLang("name", $this->store->getDefaultLanguageCode(), 'dump' );
		$categoryNode->save();
		
		$categoryNode->setValueByLang("name", $this->store->getDefaultLanguageCode(), $this->translate("_new_category") . " " . $categoryNode->getID() );

        $categoryNode->save();

		try 
		{
			return new JSONResponse($categoryNode->toArray());
		}
		catch(Exception $e)
		{
		    return new JSONResponse(false);
		}
	}

	/**
	 * Updates a category record
	 * 
	 * @role !category.update
	 *
	 * @return ActionRedirectResponse
	 */
	public function update()
	{
	    $validator = $this->buildValidator();
		if($validator->isValid())
		{
			$categoryNode = Category::getInstanceByID($this->request->get("id"), Category::LOAD_DATA);
			$categoryNode->setFieldValue('isEnabled', $this->request->get('isEnabled', 0));
			
			$multilingualFields = array("name", "description", "keywords");
			$categoryNode->setValueArrayByLang($multilingualFields, $this->store->getDefaultLanguageCode(), $this->store->getLanguageArray(true), $this->request);
			$categoryNode->save();
			
			return new JSONResponse(array_merge($categoryNode->toFlatArray(), array('infoMessage' => $this->translate('_succsessfully_saved'))));
		}
	}

	/**
	 * Debug method: outputs category tree structure
	 *
	 */
	public function viewTree()
	{
		$rootNode = ActiveTreeNode::getRootNode("Category");

		$recordSet = $rootNode->getChildNodes(false, true);
		echo "<pre>"; print_r($recordSet->toArray()); echo "</pre>";
	}

	/**
	 * Removes node from a category
	 *
	 * @role !category.remove
	 */
	public function remove()
	{
		try
        {
            Category::deleteByID($this->request->get("id"), 0);   
            $status = true;
        }
        catch (Exception $e)
        {
            $status = false;
        }
		
		return new JSONResponse($status);
	}

	/**
	 * Reorder category node
	 *
	 * @role !category.sort
	 */
	public function reorder()
	{
	    $targetNode = Category::getInstanceByID((int)$this->request->get("id"));
		$parentNode = Category::getInstanceByID((int)$this->request->get("parentId"));
		
		$status = true;
		try
		{
			if($direction = $this->request->get("direction", false))
			{
			    if(ActiveTreeNode::DIRECTION_LEFT == $direction) $targetNode->moveLeft(false);
			    if(ActiveTreeNode::DIRECTION_RIGHT == $direction) $targetNode->moveRight(false);
			}
			else
			{
			    $targetNode->moveTo($parentNode);
			}
		}
		catch(Exception $e)
	    {
		    $status = false;
		}
		
		return new JSONResponse($status);
	}

	public function countTabsItems() {
	  	ClassLoader::import('application.model.category.*');
	  	ClassLoader::import('application.model.filter.*');
	  	ClassLoader::import('application.model.product.*');
	    
	    $category = Category::getInstanceByID((int)$this->request->get('id'), Category::LOAD_DATA);
	    return new JSONResponse(array(
	        'tabProducts' => $category->totalProductCount->get(),
	        'tabFilters' => FilterGroup::countItems($category),
	        'tabFields' => SpecField::countItems($category),
	        'tabImages' => CategoryImage::countItems($category),
	    ));
	}
	
	public function xmlBranch() 
	{
	    $xmlResponse = new XMLResponse();
	    $rootID = (int)$this->request->get("id");

	    if(!in_array($rootID, array(Category::ROOT_ID, 0))) 
	    {
	       $category = Category::getInstanceByID($rootID);
		   $xmlResponse->set("rootID", $rootID);
           $xmlResponse->set("categoryList", $category->getChildNodes(false, true)->toArray($this->store->getDefaultLanguageCode()));
	    }
	    
	    return $xmlResponse;
	}

	public function xmlRecursivePath() 
	{
	    $xmlResponse = new XMLResponse();
	    $targetID = (int)$this->request->get("id");
	    
	    try 
	    {
    	    $categoriesList = Category::getInstanceByID($targetID)->getPathBranchesArray();
    	    if(count($categoriesList) > 0 && isset($categoriesList['children'][0]['parent'])) 
    	    {
        	    $xmlResponse->set("rootID", $categoriesList['children'][0]['parent']);
        	    $xmlResponse->set("categoryList", $categoriesList);
    	    }
	    }
	    catch(Exception $e) 
	    {
	    }
	    
	    $xmlResponse->set("targetID", $targetID);
	    
	    return $xmlResponse;
	}
	
	public function debug()
	{
		ActiveTreeNode::reindex("Category");
	}
	
	/**
	 * Builds a category form validator
	 *
	 * @return RequestValidator
	 */
	private function buildValidator()
	{
		ClassLoader::import("framework.request.validator.RequestValidator");

		$validator = new RequestValidator("category", $this->request);
		$validator->addCheck("name", new IsNotEmptyCheck($this->translate("Catgory name should not be empty")));
		return $validator;
	}

	/**
	 * Builds a category form instance
	 *
	 * @return Form
	 */
	private function buildForm()
	{
		$form = new Form($this->buildValidator());
		return $form;
	}
}

?>