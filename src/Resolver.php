<?php

declare(strict_types=1);

namespace Conia\Registry;

use Closure;
use Conia\Registry\Exception\ContainerException;
use Conia\Registry\Exception\NotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use Throwable;

class Resolver
{
    public function __construct(protected readonly Registry $registry)
    {
    }

    /** @psalm-param class-string $class */
    public function autowire(
        string $class,
        array $predefinedArgs = [],
        ?string $constructor = null
    ): object {
        if (!$this->registry->autowire) {
            try {
                $this->registry->new($class, ...$predefinedArgs);
            } catch (Throwable $e) {
                throw new ContainerException(
                    "Autowiring is turned off. Tried to instantiate class '{$class}'"
                );
            }
        }

        $rc = new ReflectionClass($class);

        try {
            if ($constructor) {
                // Factory method
                $rm = $rc->getMethod($constructor);
                $args = $this->resolveArgs($rm, $predefinedArgs);
                $instance = $rm->invoke(null, ...$args);
            } else {
                // Regular constructor
                $args = $this->resolveConstructorArgs($rc, $predefinedArgs);
                $instance = $rc->newInstance(...$args);
            }

            assert(is_object($instance));

            return $this->resolveCallAttributes($instance);
        } catch (Throwable $e) {
            throw new ContainerException(
                'Autowiring unresolvable: ' . $class . ' Details: ' . $e->getMessage()
            );
        }
    }

    public function resolveCallAttributes(object $instance): object
    {
        $callAttrs = (new ReflectionObject($instance))->getAttributes(Call::class);

        // See if the attribute itself has one or more Call attributes. If so,
        // resolve/autowire the arguments of the method it states and call it.
        foreach ($callAttrs as $callAttr) {
            $callAttr = $callAttr->newInstance();
            $methodToResolve = $callAttr->method;

            /** @psalm-var callable */
            $callable = [$instance, $methodToResolve];
            $args = $this->resolveCallableArgs($callable, $callAttr->args);
            $callable(...$args);
        }

        return $instance;
    }

    public function resolveParam(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            try {
                return $this->registry->get(ltrim($type->getName(), '?'));
            } catch (NotFoundException | ContainerException  $e) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }

                throw $e;
            }
        } else {
            if ($type) {
                throw new ContainerException(
                    "Autowiring does not support union or intersection types. Source: \n" .
                        $this->getParamInfo($param)
                );
            }

            throw new ContainerException(
                "Autowired entities need to have typed constructor parameters. Source: \n" .
                    $this->getParamInfo($param)
            );
        }
    }

    public function getParamInfo(ReflectionParameter $param): string
    {
        $type = $param->getType();
        $rf = $param->getDeclaringFunction();
        $rc = null;

        if ($rf instanceof ReflectionMethod) {
            $rc = $rf->getDeclaringClass();
        }

        return ($rc ? $rc->getName() . '::' : '') .
            ($rf->getName() . '(..., ') .
            ($type ? (string)$type . ' ' : '') .
            '$' . $param->getName() . ', ...)';
    }

    /** @psalm-param callable-array|callable $callable */
    public function resolveCallableArgs(array|callable $callable, array $predefinedArgs = []): array
    {
        $callable = Closure::fromCallable($callable);
        $rf = new ReflectionFunction($callable);
        $predefinedArgs = array_merge($this->getInjectedArgs($rf), $predefinedArgs);

        return $this->resolveArgs($rf, $predefinedArgs);
    }

    /** @psalm-param ReflectionClass|class-string $class */
    public function resolveConstructorArgs(ReflectionClass|string $class, array $predefinedArgs = []): array
    {
        $rc = is_string($class) ? new ReflectionClass($class) : $class;
        $constructor = $rc->getConstructor();

        if ($constructor) {
            $combinedArgs = array_merge($this->getInjectedArgs($constructor), $predefinedArgs);

            return $this->resolveArgs($constructor, $combinedArgs);
        }

        return $predefinedArgs;
    }

    protected function resolveArgs(
        ?ReflectionFunctionAbstract $rf,
        array $predefinedArgs = [],
    ): array {
        $args = [];

        if ($rf) {
            $parameters = $rf->getParameters();
            $countPredefined = count($predefinedArgs);

            if (array_is_list($predefinedArgs) && $countPredefined > 0) {
                // predefined args are not named, use them as they are
                $args = $predefinedArgs;
                $parameters = array_slice($parameters, $countPredefined);
            }

            foreach ($parameters as $param) {
                $name = $param->getName();
                if (isset($predefinedArgs[$name])) {
                    /** @psalm-var list<mixed> */
                    $args[] = $predefinedArgs[$name];
                } else {
                    /** @psalm-var list<mixed> */
                    $args[] = $this->resolveParam($param);
                }
            }
        }

        return $args;
    }

    protected function getInjectedArgs(ReflectionFunctionAbstract $rf): array
    {
        /** @psalm-var array<non-empty-string, mixed> */
        $result = [];
        $injectAttrs = $rf->getAttributes(Inject::class);

        foreach ($injectAttrs as $injectAttr) {
            $instance = $injectAttr->newInstance();

            /** @psalm-suppress MixedAssignment */
            foreach ($instance->args as $name => $value) {
                assert(is_string($name));

                if (is_string($value)) {
                    if ($this->registry->has($value)) {
                        $result[$name] = $this->registry->get($value);
                    } elseif (class_exists($value)) {
                        $result[$name] = $this->autowire($value);
                    } else {
                        $result[$name] = $value;
                    }
                } else {
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }
}
