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

use ILIAS\Component\Activities\ActivityType;
use ILIAS\Data\Factory;
use ILIAS\Data\Result;
use ILIAS\ScormAicc\Activities\HelloSCORMActivity;
use ILIAS\ScormAicc\Services\ScormService;
use PHPUnit\Framework\TestCase;

class HelloSCORMActivityTest extends TestCase
{
    public function testGetTypeReturnsQuery(): void
    {
        $activity = new HelloSCORMActivity(
            $this->createMock(Factory::class),
            $this->createMock(ScormService::class)
        );

        $this->assertSame(ActivityType::Query, $activity->getType());
    }

    public function testIsAllowedToPerformReturnsTrue(): void
    {
        $activity = new HelloSCORMActivity(
            $this->createMock(Factory::class),
            $this->createMock(ScormService::class)
        );

        $this->assertTrue($activity->isAllowedToPerform(99, []));
    }

    public function testPerformReturnsHelloMessageFromService(): void
    {
        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->once())
            ->method('hello')
            ->willReturn('Hello from ScormAicc.');

        $activity = new HelloSCORMActivity(
            $this->createMock(Factory::class),
            $scorm_service
        );

        $this->assertSame(
            ['message' => 'Hello from ScormAicc.'],
            $activity->perform([])
        );
    }

    public function testMaybePerformAsReturnsOkResult(): void
    {
        $result = $this->createMock(Result::class);

        $data_factory = $this->createMock(Factory::class);
        $data_factory->expects($this->once())
            ->method('ok')
            ->with(['message' => 'Hello from ScormAicc.'])
            ->willReturn($result);

        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->once())
            ->method('hello')
            ->willReturn('Hello from ScormAicc.');

        $activity = new HelloSCORMActivity($data_factory, $scorm_service);

        $this->assertSame($result, $activity->maybePerformAs(99, []));
    }
}
