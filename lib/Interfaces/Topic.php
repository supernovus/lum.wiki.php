<?php

namespace Lum\Wiki\Interfaces;

/**
 * Interface for a child document of the `Topics` model interface.
 *
 * @see \Lum\Wiki\Interfaces\Topics for the parent model interface.
 */
interface Topic extends Content
{
  /**
   * Return the parent `Category` if it's been set.
   *
   * @return ?Category  The parent category.
   */
  function getCategory(): ?Category;

  /**
   * Set or update the parent `Category` for this topic.
   */
  function setCategory(Category $category, bool $parentOnly=false);

  /**
   * Get the content of this topic.
   *
   * @return string  An HTML string of rendered content.
   */
  function getContent(): string;

}