<?php

namespace Lum\Wiki\Interfaces;

/**
 * An interface describing the controller base we need.
 *
 * This is meant to be fulfilled as a `Lum\Controllers\Core`
 * subclass with *at least* the `Routes`, `URL`, and `Messages` traits.
 */
interface Controller
{
  function model($modelname=null, $modelopts=[], $loadopts=[]);
  function get_text();
  function get_uri ($page, $params=[]);
  function request_uri();
}