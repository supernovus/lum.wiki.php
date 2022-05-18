<?php

namespace Lum\Wiki\Interfaces;

use Lum\Wiki\ErrorFlags;

interface Content
{
  /**
   * Get the Unique identifier for the object with content.
   * 
   * @return string 
   */
  function getIdent(): string;

  /**
   * Get the raw content text.
   * 
   * @param int $fatal  `ErrorFlags` for errors that should be fatal.
   * @param int $log   `ErrorFlags` for errors that should be logged.
   * 
   * @return string  The content text in its raw, unrendered source form.
   */
  function getText(int $fatal=ErrorFlags::NONE, int $log=ErrorFlags::ALL): string;

  /**
   * Set the raw content text.
   * 
   * @param string $text  The raw, unrendered source content text to set.
   * @param null|string $file  If the storage has a specific file or URL, it can be set here.
   */
  function setText(string $text, ?string $file=null): void;

  /**
   * Get the raw content text of an include file or document.
   * 
   * @param string $name  The filename or unique identifier of the include document.
   * @param int $fatal  `ErrorFlags` for errors that should be fatal.
   * @param int $log  `ErrorFlags` for errors that should be logged.
   * @return mixed 
   */
  function getInclude(string $name, int $fatal=ErrorFlags::NONE, int $log=ErrorFlags::ALL);
  
}