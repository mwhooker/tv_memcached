<?php

require_once 'Memcached.php';

class TV_Memcached_Factory {

	private static $_instances = array();

	public static function get($environment = 'default') {

		if (!isset(self::$_instances[$environment])) {
			$serverList = self::getServerList();
            $mcd = new TV_Memcached($serverList, $environment);
			self::$_instances[$environment] = $mcd;
		}
		return self::$_instances[$environment];
	}

	private static function getServerList() {
        $memcache_list_file = $GLOBALS['GNE_SETTINGS']['GNE']['MEMCACHED_LIST'];
        
        $mcd_list = file_get_contents($memcache_list_file);
        $servers=explode(",", str_replace("'",'', $mcd_list));
        foreach ($servers as $k=>$v) {
            $servers[$k] = explode(':', trim($v));
        }
        return $servers;
	}
    
}
