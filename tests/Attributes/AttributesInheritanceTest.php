<?php

namespace Orchestra\Testbench\Tests\Attributes;

use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('fake.parent_attribute', true)]
#[WithConfig('fake.override_attribute', 'parent')]
abstract class AttributesInheritanceTestBaseTestCase extends TestCase
{
    #[BeforeClass]
    public static function defineTestingFeatures()
    {
        static::usesTestingFeature(new WithConfig('fake.override_attribute_2', 'parent'));
    }
}

#[WithConfig('fake.override_attribute', 'child')]
class AttributesInheritanceTest extends AttributesInheritanceTestBaseTestCase
{
    #[BeforeClass]
    public static function defineChildTestingFeatures()
    {
        static::usesTestingFeature(new WithConfig('fake.override_attribute_2', 'child'));
    }

    #[Test]
    public function it_can_see_parent_attributes()
    {
        $this->assertSame(true, config('fake.parent_attribute'));
    }

    #[Test]
    public function it_can_override_parent_attributes()
    {
        $this->assertSame('child', config('fake.override_attribute'));
        $this->assertSame('child', config('fake.override_attribute_2'));
    }
}
