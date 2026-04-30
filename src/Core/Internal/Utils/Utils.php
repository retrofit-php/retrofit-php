<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\Utils;

use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\FluentArray;
use Ouzo\Utilities\Joiner;
use Ouzo\Utilities\Strings;
use ReflectionMethod;
use ReflectionParameter;
use Retrofit\Core\Attribute\Url;
use RuntimeException;
use TRegx\CleanRegex\Match\Detail;

/**
 * Convenient utils for repeatable things.
 *
 * @internal
 */
readonly class Utils
{
    private const string NAMESPACE_DELIMITER = '\\';

    private const string PARAM_URL_REGEX = '\{([a-zA-Z][a-zA-Z0-9_-]*)\}';

    private function __construct()
    {
    }

    /**
     * Transforms {@code $names} to the valid FQCN (Full Qualified Class Name) with leading namespace delimiter.
     */
    public static function toFQCN(string ...$names): string
    {
        return Joiner::on(Strings::EMPTY)
            ->mapValues(fn(string $name): string => str_starts_with($name, self::NAMESPACE_DELIMITER) ? $name : (self::NAMESPACE_DELIMITER . $name))
            ->join($names);
    }

    /**
     * Creates an exception with the message which contains a detailed info about method where an error occurs.
     */
    public static function methodException(ReflectionMethod $reflectionMethod, string $message): RuntimeException
    {
        $methodExceptionMessage = self::methodExceptionMessage($reflectionMethod);
        return new RuntimeException("{$methodExceptionMessage}. {$message}");
    }

    /**
     * Creates exception with message which contains a detailed info about method and parameter number where error occurs.
     */
    public static function parameterException(ReflectionMethod $reflectionMethod, int $position, string $message): RuntimeException
    {
        $methodExceptionMessage = self::methodExceptionMessage($reflectionMethod);
        $position += 1;
        return new RuntimeException("{$methodExceptionMessage} parameter #{$position}. {$message}");
    }

    /**
     * Gets the set of unique path parameters used in the given URI. If a parameter is used twice in the URI, it will
     * only show up once in the set.
     *
     * @return list<string>
     */
    public static function parsePathParameters(?string $path): array
    {
        if (is_null($path)) {
            return [];
        }

        $matcher = pattern(self::PARAM_URL_REGEX)
            ->match($path);

        /** @var list<string> */
        return FluentArray::from(iterator_to_array($matcher))
            ->map(fn(Detail $detail) => $detail->get(1))
            ->unique()
            ->toArray();
    }

    /**
     * Sorts parameters using their priorities.
     *
     * @param list<ReflectionParameter> $reflectionParameters
     * @return list<ReflectionParameter>
     */
    public static function sortParameterAttributesByPriorities(array $reflectionParameters): array
    {
        /** @var array<class-string, int> $attributeToPriority */
        static $attributeToPriority = [
            Url::class => 1,
        ];
        static $defaultNoPriorityFactor = 1_000;

        /** @var list<ReflectionParameter> */
        return Arrays::sort($reflectionParameters, function (ReflectionParameter $a, ReflectionParameter $b) use ($attributeToPriority, $defaultNoPriorityFactor): int {
            $aPriority = $attributeToPriority[$a->getAttributes()[0]->getName()] ?? $defaultNoPriorityFactor;
            $bPriority = $attributeToPriority[$b->getAttributes()[0]->getName()] ?? $defaultNoPriorityFactor;
            return $aPriority <=> $bPriority;
        });
    }

    private static function methodExceptionMessage(ReflectionMethod $reflectionMethod): string
    {
        $className = $reflectionMethod->getDeclaringClass()->getShortName();
        $methodName = $reflectionMethod->getShortName();
        return "Method {$className}::{$methodName}()";
    }
}
