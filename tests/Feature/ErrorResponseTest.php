<?php

use BoldlyGrow\Gitlab\ApiClient;
use BoldlyGrow\Gitlab\Exceptions\BadRequestException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareConnectionRefusedException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareConnectionUnreachableException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareInternalErrorException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareRequestTimeoutException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareResponseTimeoutException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareSslCertificateException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareSslHandshakeException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareUnknownErrorException;
use BoldlyGrow\Gitlab\Exceptions\ConflictException;
use BoldlyGrow\Gitlab\Exceptions\ForbiddenException;
use BoldlyGrow\Gitlab\Exceptions\MethodNotAllowedException;
use BoldlyGrow\Gitlab\Exceptions\NotFoundException;
use BoldlyGrow\Gitlab\Exceptions\PreconditionFailedException;
use BoldlyGrow\Gitlab\Exceptions\RateLimitException;
use BoldlyGrow\Gitlab\Exceptions\ServerErrorException;
use BoldlyGrow\Gitlab\Exceptions\ServiceUnavailableException;
use BoldlyGrow\Gitlab\Exceptions\UnauthorizedException;
use BoldlyGrow\Gitlab\Exceptions\UnprocessableException;
use Illuminate\Support\Facades\Http;

it('throws the exception for the status code of a failed response', function (int $status, string $exception) {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response(['message' => 'Failed'], $status),
    ]);

    expect(fn () => ApiClient::get(uri: 'projects/123'))->toThrow($exception);
})->with([
    'bad request' => [400, BadRequestException::class],
    'unauthorized' => [401, UnauthorizedException::class],
    'forbidden' => [403, ForbiddenException::class],
    'not found' => [404, NotFoundException::class],
    'method not allowed' => [405, MethodNotAllowedException::class],
    'conflict' => [409, ConflictException::class],
    'precondition failed' => [412, PreconditionFailedException::class],
    'unprocessable' => [422, UnprocessableException::class],
    'rate limit' => [429, RateLimitException::class],
    'server error' => [500, ServerErrorException::class],
    'service unavailable' => [503, ServiceUnavailableException::class],
    'cloudflare unknown' => [520, CloudflareUnknownErrorException::class],
    'cloudflare connection refused' => [521, CloudflareConnectionRefusedException::class],
    'cloudflare request timeout' => [522, CloudflareRequestTimeoutException::class],
    'cloudflare connection unreachable' => [523, CloudflareConnectionUnreachableException::class],
    'cloudflare response timeout' => [524, CloudflareResponseTimeoutException::class],
    'cloudflare ssl handshake' => [525, CloudflareSslHandshakeException::class],
    'cloudflare ssl certificate' => [526, CloudflareSslCertificateException::class],
    'cloudflare internal error' => [530, CloudflareInternalErrorException::class],
]);

it('logs the event type and level for the status code', function (int $status, string $event_type, string $level) {
    config()->set('gitlab-api-client.exceptions', false);
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response($status === 204 ? '' : ['id' => 123], $status),
    ]);

    $response = ApiClient::get(uri: 'projects/123');

    expect($response->status->code)->toBe($status);
    [$entry] = loggedEvents('gitlab.api.get.' . $event_type);
    expect($entry['level'])->toBe($level);
})->with([
    'ok' => [200, 'success', 'debug'],
    'created' => [201, 'success', 'debug'],
    'accepted' => [202, 'success', 'debug'],
    'no content' => [204, 'success', 'debug'],
    'bad request' => [400, 'warning.bad-request', 'warning'],
    'unauthorized' => [401, 'error.unauthorized', 'error'],
    'forbidden' => [403, 'error.forbidden', 'error'],
    'not found' => [404, 'warning.not-found', 'warning'],
    'method not allowed' => [405, 'error.method-not-allowed', 'error'],
    'conflict' => [409, 'error.conflict', 'error'],
    'precondition failed' => [412, 'error.precondition-failed', 'error'],
    'unprocessable' => [422, 'error.unprocessable', 'error'],
    'rate limit' => [429, 'critical.rate-limit', 'critical'],
    'server error' => [500, 'critical.server-error', 'critical'],
    'not implemented' => [501, 'error.not-implemented', 'error'],
    'service unavailable' => [503, 'critical.server-unavailable', 'critical'],
    'cloudflare unknown' => [520, 'critical.cloudflare.unknown.retryable', 'warning'],
    'cloudflare connection refused' => [521, 'critical.cloudflare.connection-refused', 'critical'],
    'cloudflare request timeout' => [522, 'critical.cloudflare.request-timeout', 'critical'],
    'cloudflare connection unreachable' => [523, 'critical.cloudflare.connection-unreachable', 'critical'],
    'cloudflare response timeout' => [524, 'critical.cloudflare.response-timeout', 'critical'],
    'cloudflare ssl handshake' => [525, 'critical.cloudflare.ssl-handshake', 'critical'],
    'cloudflare ssl certificate' => [526, 'critical.cloudflare.ssl-certificate', 'critical'],
    'cloudflare internal error' => [530, 'critical.cloudflare.internal-error', 'critical'],
]);

it('logs the reason for a failed request in the errors array', function () {
    config()->set('gitlab-api-client.exceptions', false);
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response(['message' => '404 Project Not Found'], 404),
    ]);

    ApiClient::get(uri: 'projects/123');

    [$entry] = loggedEvents('gitlab.api.get.warning.not-found');
    expect($entry['message'])->toBe('ApiClient Client Error');
    expect($entry['context']['errors'])->toBe(['message' => '404 Project Not Found']);
});

it('includes the method, status code, URL, and reason in the exception message', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123/repository/branches' => Http::response(['message' => '400 Bad request - Duplicate branch name'], 400),
    ]);

    expect(fn () => ApiClient::post(uri: 'projects/123/repository/branches', data: ['branch' => 'main', 'ref' => 'main']))
        ->toThrow(BadRequestException::class, 'POST 400 https://gitlab.example.com/api/v4/projects/123/repository/branches (Reason) 400 Bad request - Duplicate branch name');
});

it('appends the decoded URL to the exception message when the URL is percent encoded', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects/*' => Http::response(['message' => '404 Project Not Found'], 404),
    ]);

    expect(fn () => ApiClient::get(uri: 'projects/' . ApiClient::urlencode('group/project'), data: ['statistics' => 'true']))
        ->toThrow(NotFoundException::class, implode(' ', [
            'GET 404 https://gitlab.example.com/api/v4/projects/group%2Fproject?statistics=true',
            '(Reason) 404 Project Not Found',
            '(Decoded) https://gitlab.example.com/api/v4/projects/group/project?statistics=true',
        ]));
});

it('flattens the reason from each error response body format', function (mixed $body, string $reason) {
    Http::fake([
        'gitlab.example.com/api/v4/projects' => Http::response($body, 400),
    ]);

    expect(fn () => ApiClient::post(uri: 'projects'))
        ->toThrow(BadRequestException::class, 'POST 400 https://gitlab.example.com/api/v4/projects (Reason) ' . $reason);
})->with([
    'error string' => [['error' => 'name is missing'], 'name is missing'],
    'message object' => [['message' => ['name' => ['has already been taken'], 'path' => ["can't be blank"]]], "name: has already been taken, path: can't be blank"],
    'oauth error description' => [['error' => 'invalid_grant', 'error_description' => 'The provided authorization grant is invalid'], 'The provided authorization grant is invalid'],
    'unknown keys' => [['detail' => 'Something went wrong'], '{"detail":"Something went wrong"}'],
]);

it('omits the reason from the exception message when the response body is empty or not JSON', function (string $body) {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123' => Http::response($body, 403),
    ]);

    try {
        ApiClient::delete(uri: 'projects/123');
    } catch (ForbiddenException $exception) {
        expect($exception->getMessage())->toBe('DELETE 403 https://gitlab.example.com/api/v4/projects/123');

        return;
    }

    $this->fail('A ForbiddenException was not thrown.');
})->with([
    'empty' => [''],
    'html' => ['<html>Forbidden</html>'],
]);

it('explains how to replace the token for a 401 response', function () {
    Http::fake([
        'gitlab.example.com/api/v4/user*' => Http::response(['message' => '401 Unauthorized'], 401),
    ]);

    expect(fn () => ApiClient::get(uri: 'user'))
        ->toThrow(UnauthorizedException::class, 'The `GITLAB_API_TOKEN` has been configured but is invalid.');
});

it('returns the failed response instead of throwing when exceptions are disabled', function () {
    config()->set('gitlab-api-client.exceptions', false);
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response(['message' => '404 Project Not Found'], 404),
    ]);

    $response = ApiClient::get(uri: 'projects/123');

    expect($response->status->code)->toBe(404);
    expect($response->status->clientError)->toBeTrue();
    expect($response->data->message)->toBe('404 Project Not Found');
});

it('throws a ConflictException for a 409 response', function () {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123/members' => Http::response(['message' => 'Member already exists'], 409),
    ]);

    expect(fn () => ApiClient::post(uri: 'projects/123/members', data: ['user_id' => 1]))
        ->toThrow(ConflictException::class, 'POST 409 https://gitlab.example.com/api/v4/projects/123/members (Reason) Member already exists');
});

it('throws the exception for the range of a 4xx or 5xx status code without its own exception', function (int $status, string $exception) {
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response('', $status),
    ]);

    expect(fn () => ApiClient::get(uri: 'projects/123'))
        ->toThrow($exception, 'GET ' . $status . ' https://gitlab.example.com/api/v4/projects/123');
})->with([
    'gone' => [410, BadRequestException::class],
    'not implemented' => [501, ServerErrorException::class],
    'bad gateway' => [502, ServerErrorException::class],
    'gateway timeout' => [504, ServerErrorException::class],
]);

it('logs a status code without its own event type with the event type for its range', function (int $status, string $event_type, string $level) {
    config()->set('gitlab-api-client.exceptions', false);
    Http::fake([
        'gitlab.example.com/api/v4/projects/123*' => Http::response('', $status),
    ]);

    $response = ApiClient::get(uri: 'projects/123');

    expect($response->status->code)->toBe($status);
    [$entry] = loggedEvents('gitlab.api.get.' . $event_type);
    expect($entry['level'])->toBe($level);
})->with([
    'partial content' => [206, 'success', 'debug'],
    'not modified' => [304, 'warning.unexpected-status', 'warning'],
    'conflict' => [409, 'error.conflict', 'error'],
    'gone' => [410, 'error.client-error', 'error'],
    'bad gateway' => [502, 'critical.server-error', 'critical'],
]);
