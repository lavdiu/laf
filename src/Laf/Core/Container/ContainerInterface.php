<?php

declare(strict_types=1);

namespace Laf\Core\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Dependency Injection Container Interface
 * 
 * Extends PSR-11 with additional functionality for binding and resolving dependencies
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind a concrete implementation to an abstract type
     *
     * @param string $abstract The interface or abstract class name
     * @param callable|string|null $concrete The concrete implementation
     * @param bool $singleton Whether to treat as singleton
     * @return void
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void;

    /**
     * Bind a singleton instance
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void;

    /**
     * Bind an existing instance as a singleton
     *
     * @param string $abstract
     * @param object $instance
     * @return void
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve a type from the container
     *
     * @template T
     * @param class-string<T> $abstract
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function make(string $abstract, array $parameters = []): object;

    /**
     * Call a callable with dependency injection
     *
     * @param callable $callable
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function call(callable $callable, array $parameters = []): mixed;

    /**
     * Register a service provider
     *
     * @param ServiceProviderInterface $provider
     * @return void
     */
    public function register(ServiceProviderInterface $provider): void;

    /**
     * Check if a binding exists
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Alias a type to a different name
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Tag bindings for group resolution
     *
     * @param array<string> $abstracts
     * @param string $tag
     * @return void
     */
    public function tag(array $abstracts, string $tag): void;

    /**
     * Resolve all bindings with a given tag
     *
     * @param string $tag
     * @return array<object>
     */
    public function tagged(string $tag): array;
}
