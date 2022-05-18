<?php

namespace Lum\Wiki\Twig;

use Exception;
use Twig\Loader\LoaderInterface;
use Twig\Error\LoaderError;
use Twig\Source as TwigSrc;
use Lum\Wiki\Parser;
use Lum\Wiki\ErrorFlags as Err;
use Lum\Wiki\Interfaces\{Categories, Category, Topic};

class Loader implements LoaderInterface
{
  const SEP = '::';

  protected Parser $parser;       // The Parser instance will be stored here.
  protected Categories $catModel; // The Categories model will be stored here.

  protected array $catDocs  = []; // A cache of Category documents will be stored here.
  protected array $sources  = []; // A cache of source text.
  protected array $updated  = []; // A cache of update times.
  protected array $outcache = []; // A cache of fully rendered output.

  /**
   * See if a model document has a valid 'ident' property.
   */
  static function valid_ident (object $doc)
  {
    return (isset($doc->ident) && is_string($doc->ident) && !empty(trim($doc->ident)));
  }

  /**
   * Build an internal name for a given topic.
   */
  static function makeName (Topic $topic): string
  {
    if (!self::valid_ident($topic))
    { // This should be impossible, but just in case.
      throw new Exception("No ident found on topic");
    }
    $category = $topic->getCategory();
    if (!self::valid_ident($category))
    { // Ditto on the impossibility factor.
      throw new Exception("No ident found on category");
    }
    return $category->ident . self::SEP . $topic->ident;
  }

  /**
   * When first building the instance, pass the Categories model instance.
   */
  public function __construct (Parser $parser)
  {
    $this->parser = $parser;
    $this->catModel = $parser->getCategories();
  }

  public function hasSource(string $name): bool
  {
    return isset($this->sources[$name]);
  }

  public function setSource(string $name, string $text)
  {
    $this->sources[$name] = $text;
  }

  public function getSource(string $name): ?string
  {
    return $this->sources[$name] ?? null;
  }

  public function setOutput(string $name, string $output)
  {
    $this->outcache[$name] = $output;
  }

  public function getOutput(string $name): ?string
  {
    return $this->outcache[$name] ?? null;
  }

  public function getSourceContext(string $name): TwigSrc
  {
    $source = $this->getSource($name);
    if (isset($source))
    { // We found it, yay.
      return new TwigSrc($source, $name);
    }
    else
    { // Didn't find it, let's look for it as an include file.
      [$categoryId,$includeId] = explode(self::SEP, $name, 2);

      if (isset($this->categoryDocs[$categoryId]))
      {
        $category = $this->categoryDocs[$categoryId];
      }
      else
      {
        $category = $this->catModel->getCategory($categoryId);
        if (isset($category))
        {
          $this->categoryDocs[$categoryId] = $category;
        }
        else
        {
          throw new LoaderError("Invalid category specified in path: $name");
        }
      }

      /**
       * @var Category $category 
       */
      $include = $category->getInclude($includeId, Err::NONE, Err::ALL);
      $this->setSource($name, $include);
      return new TwigSrc($include, $name);
    }
  }

  public function getCacheKey(string $name): string
  {
    return $name;
  }

  public function isFresh(string $name, int $time): bool
  {
    return false;
  }

  public function exists(string $name): bool
  {
    return true;
  }
}