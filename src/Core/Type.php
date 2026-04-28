<?php

declare(strict_types=1);

namespace Retrofit\Core;

use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Strings;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Object_;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Retrofit\Core\Internal\Utils\Utils;

/**
 * Wrapper around PHP types.
 *
 * This class also fills in the gaps of the generic types in PHP using PHPDoc comments syntax.
 *
 * @api
 */
readonly class Type
{
    /** @var list<string> */
    private const SCALARS = ['bool', 'int', 'float', 'string'];

    public function __construct(
        private string $rawType,
        private ?string $parametrizedType = null,
    )
    {
    }

    /**
     * Returns the raw type.
     *
     * This type could be a scalar, array or object (built-in or custom class).
     */
    public function getRawType(): string
    {
        return $this->rawType;
    }

    /**
     * Returns generic type of array.
     *
     * If type is scalar or object returns <code>null</code>.
     */
    public function getParametrizedType(): ?string
    {
        return $this->parametrizedType;
    }

    /**
     * Checks if given type is a
     * {@link https://www.php.net/manual/en/language.types.type-system.php#language.types.type-system.base scalar}.
     */
    public function isScalar(): bool
    {
        return in_array($this->rawType, self::SCALARS);
    }

    /**
     * Checks if type matches provided type.
     */
    public function isA(string $type): bool
    {
        return $this->rawType === $type;
    }

    /**
     * Determines if parametrized (generic) type is an array.
     */
    public function parametrizedTypeIsScalar(): bool
    {
        return !is_null($this->parametrizedType) && in_array($this->parametrizedType, self::SCALARS);
    }

    /**
     * Creates {@link Type} object using reflection and parsed params from PHPDoc.
     *
     * @param list<Tag> $params
     */
    public static function create(
        ReflectionMethod $reflectionMethod,
        ReflectionParameter $reflectionParameter,
        array $params = [],
    ): Type
    {
        $reflectionType = $reflectionParameter->getType();
        if (!$reflectionType instanceof ReflectionNamedType) {
            throw Utils::parameterException($reflectionMethod, $reflectionParameter->getPosition(), 'Cannot detect parameter type name.');
        }

        $rawType = $reflectionType->getName();
        $parametrizedType = self::handleParametrizedTypeForArray($rawType, $reflectionParameter, $reflectionMethod, $params);

        return new Type($rawType, $parametrizedType);
    }

    public function __toString(): string
    {
        return is_null($this->parametrizedType) ? $this->rawType : "{$this->rawType}<{$this->parametrizedType}>";
    }

    /** @param list<Tag> $params */
    private static function handleParametrizedTypeForArray(
        string $rawType,
        ReflectionParameter $reflectionParameter,
        ReflectionMethod $reflectionMethod,
        array $params,
    ): ?string
    {
        if ($rawType !== 'array') {
            return null;
        }

        $parameterName = $reflectionParameter->getName();
        /** @var Param|null $param */
        $param = Arrays::find($params, fn(Param $p): ?Param => $p->getVariableName() === $parameterName ? $p : null);
        if (is_null($param)) {
            return null;
        }

        $type = $param->getType();

        if (!$type instanceof Array_) {
            throw Utils::parameterException($reflectionMethod, $reflectionParameter->getPosition(), 'Parameter is not an array.');
        }

        $paramType = $type->getValueType();
        if ($paramType instanceof Object_) {
            return self::handleParametrizedTypeOfObject($paramType, $reflectionMethod);
        }

        return $paramType->__toString();
    }

    private static function handleParametrizedTypeOfObject(Object_ $paramType, ReflectionMethod $reflectionMethod): string
    {
        $fqsen = $paramType->getFqsen();
        if (is_null($fqsen)) {
            return $paramType->__toString();
        }

        $parametrizedType = $fqsen->getName();
        $fileName = $reflectionMethod->getDeclaringClass()->getFileName();
        if ($fileName === false) {
            $content = Strings::EMPTY;
        } else {
            $content = file_get_contents($fileName);
            if ($content === false) {
                $content = Strings::EMPTY;
            }
        }

        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForHostVersion();
        $stmts = $parser->parse($content);

        if (is_null($stmts)) {
            return Strings::EMPTY;
        }

        $nodeFinder = new NodeFinder();
        $useStmt = $nodeFinder->findFirst($stmts, fn(Node $n): bool => $n instanceof Use_ && $parametrizedType === $n->uses[0]->name->getLast());

        if (!$useStmt instanceof Use_) {
            return Strings::EMPTY;
        }

        return $useStmt->uses[0]->name->toString();
    }
}
