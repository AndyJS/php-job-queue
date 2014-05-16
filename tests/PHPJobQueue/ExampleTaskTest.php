<?php

namespace PHPJobQueue;


class ExampleTaskTest extends \PHPUnit_Framework_TestCase {

    public function testROT13Process() {
        $task = new ExampleTask();
        $data = "abcdefghijklmnopqrstuvwxyz";

        $this->assertInstanceOf('\PHPJobQueue\ExampleTask', $task);
        $this->assertEquals('nopqrstuvwxyzabcdefghijklm', $task->process($data));
    }

}
 