<?php

namespace PHPJobQueue;

class KeyProviderTest extends \PHPUnit_Framework_TestCase {

    public function testCreateKeyProvider() {
        $provider = new KeyProvider();
        $this->assertInstanceOf('\PHPJobQueue\KeyProvider', $provider);
    }

    public function testGetUniqueKey() {
        $pid = getmypid();
        $provider = new KeyProvider();

        $key = $provider->getUniqueKey();
        $this->assertEquals($pid*$provider::SHM_KEY_SPACE, $key);
    }

    public function testGetMultipleUniqueKeys() {
        $pid = getmypid();
        $provider = new KeyProvider();

        $keys = $provider->getMultipleUniqueKeys(10);
        $this->assertEquals($pid*$provider::SHM_KEY_SPACE, $keys[0]);
        $this->assertEquals(count($keys), 10);

        for ($i = 1; $i < count($keys); $i++) {
            $this->assertGreaterThan($keys[$i-1], $keys[$i]);
            $this->assertTrue(is_numeric($keys[$i]));
        }
    }

}
 