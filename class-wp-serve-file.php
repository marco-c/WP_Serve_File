<?php

if (!class_exists('WP_Serve_File')) {

class WP_Serve_File {
  private static $instance;
  private $files = array();

  public function __construct() {
    add_action('wp_ajax_wpservefile', array($this, 'serve_file'));
    add_action('wp_ajax_nopriv_wpservefile', array($this, 'serve_file'));
  }

  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  private function regenerate_file($name) {
    $generatorFunc = $this->files[$name];
    if (!$generatorFunc) {
      // The file isn't registered.
      return null;
    }

    $file = call_user_func($generatorFunc);
    if (empty($file['lastModified'])) {
      $file['lastModified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
    }

    set_transient('wpservefile_files_' . $name, $file, YEAR_IN_SECONDS);

    return $file;
  }

  public function serve_file() {
    $name = $_GET['wpservefile_file'];

    $file = get_transient('wpservefile_files_' . $name);
    if (empty($file)) {
      $file = $this->regenerate_file($name);
      if (empty($file)) {
        return;
      }
    }

    $content = $file['content'];
    $contentType = $file['contentType'];
    $lastModified = $file['lastModified'];

    $maxAge = DAY_IN_SECONDS;
    $etag = md5($lastModified);

    if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= strtotime($lastModified)) ||
        (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)) {
      header('HTTP/1.1 304 Not Modified');
      exit;
    }

    header('HTTP/1.1 200 OK');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    header('Cache-Control: max-age=' . $maxAge . ', public');
    header('Last-Modified: ' . $lastModified);
    header('ETag: ' . $etag);
    header('Pragma: cache');
    header('Content-Type: ' . $contentType);
    echo $content;
    wp_die();
  }

  public function add_file($name, $generatorFunc) {
    $this->files[$name] = $generatorFunc;
  }

  public function invalidate_files($names) {
    foreach ($names as $name) {
      $this->regenerate_file($name);
    }
  }

  public static function get_relative_to_host_root_url($name) {
    return admin_url('admin-ajax.php', 'relative') . '?action=wpservefile&wpservefile_file=' . $name;
  }

  public static function get_relative_to_wp_root_url($name) {
    $url = self::get_relative_to_host_root_url($name);
    $site_url = site_url('', 'relative');
    if (substr($url, 0, strlen($site_url)) === $site_url) {
      $url = substr($url, strlen($site_url));
    }

    return $url;
  }
}

}

?>
