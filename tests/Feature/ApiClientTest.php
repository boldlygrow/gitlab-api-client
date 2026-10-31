<?php

use BoldlyGrow\Gitlab\ApiClient;
use BoldlyGrow\Gitlab\Exceptions\ConfigurationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('uses the url and token from the config when no connection array is passed', function () {
    Http::fake([
        'gitlab.example.com/api/v4/user*' => Http::response(['id' => 1, 'username' => 'z3r0c00l']),
    ]);

    $response = ApiClient::get(uri: 'user');

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($response->data->username)->toBe('z3r0c00l');
    expect($request->url())->toBe('https://gitlab.example.com/api/v4/user?per_page=100');
    expect($request->header('Authorization'))->toBe(['Bearer ' . testToken()]);
    expect($request->header('User-Agent')[0])->toContain('boldlygrow/gitlab-api-client');
});

it('uses a connection array instead of the config', function () {
    config()->set('gitlab-api-client.url', null);
    config()->set('gitlab-api-client.token', null);
    Http::fake([
        'gitlab.other.com/api/v4/user*' => Http::response(['id' => 1]),
    ]);

    ApiClient::get(uri: 'user', connection: gitlabConnection(['url' => 'https://gitlab.other.com', 'token' => testToken('b')]));

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($request->url())->toStartWith('https://gitlab.other.com/api/v4/user');
    expect($request->header('Authorization'))->toBe(['Bearer ' . testToken('b')]);
});

it('accepts a token in the newer glpat format with a routing suffix', function () {
    Http::fake([
        'gitlab.example.com/api/v4/user*' => Http::response(['id' => 1]),
    ]);

    $token = 'glpat-' . str_repeat('c', 27) . '.01.0z1abcdef';
    ApiClient::get(uri: 'user', connection: gitlabConnection(['token' => $token]));

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($request->header('Authorization'))->toBe(['Bearer ' . $token]);
});

it('rejects an invalid connection with a ConfigurationException', function (array $connection, string $reason) {
    expect(fn () => ApiClient::get(uri: 'user', connection: $connection))
        ->toThrow(ConfigurationException::class, $reason);

    Http::assertNothingSent();
})->with([
    'missing url' => [['token' => 'glpat-' . str_repeat('a', 20)], 'The url field is required.'],
    'http url' => [['url' => 'http://gitlab.example.com', 'token' => 'glpat-' . str_repeat('a', 20)], 'The url field must be a valid URL.'],
    'missing token' => [['url' => 'https://gitlab.example.com'], 'The token field is required.'],
    'token without the glpat prefix' => [['url' => 'https://gitlab.example.com', 'token' => str_repeat('a', 26)], 'The token field format is invalid.'],
]);

it('rejects an invalid config when no connection array is passed', function () {
    config()->set('gitlab-api-client.token', null);

    expect(fn () => ApiClient::get(uri: 'user'))
        ->toThrow(ConfigurationException::class, 'Gitlab API configuration validation error.');
});

it('tests the connection with the version endpoint', function () {
    Http::fake([
        'gitlab.example.com/api/v4/version*' => Http::response(['version' => '18.5.0']),
    ]);

    expect(ApiClient::testConnection())->toBeTrue();
    expect(loggedEvents('gitlab.api.test.success'))->toHaveCount(1);
});

it('sends GET query data with 100 results per page', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects*' => Http::response([]),
    ]);

    ApiClient::get(uri: '/projects', data: ['search' => 'cool']);

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($request->method())->toBe('GET');
    expect($request->url())->toBe('https://gitlab.example.com/api/v4/projects?per_page=100&search=cool');
});

it('sends GET requests with a custom number of results per page', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects*' => Http::response([]),
    ]);

    ApiClient::get(uri: 'projects', per_page: 20);

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($request->url())->toBe('https://gitlab.example.com/api/v4/projects?per_page=20');
});

it('sends the data as the request body for POST, PUT, and DELETE requests', function (string $client_method, string $http_method) {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123' => Http::response(['id' => 123]),
    ]);

    $response = ApiClient::{$client_method}(uri: 'projects/123', data: ['description' => 'Updated']);

    /** @var Request $request */
    [$request] = Http::recorded()->sole();
    expect($request->method())->toBe($http_method);
    expect($request->url())->toBe('https://gitlab.example.com/api/v4/projects/123');
    expect($request->data())->toBe(['description' => 'Updated']);
    expect($response->data->id)->toBe(123);
})->with([
    'post' => ['post', 'POST'],
    'put' => ['put', 'PUT'],
    'delete' => ['delete', 'DELETE'],
]);

it('returns the parsed data, headers, and status for a response', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123' => Http::response(['id' => 123], 201, ['X-Request-Id' => 'abc123']),
    ]);

    $response = ApiClient::post(uri: 'projects/123');

    expect($response->data->id)->toBe(123);
    expect($response->headers['X-Request-Id'])->toBe('abc123');
    expect((array) $response->status)->toBe([
        'code' => 201,
        'ok' => false,
        'successful' => true,
        'failed' => false,
        'serverError' => false,
        'clientError' => false,
    ]);
});

it('retries a PUT request that Cloudflare rejects with a 520 response', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123' => Http::sequence()
            ->push(['message' => 'Unknown'], 520)
            ->push(['id' => 123], 200),
    ]);

    $response = ApiClient::put(uri: 'projects/123', data: ['description' => 'Updated']);

    expect($response->status->code)->toBe(200);
    expect(Http::recorded())->toHaveCount(2);
    expect(loggedEvents('gitlab.api.put.cloudflare.unknown.retrying'))->toHaveCount(1);
});

it('follows the next page link until the last page and combines the results', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects?per_page=100' => Http::response([['id' => 1], ['id' => 2]], 200, [
            'Link' => '<https://gitlab.example.com/api/v4/projects?page=2&per_page=100>; rel="next", <https://gitlab.example.com/api/v4/projects?page=1&per_page=100>; rel="first"',
            'X-Next-Page' => '2',
            'X-Page' => '1',
        ]),
        'gitlab.example.com/api/v4/projects?page=2&per_page=100' => Http::response([['id' => 3]], 200, [
            'Link' => '<https://gitlab.example.com/api/v4/projects?page=1&per_page=100>; rel="prev"',
            'X-Next-Page' => '',
            'X-Page' => '2',
        ]),
    ]);

    $response = ApiClient::get(uri: 'projects');

    expect(collect((array) $response->data)->pluck('id')->all())->toBe([1, 2, 3]);
    expect($response->status->code)->toBe(200);
    expect($response->headers['X-Page'])->toBe('1');
    expect(Http::recorded())->toHaveCount(2);
    expect(loggedEvents('gitlab.api.get.process.pagination.started'))->toHaveCount(1);
    expect(loggedEvents('gitlab.api.get.process.pagination.finished'))->toHaveCount(1);
});

it('reads lowercase pagination headers', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects?per_page=100' => Http::response([['id' => 1]], 200, [
            'link' => '<https://gitlab.example.com/api/v4/projects?page=2&per_page=100>; rel="next"',
            'x-next-page' => '2',
        ]),
        'gitlab.example.com/api/v4/projects?page=2&per_page=100' => Http::response([['id' => 2]], 200, [
            'x-next-page' => '',
        ]),
    ]);

    $response = ApiClient::get(uri: 'projects');

    expect(collect((array) $response->data)->pluck('id')->all())->toBe([1, 2]);
});

it('does not paginate a single page response', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects*' => Http::response([['id' => 1]], 200, ['X-Next-Page' => '']),
    ]);

    $response = ApiClient::get(uri: 'projects');

    expect($response->data)->toBeArray()->toHaveCount(1);
    expect(Http::recorded())->toHaveCount(1);
    expect(loggedEvents('gitlab.api.get.process.pagination.started'))->toBeEmpty();
});

it('logs the request data without the keys excluded for the method', function (string $client_method, array $data, array $logged) {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response(['id' => 123]),
    ]);

    ApiClient::{$client_method}(uri: 'projects/123', data: $data);

    [$entry] = loggedEvents('gitlab.api.' . $client_method . '.success');
    expect($entry['context']['metadata']['request_data'])->toBe($logged);
})->with([
    'get without key and password' => ['get', ['search' => 'cool', 'key' => 'secret', 'password' => 'hunter2'], ['search' => 'cool']],
    'post without content' => ['post', ['branch' => 'main', 'content' => 'YmFzZTY0'], ['branch' => 'main']],
    'put without content' => ['put', ['branch' => 'main', 'content' => 'YmFzZTY0'], ['branch' => 'main']],
]);

it('does not log the request data when it is disabled for the method', function () {
    config()->set('gitlab-api-client.log.request_data.post.enabled', false);
    Http::fake([
        'gitlab.example.com/api/v4/projects' => Http::response(['id' => 123], 201),
    ]);

    ApiClient::post(uri: 'projects', data: ['name' => 'My Cool Project']);

    [$entry] = loggedEvents('gitlab.api.post.success');
    expect($entry['context']['metadata'])->not->toHaveKey('request_data');
    expect($entry['context']['metadata']['url'])->toBe('https://gitlab.example.com/api/v4/projects');
});

it('logs the rate limit remaining from the response headers', function () {
    Http::fake([
        'gitlab.example.com/api/v4/user*' => Http::response(['id' => 1], 200, [
            'RateLimit-Limit' => '2000',
            'RateLimit-Remaining' => '1998',
        ]),
    ]);

    ApiClient::get(uri: 'user');

    [$entry] = loggedEvents('gitlab.api.get.success');
    expect($entry['context']['metadata']['rate_limit_remaining'])->toBe('1998');
});

it('encodes a path with the GitLab URL encoding syntax', function () {
    expect(ApiClient::urlencode('group-name/project.name'))->toBe('group%2Dname%2Fproject%2Ename');
});
