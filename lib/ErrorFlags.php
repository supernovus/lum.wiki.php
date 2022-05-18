<?php

namespace Lum\Wiki;

final class ErrorFlags
{
  const NONE     = 0;
  const MISSING  = 1;
  const INVALID  = 2;
  const IOERRORS = 4;
  const ALL      = self::MISSING | self::INVALID | self::IOERRORS;
}
