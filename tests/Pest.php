<?php

use BoldlyGrow\Gitlab\Tests\TestCase;

uses(TestCase::class)->in(__DIR__ . '/Feature');

/**
 * A personal access token in the legacy `glpat-` format that passes connection validation.
 */
function testToken(string $character = 'a'): string
{
    return 'glpat-' . str_repeat($character, 20);
}

/**
 * A connection array for a fake GitLab instance at https://gitlab.example.com.
 *
 * @param  array<string, mixed>  $overrides
 *
 * @return array<string, mixed>
 */
function gitlabConnection(array $overrides = []): array
{
    return array_merge([
        'url' => 'https://gitlab.example.com',
        'token' => testToken(),
    ], $overrides);
}

/**
 * The log entries written during the current test with the given event type.
 *
 * @return array<int, array{level: string, message: string, context: array<string, mixed>}>
 */
function loggedEvents(string $event_type): array
{
    /** @var TestCase $test */
    $test = test();

    return array_values(array_filter(
        $test->logged,
        fn (array $entry) => ($entry['context']['event_type'] ?? null) === $event_type
    ));
}
