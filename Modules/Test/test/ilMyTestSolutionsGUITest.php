<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * Class ilMyTestSolutionsGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilMyTestSolutionsGUITest extends ilTestBaseTestCase
{
    private ilMyTestSolutionsGUI $testObj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testObj = new ilMyTestSolutionsGUI();
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilMyTestSolutionsGUI::class, $this->testObj);
    }

    public function testTestObj(): void
    {
        $obj_mock = $this->createMock(ilObjTest::class);
        $this->testObj->setTestObj($obj_mock);

        $this->assertEquals($obj_mock, $this->testObj->getTestObj());
    }

    public function testTestAccess(): void
    {
        $obj_mock = $this->createMock(ilTestAccess::class);
        $this->testObj->setTestAccess($obj_mock);

        $this->assertEquals($obj_mock, $this->testObj->getTestAccess());
    }

    public function testObjectiveParent(): void
    {
        $obj_mock = $this->createMock(ilTestObjectiveOrientedContainer::class);
        $this->testObj->setObjectiveParent($obj_mock);

        $this->assertEquals($obj_mock, $this->testObj->getObjectiveParent());
    }
}
