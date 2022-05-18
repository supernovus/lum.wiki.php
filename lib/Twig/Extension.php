<?php

namespace Lum\Wiki\Twig;

use Lum\Plugins\Debug as DBG;
use Lum\Wiki\{Parser,Context};
use Twig\Extension\AbstractExtension;
use Twig\{TwigFunction,TwigFilter,TwigTest};
use Twig\ExpressionParser as TwigExp;
use Twig\TokenParser\TokenParserInterface as TwigTokenParser;
use Twig\NodeVisitor\NodeVisitorInterface as TwigNodeVisitor;

abstract class Extension extends AbstractExtension
{
  /**
   * Override this constant in each extension class.
   */
  const NAME = 'extension';

  // Overriding this on the other hand is useless as we use self:: instead of static::
  const DEBUG_FLAG = 'wiki-extension';

  protected Parser $parser;

  protected $functions = [];
  protected $filters   = [];
  protected $tests     = [];
  protected $tags      = [];
  protected $visitors  = [];
  protected $u_ops     = [];
  protected $b_ops     = [];

  public function _diag()
  {
    $ename = $this->getName();
    foreach (['functions','filters','tests','tags','visitors','u_ops','b_ops'] as $pname)
    {
      $pval = $this->$pname;
      foreach ($pval as $key => $val)
      {
        $type = get_debug_type($val);
        $label = is_numeric($key) ? "→" : "$key ⇒";
        error_log("[$ename:$pname] $label $type");
      }
    }
  }

  /**
   * Build an extension instance.
   * 
   * @param Parser $parser  The parent `Parser` instance.
   * @param array $opts  Options to pass to the `setup()` method.
   * @return void 
   */
  public function __construct (Parser $parser, array $opts=[])
  {
    $this->parser = $parser;
    $this->setup($opts);
    if (DBG::is(self::DEBUG_FLAG, 2))
      $this->_diag();
  }

  /**
   * Use this to add functions, filters, tests, etc.
   */
  abstract protected function setup(array $opts);

  /**
   * Get the extension name used by the Parser when registering this extension.
   * @return string 
   */
  public function getName(): string
  {
    return static::NAME;
  }

  /**
   * Extend this in your extension to set up `Context` instances.
   * 
   * The `contextFor()` method in the `Parser` class calls this method on every
   * registered extension, so they can add things to the context.
   * 
   * This default implementation doesn't do anything at all.
   * 
   * @param Context $context 
   * @return mixed  Unused in the default implementations.
   */
  public function setupContext(Context $context)
  {
    return $context;
  }

  public function getTokenParsers()
  {
    return $this->tags;
  }

  public function getNodeVisitors()
  {
    return $this->visitors;
  }

  public function getFunctions()
  {
    return $this->functions;
  }

  public function getFilters()
  {
    return $this->filters;
  }

  public function getTests()
  {
    return $this->tests;
  }

  public function getOperators()
  {
    return [$this->u_ops, $this->b_ops];
  }

  public function fun(string $name): FunctionFactory
  {
    return new FunctionFactory($this, $name);
  }

  public function filter(string $name): FilterFactory
  {
    return new FilterFactory($this, $name);
  }

  public function test(string $name): TestFactory
  {
    return new TestFactory($this, $name);
  }

  public function tag(TwigTokenParser $tokenParser): static
  {
    $this->tags[] = $tokenParser;
    return $this;
  }

  public function visitor(TwigNodeVisitor $nodeVisitor): static
  {
    $this->visitors[] = $nodeVisitor;
    return $this;
  }

  public function op(string $name)
  {
    return new OpFactory($this, $name);
  }

  public function addFunction(TwigFunction $fun): static
  {
    $name = $fun->getName();
    $this->functions[$name] = $fun;
    return $this;
  }

  public function addFilter(TwigFilter $filter): static
  {
    $name = $filter->getName();
    $this->filters[$name] = $filter;
    return $this;
  }

  public function addTest(TwigTest $test): static
  {
    $name = $test->getName();
    $this->tests[$name] = $test;
    return $this;
  }

  public function addUnaryOp(string $op, array $def): static
  {
    $this->u_ops[$op] = $def;
    return $this;
  }

  public function addBinaryOp(string $op, array $def): static
  {
    $this->b_ops[$op] = $def;
    return $this;
  }

} // Extension

abstract class Factory
{
  public Extension $ext;
  public string $name;

  /**
   * Build the extension item from the factory, and add it to the Extension.
   */
  abstract function add();

  public function __construct(Extension $ext, string $name)
  {
    $this->ext = $ext;
    $this->name = $name;
  }

} // Factory

abstract class ClassFactory extends Factory
{
  public $callable;
  public array $options = [];

  /**
   * Get the class name that will be returned.
   */
  abstract function buildClass();

  public function handler(callable $callable): static
  {
    $this->callable = $callable;
    return $this;
  }

  public function method(string $method): static
  {
    return $this->handler([$this->ext, $method]);
  }

  public function variadic(bool $val=true): static
  {
    $this->options['is_variadic'] = $val;
    return $this;
  }

  public function deprecated(string $alternative=null, bool $val=true): static
  {
    $this->options['deprecated'] = $val;
    $this->options['alternative'] = $alternative;
    return $this;
  }

  public function nodeClass($nodeClass): static
  {
    $this->options['node_class'] = $nodeClass;
    return $this;
  }

  public function build()
  {
    $class = $this->buildClass();
    return new $class($this->name, $this->callable, $this->options);
  }

} // ClassFactory

class FunctionFactory extends ClassFactory
{
  public function buildClass()
  {
    return TwigFunction::class;
  }

  public function add($safe=null)
  {
    if (is_array($safe)) 
      $this->safe(...$safe);
    elseif (!is_null($safe))
      $this->safe($safe);
    return $this->_add();
  }

  protected function _add()
  {
    return $this->ext->addFunction($this->build());
  }

  public function safe(...$is_safe): static
  {
    $sc = count($is_safe);
    if ($sc === 0 || ($sc === 1 && $is_safe[0] === true))
      $is_safe = ['all'];
    elseif ($sc === 1 && !$is_safe[0])
      $is_safe = null;
    $this->options['is_safe'] = $is_safe;
    return $this;
  }

  public function safeHandler(callable $callable): static
  {
    $this->options['is_safe_callback'] = $callable;
    return $this;
  }

  public function safeMethod(string $method): static
  {
    return $this->safeHandler([$this->ext, $method]);
  }

  public function needEnv(bool $val=true): static
  {
    $this->options['needs_environment'] = $val;
    return $this;
  }

  public function needContext(bool $val=true): static
  {
    $this->options['needs_context'] = $val;
    return $this;
  }

} // FunctionFactory

class FilterFactory extends FunctionFactory
{
  public function buildClass()
  {
    return TwigFilter::class;
  }

  protected function _add()
  {
    return $this->ext->addFilter($this->build());
  }

  public function preEscape($val): static
  {
    $this->options['pre_escape'] = $val;
    return $this;
  }

  public function preserveSafety($val=true): static
  {
    $this->options['preserves_safety'] = $val;
    return $this;
  }

} // FilterFactory

class TestFactory extends ClassFactory
{
  public function buildClass()
  {
    return TwigTest::class;
  }

  public function add()
  {
    return $this->ext->addTest($this->build());
  }

  public function oneArg($val=true): static
  {
    $this->options['one_mandatory_argument'] = $val;
    return $this;
  }

} // TestFactory

class OpFactory extends Factory
{
  public string $nodeClass;
  public int $precedence = 0;
  public ?string $assClass = null;
  
  public function add()
  {
    $op = ['precedence'=>$this->precedence, 'class'=>$this->nodeClass];

    if (isset($this->assClass))
    { // A binary operator.
      $op['associativity'] = $this->assClass;
      $this->ext->addBinaryOp($this->name, $op);
    }
    else
    {
      $this->ext->addUnaryOp($this->name, $op);
    }

    return $this->ext;
  }

  public function precedence(int $prec): static
  {
    $this->precedence = $prec;
    return $this;
  }

  public function nodeClass(string $class): static
  {
    $this->nodeClass = $class;
    return $this;
  }

  public function left(): static
  {
    $this->assClass = TwigExp::OPERATOR_LEFT;
    return $this;
  }

  public function right(): static
  {
    $this->assClass = TwigExp::OPERATOR_RIGHT;
    return $this;
  }

} // OpFactory

