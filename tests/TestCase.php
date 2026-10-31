<?php

namespace BoldlyGrow\Gitlab\Tests;

use BoldlyGrow\AuditLog\AuditLogServiceProvider;
use BoldlyGrow\Gitlab\GitlabApiServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * The log entries written during the test, in order.
     *
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Every GitLab endpoint a test calls must be faked, so no test can reach the network.
        Http::preventStrayRequests();

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });
    }

    /**
     * @param  Application  $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuditLogServiceProvider::class,
            GitlabApiServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('logging.default', 'null');
        $app['config']->set('gitlab-api-client.url', 'https://gitlab.example.com');
        $app['config']->set('gitlab-api-client.token', 'glpat-' . str_repeat('a', 20));
    }
}
