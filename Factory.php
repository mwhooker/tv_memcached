<?php

require_once 'Memcached.php';

class TV_Memcached_Factory {

	private static $_instances = array();

	public static function get($environment) {

		if (!isset($_instances[$environment])) {
			$serverList = self::getServerList($environment);
			$mcd = new TV_Memcached($serverList, $environment);
			$_instances[$environment] = $mcd;
		}

		return $_instances[$environment];
	}

	private static function getServerList($env) {
		$arr = array();
		if (is_numeric($env)) {
			for ($i = 0; $i < $env; ++$i) {
				$arr[] = array('localhost', 11211+$i);
			}
		}

		switch ($env) {
		case 'dev':
			for ($i = 11211; $i <= 11243; ++$i) {
				$arr[] = array('localhost', $i);
			}
			return $arr;
		default:
			return array();
		}
	}
}
