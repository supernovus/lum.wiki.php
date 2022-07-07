<?php

namespace Lum\Wiki\Parser;

/**
 * Parsedown wrapper class.
 *
 * Prefers ParsedownExtended, but will fall back on ParsedownExtra, and
 * even the basic Parsedown if necessary.
 *
 * At the time I wrote this, ParsedownExtended has a typo in its composer.json
 * which causes its autoloader to fail. While I have a local fork that fixes
 * the issue, until the bug is fixed in the official repository, this library
 * will act as a dynamic wrapper.
 */

if (class_exists('\ParsedownExtended'))
{ // See if ParsedownExtended was autoloaded properly.
  class Parsedown extends \ParsedownExtended 
  {
    public const PARENT_LIB = 'parsedown-extended';
  }
}
elseif (class_exists('\ParsedownExtra')) 
{ // This should always be true, if it isn't something went wrong.
  class Parsedown extends \ParsedownExtra 
  {
    public const PARENT_LIB = 'parsedown-extra';
  }
} 
elseif (class_exists('\Parsedown')) 
{ // Ditto here. If this isn't true, something went *horribly* wrong.
  class Parsedown extends \Parsedown 
  {
    public const PARENT_LIB = 'parsedown';
  }
}
else
{ // If we reached here, your entire dependency tree is screwed.
  throw new \Exception("No parsedown library could be found");
}
