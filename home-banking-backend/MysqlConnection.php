<?php
class MysqlConnection {
  private static $instance = null;
  private function __construct() {}

  public static function getInstance() {
    if (!isset(self::$instance)) {
      self::$instance = new MySQLi('localhost', 'root', '', 'banking');
    }
      return self::$instance;
    }

  function __clone() {}
}
