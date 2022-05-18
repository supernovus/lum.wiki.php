<?php

namespace Lum\Wiki\Twig;

use Lum\Wiki\Context;
use Lum\Wiki\Interfaces\{Category};
use SimpleDOM;

class WikiExtension extends Extension
{
  const NAME = 'wiki';

  public $iconPrefix = 'i.';
  public $iconChar   = '.char';
  public $iconDesc   = '.desc';
  public $iconClass  = '.class';

  protected function setup(array $opts)
  {
    $this // First we add functions.
      ->fun('at')->method('makeTopicLink')->add(true)
      ->fun('ac')->method('makeCategoryLink')->add(true)
      ->fun('ai')->method('makeIndexLink')->add(true)
      ->fun('todo')->method('makeTodo')->add(true)
      ->fun('i')->method('makeIcon')->add(true)
      ->fun('topic')->handler([$this->parser, 'getTopic'])->add(true)
      ->fun('category')->handler([$this->parser, 'getCategory'])->add(true)
      ->fun('text')->handler([$this->parser, 'getText'])->add(true);

    $this // Now we add filters.
      ->filter('a')->method('makeLinkFilter')->add(true)
      ->filter('i')->method('makeIconFilter')->add(true)
      ->filter('topic')->method('getTopicFromCategory')->add(true)
      ->filter('todo')->method('makeTodo')->add(true);
  }

  public function setupContext(Context $context)
  {
    $context['home'] = $this->makeIndexLink();
  }

  public function makeTodo (string $name, bool $asObject=false): SimpleDOM|string
  {
    $tag = new SimpleDOM("<a href=\"#todo\" class=\"todo\">$name</a>");
    return ($asObject ? $tag : $tag->outerXML());
  }

  public function getTopicFromCategory($category, $topicId)
  {
    if (is_string($category))
      $category = $this->parser->getCategory($category);
    if ($category instanceof Category)
      return $category->getTopic($topicId);
    else
      return null;
  }

  protected function makeLink(string $uri, string $text, 
    ?string $desc=null, ?string $class=null, bool $asObject=false): SimpleDOM|string
  {
    $link = new SimpleDOM("<a>$text</a>");
    $link['href'] = $uri;
    if (isset($desc)) 
      $link['title'] = $desc;
    if (isset($class)) 
      $link['class'] = $class;
    return ($asObject ? $link : $link->outerXML());
  }

  protected function invalidLink(bool $asObject): SimpleDOM|string
  {
    $a = '<a/>';
    return ($asObject ? new SimpleDOM($a) : $a);
  }

  public function makeIcon (string $id, ?string $desc=null, string $class='',
    bool $asObject=false): SimpleDOM|string
  {
    $text = $this->parser->getText();
    
    $charKey  = $this->iconPrefix . $id . $this->iconChar;
    $classKey = $this->iconPrefix . $id . $this->iconClass;
    $descKey  = $this->iconPrefix . $id . $this->iconDesc;

    $charText = $text[$charKey];
    if ($charText == $charKey) 
      $charText = '';

    $classText = $text[$classKey];
    if ($classText != $classKey)
      $class .= " $classText";
    $class .= " $id";

    if (is_null($desc))
    {
      $descText = $text[$descKey];
      if ($descText != $descKey)
        $desc = $descText;
    }

    $icon = new SimpleDOM("<i>$charText</i>");

    $icon['class'] = trim($class);

    if (isset($desc))
      $icon['title'] = $desc;

    return ($asObject ? $icon : $icon->outerXML());
  }

  public function makeIconFilter(string $text, ?string $desc=null, string $class='', 
    bool $asObject=false): SimpleDOM|string
  {
    if (str_contains($text, '/'))
    {
      $text = trim($text, ' /');    // Strip leading and trailing whitespace and slashes.
      $parts = explode('/', $text); // Break it up into elements.
      $text = trim(array_shift($parts)); // The first element is the icon id.

      if (count($parts) > 0)
      { // Next would be a new description.
        $newdesc = trim(array_shift($parts));
        if (!empty($trydesc)) $desc = $newdesc;
      }

      if (count($parts) > 0)
      { // Anything left is extra classes to apply.
        $newclass = trim(join(' ', array_map(fn($v) => trim($v), $parts)));
        if (!empty($newclass)) $class = $newclass;
      }

    } // if contains '/'
    
    // Now just pass everything through to makeIcon();
    return $this->makeIcon($text, $desc, $class, $asObject);
  }

  public function makeLinkFilter(string $input, ?string $text=null, ?string $desc=null, ?string $class=null, 
    bool $asObject=false): SimpleDOM|string
  {

    if (str_starts_with($input, '$'))
    { // Start the whole thing with a '$' to set $asObject as true.
      $asObject = true;
      $input = ltrim($input, ' $');
    }

    $assignSplit = function ($delim, &$val) use (&$input)
    { // A magic function that does some funny tricks.
      if (str_contains($input, $delim))
      {
        $args = explode($delim, $input);
        $input = trim($args[0]);
        $val = trim($args[1]);
      }
    };

    // You can use any of these, but if more than one, they must be in order: 'link == text := desc .= class' 

    $assignSplit('.=', $class);   // {{'topic .= class1 class2 class3'|a}}
    $assignSplit(':=', $desc);    // {{'topic := Hover text for link'|a}}
    $assignSplit('==', $text);    // {{'topic == Nice text for link'|a}}

    if (empty($input))
    { // If after all that, the input is empty, it's invalid.
      error_log("Invalid link statement: empty after parsing options");
      return $this->invalidLink($asObject);
    }
    elseif ($input === '/')
    { // All that's left is a single slash? Okay, off to the index page we go.
      return $this->makeIndexLink($text, $desc, $class, $asObject);
    }

    // There's more to be seen, let's parse this further.

    $args = explode('/', $input);
    $argc = count($args);
    if ($argc === 1)
    { // Only one item, so no slashes, it's a basic topic link.
      $topic = trim($args[0]);
      return $this->makeTopicLink($topic, null, $text, $desc, $class, $asObject);
    }
    else
    { // More than one, the first non-empty one is the category, and if found, the second is the topic.
      $category = null;
      $topic = null;
      foreach ($args as $arg)
      {
        $val = trim($arg);
        if (empty($val)) continue; // Skip to the next one.
        if (!isset($category))
        { // Assign the category then move on.
          $category = $val;
          continue;
        }
        else
        { // category was already assigned, thus it's the topic.
          $topic = $val;
          break;
        }
      }

      if (!isset($category))
      { // That's not valid.
        error_log("Invalid link statement: no category found");
        return $this->invalidLink($asObject);
      }

      return $this->makeCategoryLink($category, $topic, $text, $desc, $class, $asObject);
    }

  } // makeLinkFilter()

  public function makeTopicLink(string $topic, ?string $category=null, 
    ?string $text=null, ?string $desc=null, ?string $class=null, bool $asObject=false): SimpleDOM|string
  {
    if (isset($category))
    {
      $doc = $this->parser->getTopicFromCategory($category, $topic);
    }
    else
    {
      $doc = $this->parser->getTopic($topic);
    }

    if (!isset($doc))
    {  // No document? Add a TODO marker instead.
      if (is_null($text))
        $text = $topic;
      return $this->makeTodo($text, $asObject);
    }

    if (is_null($text))
    {
      $text = $doc->name;
    }

    if (!$doc->published)
    { // Document isn't published, mark it as TODO.
      return $this->makeTodo($text, $asObject);
    }

    if (is_null($category))
    {
      $category = $this->parser->getCategory()->ident;
    }

    $ctrl = $this->parser->getController();
    $route = $this->parser->getTopicUri();
    $cp = $this->parser->getCategoryPlaceholder();
    $tp = $this->parser->getTopicPlaceholder();
    $uri = $ctrl->get_uri($route, [$cp=>$category, $tp=>$topic]);

    return $this->makeLink($uri, $text, $desc, $class, $asObject);
  }

  public function makeCategoryLink(string $category, ?string $topic=null, 
    ?string $text=null, ?string $desc=null, ?string $class=null, bool $asObject=false): SimpleDOM|string
  {
    if (isset($topic))
    { // If topic is set, we're going to flip the script and pass it over to makeTopicLink();
      return $this->makeTopicLink($topic, $category, $text, $desc, $class, $asObject);
    }

    $doc = $this->parser->getCategory($category);
    if (!isset($doc))
    { // No doc, can't go on.
      if (is_null($text))
        $text = $category;
      return $this->makeTodo($text, $asObject);
    }

    if (is_null($text))
    {
      $text = $doc->name;
    }

    if (!$doc->published)
    { // Not published, mark as TODO.
      return $this->makeTodo($text, $asObject);
    }

    $ctrl = $this->parser->getController();
    $route = $this->parser->getCategoryUri();
    $cp = $this->parser->getCategoryPlaceholder();
    $uri = $ctrl->get_uri($route, [$cp=>$category]);

    return $this->makeLink($uri, $text, $desc, $class, $asObject);
  }

  public function makeIndexLink(?string $text=null, ?string $desc=null, ?string $class=null, 
    bool $asObject=false): SimpleDOM|string
  {
    $cat = $this->parser->getCategory();
    if (isset($cat))
    {
      return $this->makeCategoryLink($cat->ident, null, $text, $desc, $class, $asObject);
    }
    else
    {
      error_log('Attempt to make index link with no current category.');
      return $this->invalidLink($asObject);
    }
  }

} // class Lum\Wiki\Twig\Extension
