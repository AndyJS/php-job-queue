<?php

namespace PHPJobQueue;


class ExampleTaskTwoTest extends \PHPUnit_Framework_TestCase {

    public function testAppendStringProcess() {
        $task = new ExampleTaskTwo();
        $data = "test string";

        $this->assertInstanceOf('\PHPJobQueue\ExampleTaskTwo', $task);
        $this->assertEquals("test string\n[This data parsed by ExampleTaskTwo]", $task->process($data));
    }

}

 