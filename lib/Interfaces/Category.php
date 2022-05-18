<?php

namespace Lum\Wiki\Interfaces;

/**
 * Interface for a child document of the `Categories` model interface.
 *
 * @see \Lum\Wiki\Interfaces\Categories for the parent model interface.
 * @see \Lum\Wiki\Interfaces\Topics for the child model `topics` interface.
 */
interface Category extends Content
{
  /**
   * Return an array or traversable object with all topics from this category.
   *
   * @return iterable  All the topics.
   */
  function getTopics(): iterable;

  /**
   * Look for a topic with the given identifier.
   *
   * @param string $ident  The topic identifier we're looking for.
   *
   * @return ?Topic  The topic if it was found, or `null` otherwise.
   */
  function getTopic(string $ident): ?Topic;

}