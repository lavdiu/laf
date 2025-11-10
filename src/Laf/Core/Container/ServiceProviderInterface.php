<?php

declare(strict_types=1);

namespace Laf\Core\Container;

/**
 * Service Provider Interface
 * 
 * Service providers are the central place to configure application bindings
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings in the container
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap any application services (called after all providers are registered)
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}
