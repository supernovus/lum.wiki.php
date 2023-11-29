<?php

namespace Lum\Wiki\Twig;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * A Node for {% switch %} tags.
 * Pinched from CraftCMS who pinched it from fabpot.
 */
class SwitchNode extends Node
{
  /**
   * @inheritdoc
   */
  public function compile(Compiler $compiler)
  {
    $compiler
      ->addDebugInfo($this)
      ->write('switch (')
      ->subcompile($this->getNode('value'))
      ->raw(") {\n")
      ->indent();

    foreach ($this->getNode('cases') as $case) {
      /** @var Node $case */
      // The 'body' node may have been removed by Twig if it was an empty text node in a sub-template,
      // outside of any blocks
      if (!$case->hasNode('body')) {
        continue;
      }

      foreach ($case->getNode('values') as $value) {
        $compiler
          ->write('case ')
          ->subcompile($value)
          ->raw(":\n");
      }

      $compiler
        ->write("{\n")
        ->indent()
        ->subcompile($case->getNode('body'))
        ->write("break;\n")
        ->outdent()
        ->write("}\n");
    }

    if ($this->hasNode('default')) {
      $compiler
        ->write("default:\n")
        ->write("{\n")
        ->indent()
        ->subcompile($this->getNode('default'))
        ->outdent()
        ->write("}\n");
    }

    $compiler
      ->outdent()
      ->write("}\n");
  }
}
