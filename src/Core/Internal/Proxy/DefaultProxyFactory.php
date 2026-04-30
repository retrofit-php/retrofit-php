<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\Proxy;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Namespace_;
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param as NodeParam;
use PhpParser\Node\Scalar\MagicConst\Function_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinterAbstract;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Retrofit\Core\Call;
use Retrofit\Core\Internal\ParameterHandler\Factory\ParameterHandlerFactoryProvider;
use Retrofit\Core\Internal\ServiceMethod;
use Retrofit\Core\Internal\ServiceMethodFactory;
use Retrofit\Core\Internal\Utils\Utils;
use Retrofit\Core\Retrofit;
use Override;

/**
 * Creates an proxy which implements all of the methods from the service interface.
 *
 * In the constructor of an interface implementation a {@link Retrofit} object is injected. Each of the implemented methods
 * calls {@link ServiceMethodFactory::create()} method to create a {@link ServiceMethod} implementation with required
 * details - parsed and validated attributes. This created method is immediately invoked with passed all arguments.
 *
 * Example (pseudo-code):
 * <pre>
 * namespace Retrofit\Proxy\Retrofit\Tests\Fixtures;
 *
 * readonly class SomeApiImpl implements \Retrofit\Tests\Fixtures\SomeApi
 * {
 *      private \Retrofit\Internal\ServiceMethodFactory $serviceMethodFactory;
 *
 *      public function __construct(\Retrofit\Retrofit $retrofit)
 *      {
 *          $this->serviceMethodFactory = new \Retrofit\Internal\ServiceMethodFactory($retrofit);
 *      }
 *
 *      #[\Retrofit\Attribute\GET('/users/{id}')]
 *      public function getUser(#[\Retrofit\Attribute\Path('id')] int $id): \Retrofit\Call
 *      {
 *          return $this->serviceMethodFactory->create('\\Retrofit\\Tests\\Fixtures\\SomeApi', __FUNCTION__)->invoke(func_get_args());
 *      }
 * }
 * </pre>
 *
 * @internal
 */
readonly class DefaultProxyFactory implements ProxyFactory
{
    private const string SERVICE_IMPLEMENTATION_NAMESPACE_PREFIX = 'Retrofit\Proxy\\';

    private const string SERVICE_IMPLEMENTATION_CLASS_SUFFIX = 'Impl';

    public function __construct(
        private BuilderFactory $builderFactory,
        private PrettyPrinterAbstract $prettyPrinterAbstract,
    )
    {
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $service
     * @return object
     */
    #[Override]
    public function create(Retrofit $retrofit, ReflectionClass $service): object
    {
        $proxyServiceNamespace = self::SERVICE_IMPLEMENTATION_NAMESPACE_PREFIX . $service->getNamespaceName();
        $proxyServiceClassName = $service->getShortName() . self::SERVICE_IMPLEMENTATION_CLASS_SUFFIX;
        $proxyServiceFQCN = Utils::toFQCN($proxyServiceNamespace, $proxyServiceClassName);

        if (!class_exists($proxyServiceFQCN, false)) {
            $serviceClassImplementation = $this->serviceClassImplementation($service, $proxyServiceClassName);
            $this->appendServiceMethodFactoryProperty($serviceClassImplementation);
            $this->appendConstructor($serviceClassImplementation);
            $this->appendMethods($service, $serviceClassImplementation);
            $serviceClassImplementationInNamespace = $this->wrapInNamespace($proxyServiceNamespace, $serviceClassImplementation);

            $proxyServiceClass = $this->prettyPrinterAbstract->prettyPrint([$serviceClassImplementationInNamespace->getNode()]);
            eval($proxyServiceClass);
        }

        return new $proxyServiceFQCN($retrofit);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $service
     */
    private function serviceClassImplementation(ReflectionClass $service, string $proxyServiceName): Class_
    {
        $serviceFQCN = Utils::toFQCN($service->getName());
        return $this->builderFactory
            ->class($proxyServiceName)
            ->implement($serviceFQCN)
            ->makeReadonly();
    }

    private function appendServiceMethodFactoryProperty(Class_ $serviceClassImplementation): void
    {
        $property = $this->builderFactory->property('serviceMethodFactory')
            ->makePrivate()
            ->setType(Utils::toFQCN(ServiceMethodFactory::class));
        $serviceClassImplementation->addStmt($property->getNode());
    }

    private function appendConstructor(Class_ $serviceClassImplementation): void
    {
        $retrofitParameter = $this->builderFactory
            ->param('retrofit')
            ->setType(Utils::toFQCN(Retrofit::class));

        $serviceMethodFactoryProperty = new PropertyFetch(new Variable('this'), 'serviceMethodFactory');
        $serviceMethodFactoryInstance = new New_(
            new Name(Utils::toFQCN(ServiceMethodFactory::class)),
            [
                new Arg(new Variable('retrofit')),
                new Arg(
                    new New_(
                        new Name(Utils::toFQCN(ParameterHandlerFactoryProvider::class)),
                        [
                            new Arg(new PropertyFetch(new Variable('retrofit'), 'converterProvider')),
                        ],
                    ),
                ),
            ],
        );
        $assign = new Assign($serviceMethodFactoryProperty, $serviceMethodFactoryInstance);

        $constructor = $this->builderFactory
            ->method('__construct')
            ->makePublic()
            ->addParam($retrofitParameter->getNode())
            ->addStmt($assign);

        $serviceClassImplementation->addStmt($constructor->getNode());
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $service
     */
    private function appendMethods(ReflectionClass $service, Class_ $serviceClassImplementation): void
    {
        $serviceMethodInvokeReturnStmt = $this->createServiceMethodInvokeReturnStmt($service);

        $methods = $service->getMethods();
        foreach ($methods as $method) {
            $this->validateMethodReturnType($method);

            $serviceClassMethodImplementation = $this->builderFactory
                ->method($method->getName())
                ->makePublic();

            $this->appendAttributes($method->getAttributes(), $serviceClassMethodImplementation);

            $methodParameters = $this->appendMethodParameters($method);
            $serviceClassMethodImplementation->addParams($methodParameters);

            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) {
                throw Utils::methodException($method, 'Cannot detect return type name.');
            }

            $serviceClassMethodImplementation->setReturnType(Utils::toFQCN($returnType->getName()));
            $serviceClassMethodImplementation->addStmt(new Return_($serviceMethodInvokeReturnStmt));

            $serviceClassImplementation->addStmt($serviceClassMethodImplementation->getNode());
        }
    }

    private function wrapInNamespace(string $namespace, Class_ $serviceClassImplementation): Namespace_
    {
        return $this->builderFactory
            ->namespace($namespace)
            ->addStmt($serviceClassImplementation);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $service
     */
    private function createServiceMethodInvokeReturnStmt(ReflectionClass $service): MethodCall
    {
        $serviceMethodFactoryCreateMethodCall = new MethodCall(
            new PropertyFetch(new Variable('this'), 'serviceMethodFactory'),
            'create',
            [
                new Arg(new String_(Utils::toFQCN($service->getName()))),
                new Arg(new Function_()),
            ],
        );
        return new MethodCall(
            $serviceMethodFactoryCreateMethodCall,
            'invoke',
            [
                new Arg(new FuncCall(new Name('func_get_args'))),
            ],
        );
    }

    private function validateMethodReturnType(ReflectionMethod $method): void
    {
        if (!$method->hasReturnType()) {
            throw Utils::methodException($method, 'Method return type is required, none found.');
        }

        $returnType = $method->getReturnType();
        if (!$returnType instanceof ReflectionNamedType) {
            throw Utils::methodException($method, 'Cannot detect return type name.');
        }

        $returnTypeName = $returnType->getName();
        $callClassReturnType = Call::class;
        if ($returnTypeName !== $callClassReturnType) {
            throw Utils::methodException(
                $method,
                "Method return type should be a {$callClassReturnType} class. '{$returnTypeName}' return type found.",
            );
        }
    }

    /** @return list<NodeParam> */
    private function appendMethodParameters(ReflectionMethod $method): array
    {
        $params = [];
        foreach ($method->getParameters() as $parameter) {
            $this->validateParameter($parameter, $method);

            $paramBuilder = $this->builderFactory->param($parameter->name);

            if ($parameter->isDefaultValueAvailable()) {
                $paramBuilder->setDefault($parameter->getDefaultValue());
            }

            $reflectionType = $parameter->getType();

            if (!$reflectionType instanceof ReflectionNamedType) {
                throw Utils::parameterException($method, $parameter->getPosition(), 'Cannot detect parameter type name.');
            }

            $reflectionTypeName = $reflectionType->getName();
            if (!$reflectionType->isBuiltin()) {
                $reflectionTypeName = Utils::toFQCN($reflectionTypeName);
            }

            if ($reflectionType->allowsNull()) {
                $typeNode = $reflectionType->isBuiltin() ? new Identifier($reflectionTypeName) : new Name($reflectionTypeName);
                $paramBuilder->setType(new NullableType($typeNode));
            } else {
                $paramBuilder->setType($reflectionTypeName);
            }

            if ($parameter->isPassedByReference()) {
                $paramBuilder->makeByRef();
            }

            if ($parameter->isVariadic()) {
                $paramBuilder->makeVariadic();
            }

            $this->appendAttributes($parameter->getAttributes(), $paramBuilder);

            $params[] = $paramBuilder->getNode();
        }
        return $params;
    }

    private function validateParameter(ReflectionParameter $parameter, ReflectionMethod $method): void
    {
        if (is_null($parameter->getType())) {
            throw Utils::parameterException($method, $parameter->getPosition(), 'Parameter type is required, none found.');
        }
    }

    /**
     * @template A of object
     * @param list<ReflectionAttribute<A>> $attributes
     */
    private function appendAttributes(array $attributes, Method|Param $destination): void
    {
        foreach ($attributes as $attribute) {
            $name = new Name(Utils::toFQCN($attribute->getName()));
            $attribute = $this->builderFactory->attribute($name, $attribute->getArguments());

            $destination->addAttribute($attribute);
        }
    }
}
