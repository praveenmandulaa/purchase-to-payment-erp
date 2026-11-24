<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $environment = $app->environment();

        $connection = $app['config']->get(
            'database.default'
        );

        $database = $app['config']->get(
            "database.connections.{$connection}.database"
        );

        if (
            $environment !== 'testing'
            || $connection !== 'mysql'
            || $database !== 'erp_p2p_testing'
        ) {
            throw new RuntimeException(
                'Unsafe test environment. Tests must use '
                . 'the erp_p2p_testing MySQL database.'
            );
        }

        $actualDatabase = $app['db']
            ->connection()
            ->selectOne('SELECT DATABASE() as current_database')
            ->current_database;

        if ($actualDatabase !== 'erp_p2p_testing') {
            throw new RuntimeException(
                'Unsafe database connection detected. '
                . 'Testing has been stopped.'
            );
        }

        return $app;
    }
}