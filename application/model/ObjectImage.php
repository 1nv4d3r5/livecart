<?php

ClassLoader::import("application.model.system.MultilingualObject");
ClassLoader::import("library.image.ImageManipulator");

/**
 * Generic associated image handler. Images can be associated to products, categories and possibly
 * other entities in the future
 *
 * @package application.model
 * @author Integry Systems <http://integry.com>
 */
abstract class ObjectImage extends MultilingualObject
{
	abstract public static function getImageSizes();
	abstract public function getOwner();

	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));
		$schema->registerField(new ARField("title", ARArray::instance()));
		$schema->registerField(new ARField("position", ARInteger::instance()));

		return $schema;
	}

	public function deleteImageFiles()
	{
		foreach ($this->getImageSizes() as $key => $value)
	  	{
	  		if(is_file($this->getPath($key)))
	  		{
			   unlink($this->getPath($key));
	  		}
		}
	}

	public static function deleteByID($className, $id, $foreignKeyName)
	{
		$inst = ActiveRecordModel::getInstanceById($className, $id, ActiveRecordModel::LOAD_DATA);
		$inst->getOwner()->load();
		$inst->deleteImageFiles();

		// check if main image is being deleted
		$owner = $inst->getOwner();
		$owner->load(array(get_class($inst)));
		if ($owner->defaultImage->get()->getID() == $id)
		{
			// set next image (by position) as the main image
			$f = new ARSelectFilter();
			$cond = new EqualsCond(new ARFieldHandle(get_class($inst), $foreignKeyName), $owner->getID());
			$cond->addAND(new NotEqualsCond(new ARFieldHandle(get_class($inst), 'ID'), $inst->getID()));
			$f->setCondition($cond);
			$f->setOrder(new ARFieldHandle(get_class($inst), 'position'));
			$f->setLimit(1);
			$newDefaultImage = ActiveRecordModel::getRecordSet(get_class($inst), $f);
			if ($newDefaultImage->size() > 0)
			{
			  	$owner->defaultImage->set($newDefaultImage->get(0));
			  	$owner->save();
			}
		}

		return parent::deleteByID($className, $id);
	}

	public function setFile($file)
	{
		if (substr(strtolower($file), 0, 7) == 'http://')
		{
			$fetch = new NetworkFetch($file);
			$fetch->fetch();
			$this->cacheFile = $fetch->getTmpFile();

			if (!file_exists($this->cacheFile))
			{
				return null;
			}

			$file = $this->cacheFile;
		}

		return $this->resizeImage(new ImageManipulator($file));
	}

	public function resizeImage(ImageManipulator $resizer)
	{
	  	$publicRoot = dirname(ClassLoader::getRealPath('public.upload')) . '/';

		foreach ($this->getImageSizes() as $key => $size)
	  	{
			$filePath = $publicRoot . $this->getPath($key);

			if (!file_exists(dirname($filePath)))
			{
				mkdir(dirname($filePath), 0777, true);
			}

			$res = $resizer->resize($size[0], $size[1], $filePath);
			if (!$res)
			{
			  	break;
			}
		}

		return $res;
	}

	protected function insert($foreignKeyName)
	{
	  	$className = get_class($this);

		// get current max image position
	  	$filter = new ARSelectFilter();
	  	$filter->setCondition(new EqualsCond(new ARFieldHandle($className, $foreignKeyName), $this->getOwner()->getID()));
	  	$filter->setOrder(new ARFieldHandle($className, 'position'), 'DESC');
	  	$filter->setLimit(1);
	  	$maxPosSet = ActiveRecord::getRecordSet($className, $filter);
		if ($maxPosSet->size() > 0)
		{
			$maxPos = $maxPosSet->get(0)->position->get() + 1;
		}
		else
		{
		  	$maxPos = 0;
		}

		$this->position->set($maxPos);

		return parent::insert();
	}

	public function save()
	{
		parent::save();

		// set as main image if it's the first image being uploaded
		if ($this->position->get() == 0)
		{
			$owner = $this->getOwner();
			$owner->defaultImage->set($this);
			$owner->save();
		}
	}

	public function _clone(ActiveRecordModel $owner)
	{
		$cloned = clone $this;
		$cloned->setOwner($owner);
		$cloned->save();

		foreach ($this->getImageSizes() as $key => $value)
	  	{
			$p = $this->getPath($key);
			$originalPaths[$key] = $this->getPath($key);
		}

		parent::__clone();

		$originalPaths = array();
		foreach ($this->getImageSizes() as $key => $value)
	  	{
			$originalPaths[$key] = $this->getPath($key);
		}

		return;
		foreach (1 as $key => $value)
	  	{
	  		if(is_file($this->getPath($key)))
	  		{
			   unlink($this->getPath($key));
	  		}
		}
	}

	public function __destruct()
	{
		if ($this->cacheFile)
		{
			@unlink($this->cacheFile);
		}
	}
}

?>