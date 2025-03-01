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

use ILIAS\TestQuestionPool\QuestionInfoService;

/**
 * Class ilTestCorrectionsGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilTestCorrectionsGUITest extends ilTestBaseTestCase
{
    private ilTestCorrectionsGUI $testObj;

    protected function setUp(): void
    {
        global $DIC;

        parent::setUp();

        $this->addGlobal_ilAccess();
        $this->addGlobal_ilCtrl();
        $this->addGlobal_ilDB();
        $this->addGlobal_ilHelp();
        $this->addGlobal_ilTabs();
        $this->addGlobal_http();
        $this->addGlobal_refinery();
        $this->addGlobal_uiFactory();
        $this->addGlobal_uiRenderer();

        $this->testObj = new ilTestCorrectionsGUI(
            $DIC['ilDB'],
            $DIC['ilCtrl'],
            $DIC['ilAccess'],
            $DIC['lng'],
            $DIC['ilTabs'],
            $DIC['ilHelp'],
            $DIC['ui.factory'],
            $DIC['ui.renderer'],
            $DIC['tpl'],
            $DIC['refinery'],
            $DIC->http()->request(),
            $this->createMock(ILIAS\Test\InternalRequestService::class),
            $this->createMock(ilObjTest::class),
            $this->createMock(QuestionInfoService::class)
        );
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilTestCorrectionsGUI::class, $this->testObj);
    }
}
