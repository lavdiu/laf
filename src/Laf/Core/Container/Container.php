<?php

declare(strict_types=1);

namespace Laf\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Dependency Injection Container
 * 
 * PSR-11 compliant container with auto-wiring capabilities
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed> Registered bindings
     */
    private array $bindings = [];

    /**
     * @var array<string, object> Resolved singleton instances
     */
    private array $instances = [];

    /**
     * @var array<string, string> Type aliases
     */
    private array $aliases = [];

    /**
     * @var array<string, array<string>> Tagged bindings
     */
    private array $tags = [];

    /**
     * @var array<string> Stack of types currently being resolved (for circular dependency detection)
     */
    private array $buildStack = [];

    /**
     * @var array<ServiceProviderInterface> Registered service providers
     */
    private array $providers = [];

    /**
     * @var array<ServiceProviderInterface> Booted service providers
     */
    private array $bootedProviders = [];

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $abstract, array $parameters = []): object
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("Entry '{$id}' not found in container") extends \Exception implements NotFoundExceptionInterface {};
        }

        return $this->resolve($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) 
            || isset($this->instances[$id]) 
            || isset($this->aliases[$id])
            || class_exists($id)
            || interface_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * {@inheritdoc}
     */
    public function tag(array $abstracts, string $tag): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }

        foreach ($abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return array_map(
            fn($abstract) => $this->make($abstract),
            $this->tags[$tag]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable $callable, array $parameters = []): mixed
    {
        $dependencies = $this->resolveCallableDependencies($callable, $parameters);
        
        return $callable(...$dependencies);
    }

    /**
     * {@inheritdoc}
     */
    public function register(ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
        $provider->register($this);
    }

    /**
     * Boot all registered service providers
     *
     * @return void
     */
    public function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            if (!in_array($provider, $this->bootedProviders, true)) {
                $provider->boot($this);
                $this->bootedProviders[] = $provider;
            }
        }
    }

    /**
     * Resolve a type from the container
     *
     * @param string $abstract
     * @param array<string, mixed> $parameters
     * @return object
     * @throws ContainerExceptionInterface
     */
    private function resolve(string $abstract, array $parameters = []): object
    {
        $abstract = $this->getAlias($abstract);

        // Return existing singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get concrete implementation
        $concrete = $this->getConcrete($abstract);

        // Build the object
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        // Store singleton if needed
        if ($this->isSingleton($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the concrete type for an abstract type
     *
     * @param string $abstract
     * @return mixed
     */
    private function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given concrete is buildable
     *
     * @param mixed $concrete
     * @param string $abstract
     * @return bool
     */
    private function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Check if a type is registered as singleton
     *
     * @param string $abstract
     * @return bool
     */
    private function isSingleton(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['singleton']) 
            && $this->bindings[$abstract]['singleton'] === true;
    }

    /**
     * Build a concrete instance
     *
     * @param string|Closure $concrete
     * @param array<string, mixed> $parameters
     * @return object
     * @throws ContainerExceptionInterface
     */
    private function build(string|Closure $concrete, array $parameters = []): object
    {
        // If it's a closure, call it with the container
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        // Check for circular dependencies
        if (in_array($concrete, $this->buildStack)) {
            throw new class("Circular dependency detected: " . implode(' -> ', $this->buildStack) . " -> {$concrete}") 
                extends \Exception implements ContainerExceptionInterface {};
        }

        $this->buildStack[] = $concrete;

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            array_pop($this->buildStack);
            throw new class("Target class [{$concrete}] does not exist: " . $e->getMessage()) 
                extends \Exception implements ContainerExceptionInterface {};
        }

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            array_pop($this->buildStack);
            throw new class("Target [{$concrete}] is not instantiable") 
                extends \Exception implements ContainerExceptionInterface {};
        }

        $constructor = $reflector->getConstructor();

        // No constructor, return new instance
        if ($constructor === null) {
            array_pop($this->buildStack);
            return new $concrete();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveMethodDependencies(
            $constructor->getParameters(),
            $parameters
        );

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method dependencies
     *
     * @param array<ReflectionParameter> $parameters
     * @param array<string, mixed> $primitives
     * @return array<mixed>
     * @throws ContainerExceptionInterface
     */
    private function resolveMethodDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if parameter was explicitly provided
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Try to resolve from type hint
            $type = $parameter->getType();
            
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            // Check if parameter has default value
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Check if parameter is nullable
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new class("Unable to resolve parameter [{$name}]") 
                extends \Exception implements ContainerExceptionInterface {};
        }

        return $dependencies;
    }

    /**
     * Resolve callable dependencies
     *
     * @param callable $callable
     * @param array<string, mixed> $parameters
     * @return array<mixed>
     * @throws ContainerExceptionInterface
     */
    private function resolveCallableDependencies(callable $callable, array $parameters = []): array
    {
        try {
            if (is_array($callable)) {
                $reflector = new \ReflectionMethod($callable[0], $callable[1]);
            } else {
                $reflector = new \ReflectionFunction($callable);
            }

            return $this->resolveMethodDependencies($reflector->getParameters(), $parameters);
        } catch (ReflectionException $e) {
            throw new class("Unable to resolve callable dependencies: " . $e->getMessage()) 
                extends \Exception implements ContainerExceptionInterface {};
        }
    }

    /**
     * Get the alias for an abstract type
     *
     * @param string $abstract
     * @return string
     */
    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }
}
