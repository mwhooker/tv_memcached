<?php

require_once 'Factory.php';

$mcd = TV_Memcached_Factory::getTest('16');

define('TO_KEY', 4096);
define('FROM_KEY', 0);

$mcd->flush();

for ($i = FROM_KEY; $i < TO_KEY; ++$i) {
	$mcd->set($i, $i);
}

echo "hit ratio(16): " . get_pct_hit(FROM_KEY, TO_KEY) . "\n";

for ($i = 1; $i <= 32; ++$i) {
	$mcd = TV_Memcached_Factory::getTest($i);
	echo "hit ratio($i): ";
	echo get_pct_hit(FROM_KEY, TO_KEY);
	echo "\n";
}




function get_pct_hit($start, $end) {
	global $mcd;
	$total = $end-$start;
	$found = 0;
	for ($i = $start; $i < $end; ++$i) {
		$mcd->get($i);
		if ($mcd->getResultCode() == Memcached::RES_SUCCESS) {
			++$found;
		} else if ($mcd->getResultCode() != Memcached::RES_NOTFOUND) {
			printf("big problem: %d\n", $mcd->getResultCode());
		}

	}
	return 100*($found/$total) . '%';
}
