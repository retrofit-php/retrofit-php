<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal;

use Ouzo\Utilities\Strings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Retrofit\Core\Internal\BuiltInConverters;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use stdClass;

class BuiltInConvertersTest extends TestCase
{
    #[Test]
    public function toStringConverterShouldConvertNullToEmptyString(): void
    {
        // given
        $converter = BuiltInConverters::ToStringConverter();

        // when
        $value = $converter->convert(null);

        // then
        $this->assertSame(Strings::EMPTY, $value);
    }

    #[Test]
    #[TestWith([true, 'true'])]
    #[TestWith([false, 'false'])]
    public function toStringConverterShouldConvertBoolToString(bool $bool, string $string): void
    {
        // given
        $converter = BuiltInConverters::ToStringConverter();

        // when
        $value = $converter->convert($bool);

        // then
        $this->assertSame($string, $value);
    }

    #[Test]
    public function toStringConverterShouldConvertArrayToSerializedValue(): void
    {
        // given
        $converter = BuiltInConverters::ToStringConverter();

        $array = ['one', 'two', 'three'];

        // when
        $value = $converter->convert($array);

        // then
        $serialize = serialize($array);
        $this->assertSame($serialize, $value);
    }

    #[Test]
    public function toStringConverterShouldConvertStdObjectToSerializedValue(): void
    {
        // given
        $converter = BuiltInConverters::ToStringConverter();

        $obj = new stdClass();
        $obj->one = 'value for one';
        $obj->two = 2;

        // when
        $value = $converter->convert($obj);

        // then
        $serialize = serialize($obj);
        $this->assertSame($serialize, $value);
    }

    #[Test]
    public function toStringConverterShouldConvertCustomObjectToSerializedValue(): void
    {
        // given
        $converter = BuiltInConverters::ToStringConverter();

        $userRequest = new UserRequest()
            ->setLogin('jon_doe');

        // when
        $value = $converter->convert($userRequest);

        // then
        $serialize = serialize($userRequest);
        $this->assertSame($serialize, $value);
    }
}
