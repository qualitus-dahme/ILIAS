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
 * Class ilEvaluationAllTableGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilEvaluationAllTableGUITest extends ilTestBaseTestCase
{
    private ilEvaluationAllTableGUI $tableGui;
    private ilObjTestGUI $parentObj_mock;

    protected function setUp(): void
    {
        parent::setUp();

        $lng_mock = $this->createMock(ilLanguage::class);
        $lng_mock->expects($this->any())
                 ->method("txt")
                 ->willReturnCallback(function () {
                     return "testTranslation";
                 });

        $ctrl_mock = $this->createMock(ilCtrl::class);
        $ctrl_mock->expects($this->any())
                  ->method("getFormAction")
                  ->willReturnCallback(function () {
                      return "testFormAction";
                  });

        $this->setGlobalVariable("lng", $lng_mock);
        $this->setGlobalVariable("ilCtrl", $ctrl_mock);
        $this->setGlobalVariable("tpl", $this->createMock(ilGlobalPageTemplate::class));
        $this->setGlobalVariable("component.repository", $this->createMock(ilComponentRepository::class));
        $component_factory = $this->createMock(ilComponentFactory::class);
        $component_factory->method("getActivePluginsInSlot")->willReturn(new ArrayIterator());
        $this->setGlobalVariable("component.factory", $component_factory);
        $this->setGlobalVariable("ilDB", $this->createMock(ilDBInterface::class));
        $this->setGlobalVariable("ilSetting", $this->createMock(ilSetting::class));
        $this->setGlobalVariable("rbacreview", $this->createMock(ilRbacReview::class));
        $this->setGlobalVariable("ilUser", $this->createMock(ilObjUser::class));

        $this->parentObj_mock = $this->getMockBuilder(ilObjTestGUI::class)->disableOriginalConstructor()->onlyMethods(array('getObject'))->getMock();
        $this->parentObj_mock->expects($this->any())->method('getObject')->willReturn($this->createMock(ilObjTest::class));
        $this->tableGui = new ilEvaluationAllTableGUI($this->parentObj_mock, "", $this->createMock(ilSetting::class));
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilEvaluationAllTableGUI::class, $this->tableGui);
    }

    public function testNumericOrdering(): void
    {
        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            false,
            false
        );
        $this->assertTrue($tableGui->numericOrdering("reached"));
        $this->assertTrue($tableGui->numericOrdering("hint_count"));
        $this->assertTrue($tableGui->numericOrdering("exam_id"));
        $this->assertFalse($tableGui->numericOrdering("name"));

        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            true,
            true
        );
        $this->assertTrue($tableGui->numericOrdering("reached"));
        $this->assertTrue($tableGui->numericOrdering("hint_count"));
        $this->assertTrue($tableGui->numericOrdering("exam_id"));
        $this->assertTrue($tableGui->numericOrdering("name"));

        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            true,
            false
        );
        $this->assertTrue($tableGui->numericOrdering("reached"));
        $this->assertTrue($tableGui->numericOrdering("hint_count"));
        $this->assertTrue($tableGui->numericOrdering("exam_id"));
        $this->assertTrue($tableGui->numericOrdering("name"));

        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            false,
            true
        );
        $this->assertTrue($tableGui->numericOrdering("reached"));
        $this->assertTrue($tableGui->numericOrdering("hint_count"));
        $this->assertTrue($tableGui->numericOrdering("exam_id"));
        $this->assertFalse($tableGui->numericOrdering("name"));
    }

    public function testGetSelectableColumns()
    {
        $expected = [
            "email" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "institution" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "street" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "city" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "zipcode" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "department" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
            "matriculation" => [
                "txt" => "testTranslation",
                "default" => false,
            ],
        ];

        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            false,
            false
        );

        $this->assertEquals($expected, $tableGui->getSelectableColumns());

        $tableGui = new ilEvaluationAllTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilSetting::class),
            true,
            false
        );

        $this->assertEquals([], $tableGui->getSelectableColumns());
    }

    public function testGetSelectedColumns(): void
    {
        $expected = [];
        $this->assertEquals($expected, $this->tableGui->getSelectedColumns());
    }
}
