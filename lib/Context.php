<?php

namespace Lum\Wiki;

use ArrayAccess;
use Exception;
use Lum\Wiki\Interfaces\{Category,Topic};
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;

/**
 * A class representing the context for a specific Topic to be rendered by the Parser.
 * 
 * @package Lum\Wiki
 */
class Context implements ArrayAccess
{
  const VAR_MAP =
  [
    'this'     => 'addThis',
    'parser'   => 'addParser',
    'category' => 'addCategory',
    'topic'    => 'addTopic',
  ];

  public readonly Parser $parser;
  public readonly Category $category;
  public readonly Topic $topic;

  protected array $vars;

  public function __construct(Parser $parser, Topic $topic, array $vars, array $opts)
  {
    $this->parser = $parser;
    $this->topic = $topic;
    $this->category = $topic->getCategory();
    $this->vars = $vars;

    foreach (static::VAR_MAP as $opt => $meth)
    {
      if (isset($opts[$opt]))
      { // A variable for a specific object was found.
        if (is_string($opts[$opt]) && !empty(trim($opts[$opt])))
        { // Add a Twig variable with a specified name.
          $this->$meth($opts[$opt]);
        }
        elseif (is_bool($opts[$opt]) && $opts[$opt])
        { // Add a Twig variable with the default name.
          $this->$meth();
        }
      }
    }

  }

  public function offsetExists(mixed $offset): bool 
  { 
    return isset($this->vars[$offset]);
  }

  public function offsetGet(mixed $offset): mixed 
  { 
    return $this->vars[$offset] ?? null;
  }

  public function offsetSet(mixed $offset, mixed $value): void 
  { 
    $this->vars[$offset] = $value;
  }

  public function offsetUnset(mixed $offset): void 
  { 
    unset($this->vars[$offset]);
  }

  /**
   * Add a variable for the `Topic` into the Twig context.
   * 
   * @param string $name  Optional; The variable name, default 'topic'.
   * @return static 
   */
  public function addTopic(string $name='topic'): static
  {
    $this->vars[$name] = $this->topic;
    return $this;
  }

  /**
   * Add a variable for the `Category` into the Twig context.
   * 
   * @param string $name  Optional; The variable name, default 'category'.
   * @return static 
   */
  public function addCategory(string $name='category'): static
  {
    $this->vars[$name] = $this->category;
    return $this;
  }

  /**
   * Add a variable for the `Parser` into the Twig context.
   * 
   * @param string $name  Optional; The variable name, default 'parser'.
   * @return static 
   */
  public function addParser(string $name='parser'): static
  {
    $this->vars[$name] = $this->parser;
    return $this;
  }

  /**
   * Add a variable for this `Context` object into the Twig context.
   * 
   * @param string $name  Optional; The variable name, default is 'this'.
   * @return static 
   */
  public function addThis(string $name='this'): static
  {
    $this->vars[$name] = $this;
    return $this;
  }

  /**
   * Get a raw copy of the `Context` variable data as a PHP array.
   * 
   * This is generally only used by the parent `Parser`.
   * 
   * @return array 
   */
  public function getContext(): array
  {
    return $this->vars;
  }

  /**
   * Render the `Topic` associated with this `Context`.
   * 
   * This is generally called by the `Topic` class itself,
   * in a render() method of its own. Example:
   * 
   * ```
   *   function render(): string
   *   {
   *     $parser = $this->parent->parent->parser;
   *     $context = $parser->contextFor($this);
   *     return $context->render();
   *   }
   * ```
   * 
   * @return string  The fully parsed and rendered Topic content.
   * @throws Exception 
   * @throws LoaderError 
   * @throws SyntaxError 
   * @throws RuntimeError 
   */
  public function render(): string
  {
    return $this->parser->parse($this);
  }

}