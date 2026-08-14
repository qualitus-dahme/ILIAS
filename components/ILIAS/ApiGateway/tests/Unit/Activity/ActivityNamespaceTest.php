<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use ILIAS\ApiGateway\Activity\ActivityNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityNamespaceTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testGeneratesCorrectPath(
        string $vendor,
        string $component,
        string $name,
        string $expectedPath,
    ): void {
        $namespace = new ActivityNamespace($vendor, $component, $name);

        self::assertEquals($expectedPath, $namespace->getPath());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function pathProvider(): array
    {
        return [
            'basic_activity_name' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'MyActivity',
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'name_with_query_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'QueryMy',
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'name_with_get_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'GetMy',
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'name_with_queryget_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'QueryGetMy',
                'expectedPath' => '/myvendor/mycomponent/getmy',
            ],
            'name_with_getquery_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'GetQueryMy',
                'expectedPath' => '/myvendor/mycomponent/querymy',
            ],
            'get_in_the_middle_should_stay' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'MyGetActivity',
                'expectedPath' => '/myvendor/mycomponent/myget',
            ],
            'query_in_the_middle_should_stay' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'MyQueryActivity',
                'expectedPath' => '/myvendor/mycomponent/myquery',
            ],
            'multiple_query_should_only_remove_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'QueryMyQueryActivity',
                'expectedPath' => '/myvendor/mycomponent/myquery',
            ],
            'activity_prefix_should_stay' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'ActivityMyActivity',
                'expectedPath' => '/myvendor/mycomponent/activitymy',
            ],
            'multiple_activity_prefix_should_stay' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'MyActivityActivity',
                'expectedPath' => '/myvendor/mycomponent/myactivity',
            ],
            'double_activity_should_stay' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'ActivityActivity',
                'expectedPath' => '/myvendor/mycomponent/activity',
            ],
            'activity_suffix_should_be_omitted' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'MyQueryActivity',
                'expectedPath' => '/myvendor/mycomponent/myquery',
            ],
            'name_with_lowercase_query_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'queryMyActivity', // ucfirst makes it QueryActivity, then str_replace removes Query
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'name_with_lowercase_get_prefix' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'getMyActivity',
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'name_with_uppercase' => [
                'vendor' => 'MyVendor',
                'component' => 'MyComponent',
                'name' => 'My', // ucfirst, then strtolower
                'expectedPath' => '/myvendor/mycomponent/my',
            ],
            'empty_vendor_and_component' => [
                'vendor' => '',
                'component' => '',
                'name' => 'MyActivity',
                'expectedPath' => '/my',
            ],
            'name_is_just_query' => [
                'vendor' => 'Vendor',
                'component' => 'Component',
                'name' => 'Query',
                'expectedPath' => '/vendor/component', // ucfirst makes it Query, then str_replace removes Query
            ],
            'name_is_just_get' => [
                'vendor' => 'Vendor',
                'component' => 'Component',
                'name' => 'Get',
                'expectedPath' => '/vendor/component',
            ],
            'name_is_empty' => [
                'vendor' => 'Vendor',
                'component' => 'Component',
                'name' => '',
                'expectedPath' => '/vendor/component', // ucfirst('') is '', str_replace('Query', '', '') is ''
            ],
            'vendor_is_ilias_should_be_omitted' => [
                'vendor' => 'ILIAS',
                'component' => 'MyComponent',
                'name' => 'MyActivity',
                'expectedPath' => '/mycomponent/my',
            ],
            'vendor_is_ilias_case_insensitive_should_be_omitted' => [
                'vendor' => 'ilias',
                'component' => 'MyComponent',
                'name' => 'MyActivity',
                'expectedPath' => '/mycomponent/my',
            ],
        ];
    }
}
