<?php

namespace Lum\Wiki\Twig;

class FormattingExtension extends Extension
{
  const NAME = 'formatting';

  protected function setup(array $opts) 
  { 
    $this
      ->filter('markdown')->method('parseMarkdown')->add(true)
      ->filter('textile')->method('parseTextile')->add(true);
  }
 
  public function parseMarkdown(string $text): string
  {
    return $this->parser->parsedown->text($text);
  }

  public function parseTextile(string $text): string
  {
    return $this->parser->textile->parse($text);
  }
  
}