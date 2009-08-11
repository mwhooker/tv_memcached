<?php

require_once 'Memcached.php';

class TV_Memcached_Factory {

	private static $_instances = array();

	public static function getTest($ports) {
		for ($i = 0; $i < $ports; ++$i) {
			$arr[] = array('localhost', 11211+$i);
		}
		return new TV_Memcached($arr, 'test');
	}

	public static function get($environment) {

		if (!isset(self::$_instances[$environment])) {
			$serverList = self::getServerList($environment);
			$mcd = new TV_Memcached($serverList, $environment);
			self::$_instances[$environment] = $mcd;
		}
		return self::$_instances[$environment];
	}

	private static function getServerList($env) {
		$arr = array();

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
