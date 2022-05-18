<?php

namespace Lum\Wiki\Traits;

use Lum\Wiki\Interfaces\Category as CategoryInterface;

/**
 * Implements some methods of the `Topic` interface.
 */
trait Topic
{
  protected ?CategoryInterface $parentCategory;

  abstract function getCategoryId();
  abstract function setCategoryId($category);

  public function getCategory(): ?CategoryInterface
  {
    if (isset($this->parentCategory))
    {
      return $this->parentCategory;
    }
    else
    {
      $catId = $this->getCategoryId();
      if (!empty($catId))
      {
        $categories = $this->parent->getCategories();
        $category = $categories->getCategory($catId);
        if (isset($category))
        {
          $this->parentCategory = $category;
          return $category;
        }
      }
    }

    return null;
  }

  public function setCategory(CategoryInterface $category, bool $parentOnly=false)
  {
    $this->parentCategory = $category;
    if ($parentOnly) return;
    $this->setCategoryId($category);
  }

}