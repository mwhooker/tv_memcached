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
		switch ($env) {
		case 'dev':
			return array();
		default:
			return array();
		}
	}
}
