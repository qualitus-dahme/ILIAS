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
use ILIAS\ScormAicc\Activities\HasSCORMCertificateActivity;
use ILIAS\ScormAicc\Services\ScormService;
use PHPUnit\Framework\TestCase;

class HasSCORMCertificateActivityTest extends TestCase
{
    public function testGetTypeReturnsQuery(): void
    {
        $activity = new HasSCORMCertificateActivity(
            $this->createMock(Factory::class),
            $this->createMock(ScormService::class)
        );

        $this->assertSame(ActivityType::Query, $activity->getType());
    }

    public function testIsAllowedToPerformReturnsTrue(): void
    {
        $activity = new HasSCORMCertificateActivity(
            $this->createMock(Factory::class),
            $this->createMock(ScormService::class)
        );

        $this->assertTrue($activity->isAllowedToPerform(99, ['ref_id' => 12, 'usr_id' => 34]));
    }

    public function testPerformReturnsCertificateAvailabilityFromService(): void
    {
        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->once())
            ->method('hasSCORMCertificate')
            ->with(12, 34)
            ->willReturn(true);

        $activity = new HasSCORMCertificateActivity(
            $this->createMock(Factory::class),
            $scorm_service
        );

        $this->assertSame(
            ['has_certificate' => true],
            $activity->perform(['ref_id' => 12, 'usr_id' => 34])
        );
    }

    public function testMaybePerformAsReturnsOkResultForValidParameters(): void
    {
        $result = $this->createMock(Result::class);

        $data_factory = $this->createMock(Factory::class);
        $data_factory->expects($this->once())
            ->method('ok')
            ->with(['has_certificate' => true])
            ->willReturn($result);

        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->once())
            ->method('hasSCORMCertificate')
            ->with(12, 34)
            ->willReturn(true);

        $activity = new HasSCORMCertificateActivity($data_factory, $scorm_service);

        $this->assertSame(
            $result,
            $activity->maybePerformAs(99, ['ref_id' => '12', 'usr_id' => '34'])
        );
    }

    public function testMaybePerformAsReturnsErrorForMissingParameter(): void
    {
        $result = $this->createMock(Result::class);

        $data_factory = $this->createMock(Factory::class);
        $data_factory->expects($this->once())
            ->method('error')
            ->with('Missing parameter: usr_id')
            ->willReturn($result);

        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->never())
            ->method('hasSCORMCertificate');

        $activity = new HasSCORMCertificateActivity($data_factory, $scorm_service);

        $this->assertSame(
            $result,
            $activity->maybePerformAs(99, ['ref_id' => '12'])
        );
    }

    public function testMaybePerformAsReturnsErrorForInvalidIntegerParameter(): void
    {
        $result = $this->createMock(Result::class);

        $data_factory = $this->createMock(Factory::class);
        $data_factory->expects($this->once())
            ->method('error')
            ->with('Parameter must be greater than 0: ref_id')
            ->willReturn($result);

        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->never())
            ->method('hasSCORMCertificate');

        $activity = new HasSCORMCertificateActivity($data_factory, $scorm_service);

        $this->assertSame(
            $result,
            $activity->maybePerformAs(99, ['ref_id' => 0, 'usr_id' => '34'])
        );
    }

    public function testMaybePerformAsReturnsErrorForServiceException(): void
    {
        $result = $this->createMock(Result::class);

        $data_factory = $this->createMock(Factory::class);
        $data_factory->expects($this->once())
            ->method('error')
            ->with('No scorm module found for id: 12')
            ->willReturn($result);

        $scorm_service = $this->createMock(ScormService::class);
        $scorm_service->expects($this->once())
            ->method('hasSCORMCertificate')
            ->with(12, 34)
            ->willThrowException(new OutOfBoundsException('No scorm module found for id: 12'));

        $activity = new HasSCORMCertificateActivity($data_factory, $scorm_service);

        $this->assertSame(
            $result,
            $activity->maybePerformAs(99, ['ref_id' => '12', 'usr_id' => '34'])
        );
    }
}
