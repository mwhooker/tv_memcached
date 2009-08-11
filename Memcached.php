<?php

class TV_Memcached extends Memcached {

	public function __construct(array $serverList, $prefix = '') {
		parent::__construct();

		if (strlen($prefix)>0) {
			$this->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
		}
		
		//a very fast hash with good distribution, 
		//but mutually exclusive w/ LIBKETAMA
		//$this->setOption(Memcached::OPT_HASH, Memcached::HASH_MURMUR); 
		
		$this->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
		
		//$this->setOption(Memcached::OPT_DISTRIBUTION, 
		//	Memcached::DISTRIBUTION_CONSISTENT);
		$this->setOption(Memcached::OPT_DISTRIBUTION,
			Memcached::DISTRIBUTION_MODULA);
		
		//Enables asynchronous I/O.
		$this->setOption(Memcached::OPT_NO_BLOCK, true);

		//this caused the application to slow down dramatically
		//$this->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

		//Enables or disables caching of DNS lookups.
		$this->setOption(Memcached::OPT_CACHE_LOOKUPS, true);

		//Specifies the failure limit for server connection attempts. 
		//The server will be removed after this many continuous connection 
		//failures.
		$this->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 5);
		
		//if we have the igbinary serializer, use it
		if (Memcached::HAVE_IGBINARY) {
			$this->setOption(Memcached::OPT_SERIALIZER, 
				Memcached::SERIALIZER_IGBINARY);
		}

		//@todo if this fails, log it/emit warning.
		$this->addServers($serverList);
	}
}




/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim: noet sw=4 ts=4
 */

