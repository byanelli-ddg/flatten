<?php
namespace Flatten;

use \Cache;
use \Event;

class Flatten
{
  private static $lang = null;

  /**
   * Hook Flatten to Laravel's events
   */
  public static function hook()
  {
    // Set cache language
    preg_match_all("#^([a-z]{2})/.+#i", \URI::current(), $language);
    static::$lang = $language[1][0];

    // Get ignored pages
    $ignored = Config::get('ignore');

    // Don't hook if the current page is to be ignored
    if(($ignored and !static::matches($ignored)) or !$ignored) {
      static::load();
      static::save();
    }
  }

  /**
   * Empties the pages in cache
   *
   * @return boolean Whether the operation succeeded or not
   */
  public static function flush($pattern = null)
  {
    $folder = path('storage').'cache'.DS.Config::get('folder');

    // Delete only certain files
    if($pattern) {
      $pattern = str_replace('/', '_', $pattern);
      $files = glob($folder.DS.'*'.$pattern.'*');
      foreach($files as $file) \File::delete($file);

      return sizeof($files);
    }

    return \File::cleandir($folder);
  }

  ////////////////////////////////////////////////////////////////////
  ///////////////////////////// CORE METHODS /////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Load a page from the cache
   */
  private static function load()
  {
    Event::listen(Config::get('hook'), function() {

      // Get page from cache if any
      $cache = Cache::get(static::hash());

      // Render page
      if ($cache) static::render($cache);
    });
  }

  /**
   * Save the current page in the cache
   */
  private static function save()
  {
    Event::listen('laravel.done', function() {

      // Get content from buffer
      $content = ob_get_clean();

      // Cache it
      Cache::forever(static::hash(), $content);

      // Render page
      static::render($content);
    });
  }

  ////////////////////////////////////////////////////////////////////
  ///////////////////////////// HELPERS //////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Get the current page's hash
   *
   * @return string A page hash
   */
  private static function hash($localize = true)
  {
    // Get folder and current page
    $folder = Config::get('folder');
    $page = \URI::current();

    // Localize the cache or not
    if($localize) {
      if(!starts_with($page, static::$lang)) $page = static::$lang.'/'.$page;
    }

    // Slugify and prepend folder
    $page = str_replace('/', '_', $page);
    if($folder) $page = $folder . DS . $page;

    return $page;
  }

  /**
   * Whether the current page matches against an array of pages
   *
   * @param  array $ignored   An array of pages regex
   * @return boolean          Matches or not
   */
  private static function matches($pages)
  {
    if(!$pages) return false;

    $page = \URI::current();
    $pages = implode('|', $pages);
    var_dump('#' .$pages. '#', $page);
    return preg_match('#' .$pages. '#', $page);
  }

  /**
   * Render a content
   *
   * @param  string $content A content to render
   */
  private static function render($content)
  {
    // Set correct header
    header('Content-Type: text/html; charset=utf-8');

    // Display content
    exit($content);
  }

}
