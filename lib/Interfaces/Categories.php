<?php

namespace Lum\Wiki\Interfaces;

/**
 * Interface for a database model class for top-level wiki categories.
 * 
 * @see \Lum\Wiki\Interfaces\Category for the child document interface.
 */
interface Categories
{
  /**
   * Look for a `Category` with the given identifier.
   *
   * @param string $ident  The category identifier we're looking for.
   *
   * @return ?Category  The category if it was found, or `null` otherwise.
   */
  function getCategory (string $ident): ?Category;

  /**
   * Return the associated top-level database model for `Topics`.
   */
  function getTopics(): Topics;

}