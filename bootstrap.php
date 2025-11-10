<?php
// bootstrap.php

require __DIR__ . '/vendor/autoload.php';

use Laf\Core\Container\Container;
use Laf\Core\Database\Connection;
use Laf\Core\Database\ConnectionInterface;
use Laf\Core\Database\DatabaseConfig;
use Laf\Core\Database\DatabaseDriver;

$container = new Container();

// Configure database
$container->singleton(ConnectionInterface::class, function () {
    $config = new DatabaseConfig(
        driver: DatabaseDriver::MYSQL,
        host: 'localhost',
        port: 3306,
        database: 'your_database',
        username: 'your_username',
        password: 'your_password',
        charset: 'utf8mb4'
    );

    return new Connection($config);
});

return $container;