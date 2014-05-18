<?php

namespace PHPJobQueue;

class ConfigParserTest extends \PHPUnit_Framework_TestCase {

    public function testParseINI() {
        $configParser = new ConfigParser(__DIR__ . "/jobqueuetest.conf");
        $this->assertTrue($configParser->parseConfiguration());
    }

    public function testParseINIRelative() {
        $configParser = new ConfigParser("../../tests/PHPJobQueue/jobqueuetest.conf");
        $this->assertTrue($configParser->parseConfiguration());
    }

    public function testGettingNumericProperty() {
        $configParser = new ConfigParser(__DIR__ . "/jobqueuetest.conf");
        $configParser->parseConfiguration();

        $value = $configParser->getConfigProperty("numeric", "numeric value", true, "a", false);
        $this->assertTrue(is_numeric($value));
        $this->assertEquals(10, $value);
    }

    public function testGettingNonNumericProperty() {
        $configParser = new ConfigParser(__DIR__ . "/jobqueuetest.conf");
        $configParser->parseConfiguration();

        $value = $configParser->getConfigProperty("nonnumeric", "non-numeric value", false, "testdefault", false);
        $this->assertEquals("test", $value);
    }
}
 