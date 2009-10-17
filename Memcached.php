<?php

class TV_Memcached extends Memcached {
    const DEFAULT_TTL = 3600;

    protected $pre_expire_last_mcd_result = false;
    protected $num_servers = NULL;
    public $perfmon_enabled = false;


    /**
     * __construct 
     * 
     * @param array $serverList which servers to populate the pool with
     * @param string $prefix what prefix 'namespace' to create keys with 
     * @access public
     * @return void
     */
    public function __construct(array $serverList, $prefix = '') {
        parent::__construct();

        if (strlen($prefix)>0) {
            $this->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
        }

        //a very fast hash with good distribution, 
        //but mutually exclusive w/ LIBKETAMA
        //$this->setOption(Memcached::OPT_HASH, Memcached::HASH_MURMUR); 

        $this->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        $this->setOption(Memcached::OPT_DISTRIBUTION, 
            Memcached::DISTRIBUTION_CONSISTENT);

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

        $this->num_servers = count($serverList);
        //@todo if this fails, log it/emit warning.
        $this->addServers($serverList);
    }

    /**
     * getNumServers - how many servers in the pool 
     * 
     * @access public
     * @return int
     */
    public function getNumServers() {
        return $this->num_servers;
    }

    /**
     * retrieve the specified key from cache. 
     *
     * Provides two layers of caching:
     *  1. local in-memory cache
     *  2. Memcached
     * Logs the results to PQP.
     * To prevent stampedes, it provides a probabalistic timeout mechanism.
     * Call TV_Memcached::lastResFound to see if a valid result was
     * returned.
     * ref: http://lists.danga.com/pipermail/memcached/2007-July/004807.html
     * There is a slight race condition if two clients try to expire the cache
     * val at the same time, but the CAS logic will prevent one from clobbering
     * the other. The DB load should be insignificant with our current traffic
     * profile.
     *
     * @author Matthew Hooker <mwhooker@gmail.com>
     * @param $key string cache key. no spaces allowed.
     * @return stored value or false on error. 
     */
    public function tvGet($key) {
        $pqp_start = microtime(true);

        // Try fetching from the registry first 
        $reg = GNE_Registry::getInstance();

        if (gne_is_cnet()) {
            if (isset($_REQUEST['clear_cache']) 
                || isset($_REQUEST['del_cache']) 
                || isset($_REQUEST['del_mc']) 
                || isset($_REQUEST['delmc'])) {
                    $this->delete($key);
                    if ($reg->hasKey($key)) {
                        $reg->remove($key);
                    }
                }
        }


        //caching layer local to the request
        if ($reg->hasKey($key)) {

            $data = $reg->get($key);

            PQP_Console::logCache($key, "registry", $pqp_start, $data);
            return $data;
        } 

        $result = $this->get($key, null, $cas);

        //reset pre-expire token when we retrieve a new value
        $this->pre_expire_last_mcd_result = false;

        if ($this->getResultCode() == Memcached::RES_SUCCESS) {
            //store the cas token to registry. we may need it.
            $reg->set('cas_ctrl:' . $key, $cas);

            //key expires
            if ($result['ttl'] > 0) {

                //how much time is left until value expires
                $time_delta = $result['expire_on'] - time();

                //what percentage are we to reaching the TTL?
                $normal_delta = ($time_delta / $result['ttl']) * 100;

                //the probability of clearing the cache goes to 100 
                //as the delta approaches 0
                $probability = round(100 / (abs($normal_delta) + 1));
                $clear_value = (mt_rand(1, 100) <= $probability);

                //if it already expired or it's turn is up, expire it.
                if ($time_delta < 0 || $clear_value) {
                    $this->preExpireLastRes();
                    PQP_Console::logCache($key, "miss (pre-expirey)", $pqp_start);
                    return false;
                }
            }

            $data = $result['value'];
            PQP_Console::logCache($key, "memcached", $pqp_start, $data);

            $reg->set($key, $data);
            return $data;
        }

        PQP_Console::logCache($key, "miss", $pqp_start);
        return false;
    }

    /**
     * set_cache store the provided value to cache
     *
     * Provides two layers of caching:
     *  1. local in-memory cache
     *  2. Memcached
     *
     * 
     * @author Matthew Hooker <mwhooker@gmail.com>
     * @param string $key 
     * @param mixed $value 
     * @param int $ttl 
     * @access public
     * @return bool
     */
    public function tvSet($key, $value, $ttl = self::DEFAULT_TTL) {
        //ttl shouldn't be nagative
        if (intval($ttl) < 0) {
            self::DEFAULT_TTL;
        }
        //wrap value in the below tuple to facilitate pre-expirey
        $mcd_value = array(
            'value' => $value, 
            'expire_on' => time() + $ttl, 
            'ttl' => intval($ttl));

        //this is supposed to estimate our key distribution.
        //@todo re-enable this using Memcached::getServerByKey
        if ($this->perfmon_enabled && false && $this->getNumServers() > 0) {
            $probe = PerfMon::getProbe('cache');

            $probe->setMessage(array(
                "key" => $key, 
                "assigned_server" => ((crc32($key) >> 16) & 0x7fff) % $this->num_servers, 
                "payload_size" => strlen(serialize($value))));
        }

        // Add to Registry
        $reg = GNE_Registry::getInstance();
        $reg->set($key, $mcd_value['value']);

        //if there's a cas value, we're re-setting the value after a pre-expirey
        $cas_key = 'cas_ctrl:' . $key;
        if (!$reg->hasKey($cas_key)) {
            // we set expirey to 24 hours because we manage expiration internally
            return $this->set($key, $mcd_value, 86400);
        } else {
            //if we've got a cas token, use it in update to prevent clobbering.
            $cas_token = $reg->get($cas_key);
            $reg->remove($cas_key);
            return $this->cas($cas_token, $key, $mcd_value, 86400);
        }
    }

    /**
     * check to see if key exists
     *
     * @param mixed $key
     * @access public
     * @return bool
     */
    public function keyExists($key)
    {
        if ($this->get_cache($key) == false
            && $this->getResultCode() == Memcached::RES_NOTFOUND) 
        {
            return false;
        } else { 
            if ($this->getResultCode() == Memcached::RES_SUCCESS) {
                return true;
            } else {
                //possibly throw error
                return false;
            }
        }
    }

    /**
     * lastResFound 
     * did the last call to TV_Memcached::tvGet return a value? Use
     * for checking falsyness of the returned value.
     *
     * This function respects the anti-stampede mechanism 
     * 
     * @access public
     * @return bool
     */
    public function lastResFound() {
        if ($this->pre_expire_last_mcd_result) {
            return false;
        }
        return ($this->getResultCode() == Memcached::RES_SUCCESS);
    }

    /**
     * preExpireLastRes 
     *
     * 
     * @access protected
     * @return void
     */
    protected function preExpireLastRes()
    {
        if ($this->getResultCode() == Memcached::RES_SUCCESS) {
            $this->pre_expire_last_mcd_result = true;
        }
    }
}


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim: noet sw=4 ts=4
 */

