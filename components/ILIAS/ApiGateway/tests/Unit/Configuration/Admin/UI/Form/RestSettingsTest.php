<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration\Admin\UI\Form;

use ILIAS\ApiGateway\Configuration\Admin\SettingsService;
use ILIAS\ApiGateway\Configuration\Admin\UI\Form\RestSettings;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Container\Form\Factory as FormFactory;
use ILIAS\UI\Component\Input\Container\Factory as ContainerFactory;
use ILIAS\UI\Component\Input\Factory as InputFactory;
use ILIAS\UI\Component\Input\Field\Checkbox;
use ILIAS\UI\Component\Input\Field\Numeric;
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\UI\Component\Input\Container\Form\Standard;
use ilCtrl;
use ilLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RestSettings::class)]
final class RestSettingsTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private ilCtrl&MockObject $ctrl;
    private ilLanguage&MockObject $lng;
    private UIFactory&MockObject $ui_factory;
    private RestSettings $form;

    #[\Override]
    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->ctrl = $this->createMock(ilCtrl::class);
        $this->lng = $this->createMock(ilLanguage::class);
        $this->ui_factory = $this->createMock(UIFactory::class);

        $this->form = new RestSettings(
            $this->settings,
            $this->ctrl,
            $this->lng,
            $this->ui_factory
        );
    }

    public function testGetReturnsStandardFormWithExpectedInputs(): void
    {
        $action = "http://form-action";

        $inputFactory = $this->createMock(InputFactory::class);
        $fieldFactory = $this->createMock(FieldFactory::class);
        $containerFactory = $this->createMock(ContainerFactory::class);
        $containerFormFactory = $this->createMock(FormFactory::class);

        $this->ui_factory->method('input')->willReturn($inputFactory);
        $inputFactory->method('field')->willReturn($fieldFactory);
        $inputFactory->method('container')->willReturn($containerFactory);
        $containerFactory->method('form')->willReturn($containerFormFactory);

        $checkboxMock = $this->createMock(Checkbox::class);
        $numericMock = $this->createMock(Numeric::class);
        $sectionMock = $this->createMock(Section::class);
        $formMock = $this->createMock(Standard::class);

        $fieldFactory->method('checkbox')->willReturn($checkboxMock);
        $fieldFactory->method('numeric')->willReturn($numericMock);
        $fieldFactory->method('section')->willReturn($sectionMock);
        $containerFormFactory->method('standard')->willReturn($formMock);

        $checkboxMock->method('withValue')->willReturn($checkboxMock);
        $numericMock->method('withValue')->willReturn($numericMock);

        $this->settings->method('getData')->willReturn('1');
        $this->lng->method('txt')->willReturn('translated');

        $actual = $this->form->get($action);

        self::assertSame($formMock, $actual);
    }
}
