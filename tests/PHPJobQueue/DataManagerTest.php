<?php

namespace PHPJobQueue;

class DataManagerTest extends \PHPUnit_Framework_TestCase {

    public function testCheckNoPendingData() {
        $dataManager = new DataManager();

        $dataIndex = $dataManager->checkPendingData();
        $this->assertEquals(-1, $dataIndex);
    }

    public function testCheckPendingData() {
        $dataManager = new DataManager();
        $newIndex = $dataManager->addDataToPool(new DataItem("testdata"));

        $dataIndex = $dataManager->checkPendingData();
        $this->assertNotEquals(-1, $dataIndex);
        $this->assertEquals($newIndex, $dataIndex);
        $this->assertInstanceOf('\PHPJobQueue\DataItem', $dataManager->getDataItem($dataIndex));
    }

    public function testGetDataItem() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $this->assertGreaterThanOrEqual(0, $newIndex);
        $returnedDataItem = $dataManager->getDataItem($newIndex);
        $this->assertInstanceOf('\PHPJobQueue\DataItem', $returnedDataItem);
        $this->assertEquals($returnedDataItem->getData(), $dataItem->getData());
    }

    public function testGetNoAllocatedData() {
        $dataManager = new DataManager();

        $this->assertFalse($dataManager->getDataItem(0));
    }

    public function testGetAllocatedData() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("14096", $newIndex);

        $returnedDataItem = $dataManager->getAllocatedDataItem("14096");
        $this->assertInstanceOf('\PHPJobQueue\DataItem', $returnedDataItem);
        $this->assertEquals($returnedDataItem->getData(), $dataItem->getData());
    }

    public function testAllocatePID() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("14096", $newIndex);

        $this->assertFalse($dataManager->getDataItem($newIndex));

        $returnedDataItem = $dataManager->getAllocatedDataItem("14096");
        $this->assertEquals($returnedDataItem->getData(), $dataItem->getData());
    }

    public function testFreeAllocatedItem() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("14096", $newIndex);

        $this->assertTrue($dataManager->freeAllocatedDataItem("14096"));
        $this->assertFalse($dataManager->getAllocatedDataItem("14096"));
        $this->assertNotEquals(-1, $dataManager->checkPendingData());
    }

    public function testHasNoAllocation() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("3234", $newIndex);

        $this->assertFalse($dataManager->hasAllocation("14096"));
    }

    public function testHasAllocation() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("14096", $newIndex);

        $this->assertTrue($dataManager->hasAllocation("14096"));
    }

    public function testClearNoCompletedItem() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("3234", $newIndex);
        $this->assertFalse($dataManager->clearCompletedDataItem("14096"));
        $this->assertTrue($dataManager->hasAllocation("3234"));
    }

    public function testClearCompletedItem() {
        $dataManager = new DataManager();
        $dataItem = new DataItem("testdata");
        $newIndex = $dataManager->addDataToPool($dataItem);

        $dataManager->allocatePIDToDataItem("14096", $newIndex);

        $this->assertTrue($dataManager->clearCompletedDataItem("14096"));
        $this->assertFalse($dataManager->hasAllocation("14096"));
    }

}
 