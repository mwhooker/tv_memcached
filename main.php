<?php

class TV_Memcached extends Memcached {

	public function construct(array $serverList, $prefix = '') {
		if (strlen($prefix)>0) {
			$this->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
		}
		
		//a very fast hash with good distribution, but mutually exclusive w/ LIBKETAMA
		//$this->setOption(Memcached::OPT_HASH, Memcached::HASH_MURMUR); 
		
		$this->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
		
		$this->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
		
		//Enables asynchronous I/O.
		$this->setOption(Memcached::OPT_NO_BLOCK, true);

		$this->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

		//Enables or disables caching of DNS lookups.
		$this->setOption(Memcached::OPT_CACHE_LOOKUPS, true);

		//Specifies the failure limit for server connection attempts. 
		//The server will be removed after this many continuous connection failures.
		$this->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 5);
		
		//if we have the igbinary serializer, use it
		if (Memcached::HAVE_IGBINARY) {
			$this->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY);
		}
	}
}


$this = new Memcached();

define('TO_KEY', 4096);
define('FROM_KEY', 0);

for ($port = 11211; $port<= 11242; ++$port) {
		echo "adding server 127.0.0.1:$port\n";
		$res = $this->addServer('127.0.0.1', $port);
		$found_pct = get_pct_hit(FROM_KEY, TO_KEY);
		echo $found_pct . "\n";

		if ((($port-11211) %1)==0) {


				echo "setting again...\n";

				for ($i = FROM_KEY; $i < TO_KEY; ++$i) {
						$this->set($i, $i);
				}
				$found_pct = get_pct_hit(FROM_KEY, TO_KEY);
				echo $found_pct . "\n";
		}
		echo "\n";
}

die;


function get_pct_hit($start, $end) {
	global $this;
	$total = $end-$start;
	$found = 0;
	for ($i = $start; $i < $end; ++$i) {
		$this->get($i);
		if ($this->getResultCode() !== Memcached::RES_NOTFOUND) {
			++$found;
		}
	}
	return 100*($found/$total) . '%';
}

function get_val($i) {
	global $this;

	$key = "val:$i";
	$val = $this->get($key);

	if ($val === false) {
		$val = $i;
		$this->add($key, $val, 3600);
	}
	return $val;
}

/*
for ($port = 11211; $port<= 11220; ++$port) {
	$res = $this->addServer('127.0.0.1', $port);
}



while (1) {
	for ($x = 0; $x<10; ++$x) {
		get_val($x);
	}
	sleep(5);
}
exit;
*/

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim: noet sw=4 ts=4
 */

