<?php

namespace Lum\Wiki;

use Exception, ParsedownExtra;
use Lum\Plugins\Debug as DBG;
use Lum\Wiki\Interfaces\{Categories,Category,Topics,Topic,Controller};
use Lum\Wiki\Twig\Extension;
use Lum\Wiki\Twig\Loader;
use Twig\Environment as TwigEnv;
use Netcarver\Textile\Parser as Textile;

class Parser
{
  const NONE     = 0;
  const MARKDOWN = 1;
  const TEXTILE  = 2;

  const DEFAULT_TWIG_OPTS = ['autoescape'=>false];
  const DEFAULT_TWIG_EXTS = 
  [
    '\Lum\Wiki\Twig\WikiExtension',
    '\Lum\Wiki\Twig\FormattingExtension',
    '\Lum\Wiki\Twig\SwitchExtension',
  ];

  const O_CTRL    = 'parent';
  const O_CAT     = 'categories';
  const O_TWIG    = 'twig';
  const O_GLOB    = 'globals';
  const O_CTX     = 'context';
  const O_SETEXT  = 'setExtensions';
  const O_ADDEXT  = 'addExtensions';
  const O_FORMAT  = 'primaryFormat';
  const O_MD_TAG  = 'markdownTag';
  const O_TX_TAG  = 'textileTag';
  const O_STRIP   = 'stripBlanks';

  const O_TAG_MAP =
  [
    self::O_MD_TAG => 'defaultMarkdownTag',
    self::O_TX_TAG => 'defaultTextileTag',
  ];

  const WITH_MD_TAG = 'markdown';
  const WITH_TX_TAG = 'textile';

  // Thanks to how class 'constants' work in PHP, you can override these in sub-classes.

  const CATEGORY_URI = 'wiki_get_category';
  const TOPIC_URI    = 'wiki_get_topic';
  const CATEGORY_PLACEHOLDER = 'category';
  const TOPIC_PLACEHOLDER    = 'topic';

  // We use self:: not static:: for this, so overriding it is useless.
  const DEBUG_FLAG = 'wiki-parser';
  
  protected readonly Controller $controller;
  protected readonly Categories $categories;
  protected readonly TwigEnv $twig;
  protected readonly Loader $twigLoader;
  public readonly ParsedownExtra $parsedown;
  public readonly Textile $textile;

  protected array $exts = [];

  protected int $defaultPrimaryFormat   = self::MARKDOWN;
  protected ?string $defaultMarkdownTag = null;
  protected ?string $defaultTextileTag  = null;
  protected bool $defaultStripBlanks    = true;

  protected int $primaryFormat;
  protected ?string $markdownTag;
  protected ?string $textileTag;
  protected bool $stripBlanks;

  protected array $globals = [];
  protected array $contextOpts = ['this' => true];

  protected ?Context $current = null;

  public function getCategoryUri(): string
  {
    return static::CATEGORY_URI;
  }

  public function getTopicUri(): string 
  {
    return static::TOPIC_URI;
  }

  public function getCategoryPlaceholder(): string
  {
    return static::CATEGORY_PLACEHOLDER;
  }

  public function getTopicPlaceholder(): string
  {
    return static::TOPIC_PLACEHOLDER;
  }

  public function __construct(array $opts=[])
  {
    $o = static::O_CTRL;
    if (isset($opts[$o]) && $opts[$o] instanceof Controller)
    {
      $this->controller = $opts[$o];
    }
    else
    {
      throw new Exception("No '$o' controller in constructor options");
    }

    $o = static::O_CAT;
    if (isset($opts[$o]) && $opts[$o] instanceof Categories)
    {
      $this->categories = $opts[$o];
    }
    elseif (isset($opts[$o]) && is_string($opts[$o]))
    {
      $this->categories = $this->controller->model($opts[$o]);
    }
    else
    {
      throw new Exception("No '$o' model in constructor options");
    }

    $this->parsedown = new ParsedownExtra();
    $this->textile = new Textile();
    $this->twigLoader = new Loader($this);
    $twigOpts = $opts[static::O_TWIG] ?? static::DEFAULT_TWIG_OPTS;
    $this->twig = new TwigEnv($this->twigLoader, $twigOpts);

    $exts = $opts[static::O_SETEXT] ?? static::DEFAULT_TWIG_EXTS;

    $o = static::O_ADDEXT;
    if (isset($opts[$o]) && is_array($opts[$o]))
    {
      $exts = array_merge($exts, $opts[$o]);
    }

    foreach ($exts as $ek => $ev) 
    {
      if (is_numeric($ek) && (is_string($ev) || $ev instanceof Extension)) 
      { // Value is a classname or Extension instance.
        $this->addExtension($ev);
      } 
      elseif (is_string($ek) && is_array($ev)) 
      { // Key is the extension classname, value is the $opts.
        $this->addExtension($ek, $ev);
      }
      else
      { // That's not valid.
        throw new Exception("Invalid extension definition: " . serialize([$ek, $ev]));
      }
    }

    foreach (static::O_TAG_MAP as $o => $p)
    {
      if (array_key_exists($o, $opts) && (is_string($opts[$o]) | is_null($opts[$o])))
      {
        $this->$p = $opts[$o];
      }
    }

    $o = static::O_FORMAT;
    if (isset($opts[$o]) && is_int($opts[$o]))
    {
      $this->defaultPrimaryFormat = $opts[$o];
    }

    $o = static::O_STRIP;
    if (isset($opts[$o]) && is_bool($opts[$o]))
    {
      $this->defaultStripBlanks = $opts[$o];
    }

    $o = static::O_GLOB;
    if (isset($opts[$o]) && is_array($opts[$o]))
    {
      $this->globals = $opts[$o];
    }

    $o = static::O_CTX;
    if (isset($opts[$o]) && is_array($opts[$o]))
    {
      $this->contextOpts = $opts[$o];
    }
    
    $this->resetFormat();

  } // __construct()

  public function resetFormat()
  {
    $this->primaryFormat = $this->defaultPrimaryFormat;
    $this->markdownTag   = $this->defaultMarkdownTag;
    $this->textileTag    = $this->defaultTextileTag;
    $this->stripBlanks   = $this->defaultStripBlanks;
  }

  public function addExtension(string|Extension $ext, array $opts=[]): static
  {
    if (is_string($ext))
    { // Build the extension assuming the default constructor.
      $ext = new $ext($this, $opts);
    }

    $name = $ext->getName();
    $this->exts[$name] = $ext;
    $this->twig->addExtension($ext);

    if (DBG::is(self::DEBUG_FLAG, 1))
      error_log("Added $name extension");

    return $this;
  }

  public function useNone(): static
  {
    $this->primaryFormat = self::NONE;
    return $this;
  }

  public function useMarkdown(): static
  {
    $this->primaryFormat = self::MARKDOWN;
    return $this;
  }

  public function useTextile(): static
  {
    $this->primaryFormat = self::TEXTILE;
    return $this;
  }

  public function withMarkdown(string $tag=self::WITH_MD_TAG): static
  {
    $this->markdownTag = $tag;
    return $this;
  }

  public function withTextile(string $tag=self::WITH_TX_TAG): static
  {
    $this->textileTag = $tag;
    return $this;
  }

  public function stripBlanks(bool $val=true): static
  {
    $this->stripBlanks = $val;
    return $this;
  }

  public function allowBlanks(bool $val=true): static
  {
    $this->stripBlanks = !$val;
    return $this;
  }

  public function getController(): ?object
  {
    return $this->controller;
  }
  
  public function getCategories(): Categories
  {
    return $this->categories;
  }

  public function getTopics(): Topics
  {
    return $this->categories->getTopics();
  }

  public function getCategory($category=null): ?Category
  {
    if (isset($category))
    {
      return $this->categories->getCategory($category);
    }
    elseif (isset($this->current))
    {
      return $this->current->category;
    }
    return null;
  }

  public function getTopic($topic=null): ?Topic
  {
    if (!isset($this->current)) return null;

    if (isset($topic))
    {
      return $this->current->category->getTopic($topic);
    }
    else
    {
      return $this->current->topic;
    }
  }

  public function getTopicFromCategory($catId, $topicId): ?Topic
  {
    $cat = $this->getCategory($catId);
    if (isset($cat))
    {
      return $cat->getTopic($topicId);
    }
    return null;
  }

  public function getExtension(string $name): ?Extension
  {
    return $this->exts[$name] ?? null;
  }

  function getText(?string $key=null, ?array $opts=null)
  {
    $text = $this->controller->get_text();
    if (isset($key))
    { // We want a specific key.
      if (isset($opts))
      { // With options.
        return $text->getStr($key, $opts);
      }
      else
      { // Flat text key.
        return $text[$key];
      }
    }
    else
    { // Return the Translations object instance.
      return $text;
    }
  }

  public function contextFor(Topic $topic): Context
  {
    $context = new Context($this, $topic, $this->globals, $this->contextOpts);
    $this->current = $context;
    foreach ($this->exts as $ext)
    {
      $ext->setupContext($context);
    }
    $this->current = null;
    return $context;
  }

  public function parse(Context $context): string
  {
    // Set the current context.
    $this->current = $context;

    $tl = $this->twigLoader;                                          // The singular twig loader instance.
    $name = $tl->makeName($context->topic);                           // Internal identifier for Twig.
    $output = $tl->getOutput($name);                                  // Check for cached output.
    if (isset($output))
    { // Return the cached copy.
      return $output;
    }

    // Get the raw text.
    $catText = trim($context->category->getText());
    $topicText = trim($context->topic->getText(ErrorFlags::ALL));
    $text = empty($catText) ? $topicText : "$catText\n$topicText";

    // Render the text with Twig.
    $tl->setSource($name, $text);
    $text = $this->twig->render($name, $context->getContext());

    if (isset($this->markdownTag))
    { // Parse markdown blocks if they're enabled.
      $tag = $this->markdownTag;
      $regex = "#<$tag>(.*?)</$tag>#s";
      $fun = fn($matches) => $this->parsedown->text($matches[1]);
      $text = preg_replace_callback($regex, $fun, $text);
    }

    if (isset($this->textileTag))
    { // Parse textile blocks if they're enabled.
      $tag = $this->textileTag;
      $regex = "#<$tag>(.*?)</$tag>#s";
      $fun = fn($matches) => $this->textile->parse($matches[1]);
      $text = preg_replace_callback($regex, $fun, $text);
    }

    if ($this->primaryFormat === self::MARKDOWN)
    { // Parse the rest of the document with Markdown.
      $text = $this->parsedown->text($text);
    }
    elseif ($this->primaryFormat === self::TEXTILE)
    { // Parse the rest of the document with Textile.
      $text = $this->textile->parse($text);
    }

    if ($this->stripBlanks)
    {
      $regex = "#^\s*(<p>\s*</p>\s*)*#s";
      $text = preg_replace($regex, '', $text);
    }

    // We're finished rendering, so we can clear the current context.
    $this->current = null;
  
    return $text;
  }

}
