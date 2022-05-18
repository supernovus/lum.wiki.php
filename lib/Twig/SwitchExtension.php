<?php

namespace Lum\Wiki\Twig;

class SwitchExtension extends Extension
{
  const NAME = 'switch';
  
  public function setup(array $opts)
  {
    $this->tag(new SwitchTokenParser());
  }

} // class Lum\Wiki\Twig\SwitchExtension
