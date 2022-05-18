<?php

namespace Lum\Wiki\Traits;

use Lum\Wiki\Interfaces\Topic as TopicInterface;

/**
 * Implements some methods of the `Category` interface.
 */
trait Category
{
  protected $topic_cache = [];

  public function getTopics(): iterable
  {
    $topics = $this->parent->getTopics();
    return $topics->getTopics($this);
  }

  public function getTopic(string $ident): ?TopicInterface
  {
    if (isset($this->topic_cache[$ident]))
    {
      return $this->topic_cache[$ident];
    }

    $topics = $this->parent->getTopics();
    $topic = $topics->getTopic($this, $ident);
    if (isset($topic))
    {
      $topic->setCategory($this, true);
      $this->topic_cache[$ident] = $topic;
    }
    return $topic;
  }

}
