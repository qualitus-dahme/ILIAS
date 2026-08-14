<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use BadFunctionCallException;
use DomainException;
use ILIAS\ApiGateway\Activity\ActivityAction;
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\Component\Activities\Activity;
use ILIAS\Data\Description\Description;
use ILIAS\Data\Description\Factory as DescriptionFactory;
use ILIAS\Data\Result;
use ILIAS\UI\Component\Input\Factory as InputFactory;
use Override;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActivityActionTest extends TestCase
{
    private ActivityAction $action;
    private Activity&MockObject $activityMock;
    private InputFactory&MockObject $inputFactoryMock;
    private Result&MockObject $dataResultMock;
    private Description&MockObject $dataDescriptionMock;
    private AuthUser&MockObject $currentUserMock;

    #[Override]
    protected function setUp(): void
    {
        $this->action = new ActivityAction(
            $this->activityMock = $this->createConfiguredMock(Activity::class, [
                'maybePerformAs' => $this->dataResultMock = $this->createMock(Result::class),
                'getOutputDescription' => $this->dataDescriptionMock = $this->createMock(Description::class),
            ]),
            $this->inputFactoryMock = $this->createMock(InputFactory::class),
        );

        $this->currentUserMock = $this->createMock(AuthUser::class);
    }

    public function testPassesAuthenticatedUserIdAndParametersToActivity(): void
    {
        $userId = 123;
        $params = [
            'foo' => 'bar',
        ];

        $this->currentUserMock->expects(self::once())
            ->method('getId')
            ->willReturn($userId);

        $this->activityMock->expects(self::once())
            ->method('maybePerformAs')
            ->with(
                self::identicalTo($this->inputFactoryMock),
                self::equalTo($userId),
                self::equalTo($params),
            );

        $this->dataResultMock->method('isError')->willReturn(false);
        $this->dataResultMock->method('value')->willReturn('result');

        $this->dataDescriptionMock->method('matches')->with('result')->willReturn(true);
        $this->dataDescriptionMock->method('getPrimitiveRepresentation')->with('result')->willReturn('result');

        ($this->action)($params, $this->currentUserMock);
    }

    public function testPassesGuestUserIdAndParametersToActivity(): void
    {
        $params = [
            'id' => 456,
            'foo' => 'bar',
        ];

        $this->activityMock->expects(self::once())
            ->method('maybePerformAs')
            ->with(
                self::identicalTo($this->inputFactoryMock),
                self::equalTo(0),
                self::equalTo($params),
            );

        $this->dataResultMock->method('isError')->willReturn(false);
        $this->dataResultMock->method('value')->willReturn('result');

        $this->dataDescriptionMock->method('matches')->with('result')->willReturn(true);
        $this->dataDescriptionMock->method('getPrimitiveRepresentation')->with('result')->willReturn('result');

        ($this->action)($params, null);
    }

    public function testThrowsExceptionIfPerformResultHasError(): void
    {
        $this->activityMock->expects(self::once())
            ->method('maybePerformAs')
            ->willReturn($this->dataResultMock);

        $this->dataResultMock->expects(self::once())
            ->method('isError')
            ->willReturn(true);

        $this->dataResultMock->expects(self::once())
            ->method('error')
            ->willReturn(new BadFunctionCallException());

        self::expectException(BadFunctionCallException::class);

        ($this->action)([], $this->currentUserMock);
    }

    public function testThrowsExceptionIfPerformResultHasStringError(): void
    {
        $errorMessage = 'error-string';

        $this->dataResultMock->expects(self::once())
            ->method('isError')
            ->willReturn(true);

        $this->dataResultMock->expects(self::once())
            ->method('error')
            ->willReturn($errorMessage);

        self::expectException(DomainException::class);
        self::expectExceptionMessage($errorMessage);

        ($this->action)([], $this->currentUserMock);
    }

    public function testReturnsPrimitiveRepresentationOnSuccess(): void
    {
        $value = 'some-value';
        $primitiveValue = 'serialized-value';

        $this->dataResultMock->expects(self::once())
            ->method('isError')
            ->willReturn(false);

        $this->dataResultMock->expects(self::once())
            ->method('value')
            ->willReturn($value);

        $this->activityMock->expects(self::once())
            ->method('getOutputDescription')
            ->with(self::isInstanceOf(DescriptionFactory::class));

        $this->dataDescriptionMock->expects(self::once())
            ->method('matches')
            ->with($value)
            ->willReturn(true);

        $this->dataDescriptionMock->expects(self::once())
            ->method('getPrimitiveRepresentation')
            ->with($value)
            ->willReturn($primitiveValue);

        $actual = ($this->action)([], null);

        self::assertSame($primitiveValue, $actual);
    }

    public function testThrowsExceptionIfOutputDescriptionDoesNotMatch(): void
    {
        $value = 'invalid-value';

        $this->dataResultMock->expects(self::once())
            ->method('isError')
            ->willReturn(false);

        $this->dataResultMock->expects(self::once())
            ->method('value')
            ->willReturn($value);

        $this->dataDescriptionMock->expects(self::once())
            ->method('matches')
            ->with($value)
            ->willReturn(false);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Output description does not match result.');

        ($this->action)([], null);
    }
}
