<?php

namespace Lum\Wiki\Interfaces;

/**
 * Interface for a database model class for wiki topics.
 * 
 * @see \Lum\Wiki\Interfaces\Category for the parent document interface.
 * @see \Lum\Wiki\Interfaces\Topic for the child document interface.
 */
interface Topics
{
  /**
   * Return an array or traversable object with all topics from the category.
   *
   * @return iterable  All the topics.
   */
  function getTopics (string|Category $category): iterable;

  /**
   * Look for a `Topic` in the given `Category`, with the given identifier.
   *
   * @param string|Category  The category identifier or `Category` object.
   * @param string $ident  The topic identifier we're looking for.
   *
   * @return ?Topic  The topic if it was found, or `null` otherwise.
   */
  function getTopic (string|Category $category, string $ident): ?Topic;

  /**
   * Return the associated top-level database model for `Categories`.
   */
  function getCategories(): Categories;

}