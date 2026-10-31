# Changelog

All notable changes to `boldlygrow/gitlab-api-client` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.0] - 2026-10-31

### Overview

This release moves the package to the `BoldlyGrow` namespace, updates the supported Laravel versions, and makes every response status code log and throw consistently.

- **The package is renamed** from `provisionesta/gitlab-api-client` to `boldlygrow/gitlab-api-client`.
- **Laravel v12 and v13 are supported.** Support for Laravel v8, v9, v10, and v11 is dropped.
- **Exception messages include the reason that GitLab returned**, and every 4xx and 5xx response throws an exception.
- **Tokens must start with `glpat-`.** Personal, group, and project access tokens are accepted in both the legacy format and GitLab's newer routable format.
- The API methods, exception classes, and response format are otherwise unchanged, so existing calls continue to work after the namespace and dependency updates.

### Added

- **Laravel v13 support**
    - The `illuminate/*` constraints are `^12.0 || ^13.0`. Laravel v8 through v11 are no longer supported (see **Removed**).
- **PHPStan types for API responses**
    - `ApiClient` defines the `ApiResponse`, `ApiPaginatedResponse`, `ApiErrorResponse`, `ApiResponseStatus`, and `ApiResponseHeaders` types. Import them in your application with `@phpstan-import-type ApiResponse from ApiClient`.
    - `get()`, `post()`, `put()`, and `delete()` are documented as returning `ApiResponse` instead of `object`, and every `$connection` parameter is documented as `array<string, mixed>`.
    - **Why:** With a return type of `object`, PHPStan and Larastan couldn't check the `data`, `headers`, and `status` properties that you read from a response.
- The published config file has a docblock for each configuration key.

### Changed

#### Package Architecture

- **Renamed to `boldlygrow/gitlab-api-client` with the `BoldlyGrow\Gitlab` namespace**
    - The Composer package changed from `provisionesta/gitlab-api-client` to `boldlygrow/gitlab-api-client`.
    - The PHP namespace changed from `Provisionesta\Gitlab` to `BoldlyGrow\Gitlab`.
    - **Why:** The project is consolidating under the maintainer's `BoldlyGrow` organization alongside the related audit and actions packages.
    - The `glamstack/gitlab-sdk`, `gitlab-it/gitlab-sdk`, and `provisionesta/gitlab-api-client` package names are kept as `replace` aliases for a smoother upgrade.

#### Dependencies

- **Audit logging uses `boldlygrow/audit-log` instead of `provisionesta/audit`**
    - The imported class changed from `Provisionesta\Audit\Log` to `BoldlyGrow\AuditLog\AuditLog`, and log calls changed from `Log::create()` to `AuditLog::create()`. Event types and log levels are unchanged, except for the additions under **Fixed**.
    - **Why:** `provisionesta/audit` is deprecated, and `boldlygrow/audit-log` is its direct replacement under the maintainer's organization.
    - The constraint is `^2.0.1 || ^3.0`, since 2.0.0 rejected `CarbonImmutable` timestamps (see **Fixed**).
- **`nesbot/carbon` requires `^3.0`**
    - **Why:** Laravel v12 requires Carbon 3, so the `^2.72.2` range could no longer be installed.

#### Error Handling

- **Exception messages include the reason that GitLab returned**
    - The format is `METHOD CODE URL (Reason) <reason>`.
    - **Why:** Only the `message` key of the response body was read. GitLab returns parameter validation failures under `error` (ex. `{"error":"ref is missing"}`) and OAuth failures under `error_description`, so the reason for most `400` responses was discarded.
    - The `error_description`, `error`, and `message` keys are checked in that order, and the first one present is used. A `message` with validation errors keyed by attribute name is flattened to one line (ex. `name: has already been taken, path: can't be blank`).
    - A JSON body that uses none of these keys is appended (truncated to 1000 characters). An empty or non-JSON body, such as an HTML error page from Cloudflare, adds no reason.
    - The audit log `errors` array for a failed request contains the same parsed reason.
    - **No action is required** unless you parse exception message strings. The exception classes and the `GITLAB_API_EXCEPTIONS` behavior are unchanged.
- **`ServiceUnavailableException` and the Cloudflare exceptions use the same message format**
    - **Why:** `ServiceUnavailableException` was thrown with no message, and the Cloudflare exceptions were thrown with a `json_encode()` of the response body, which produced the string `null` for the HTML error pages that Cloudflare returns.
- **A decoded copy of the URL is added to the message**
    - It is appended when it differs from the requested URL (ex. `(Decoded) .../files/stations/lp01.json` for a file path encoded with `ApiClient::urlencode()`). The encoded URL is still shown first, because it is the one that reproduces the request.

#### Connections and Configuration

- **The token must be a `glpat-` access token**
    - Connection validation accepts personal, group, and project access tokens in the legacy `glpat-` format and in the newer format with a routing suffix (ex. `glpat-<token>.01.<suffix>`). 4.x rejected the newer format, because its `alpha_dash` rule doesn't allow the dots.
    - Any other token, such as an OAuth access token or a personal access token created before GitLab 14.5 without the `glpat-` prefix, now throws a `ConfigurationException`.
    - **How:** If `GITLAB_API_TOKEN` or a connection array `token` doesn't start with `glpat-`, replace it with a personal, group, or project access token before you upgrade.

#### Documentation

- **Release notes are consolidated in this file**
    - Adopted the Keep a Changelog format and replaced the per-version `changelog/*` files with this top-level `CHANGELOG.md`.

### Removed

- **Laravel v8, v9, v10, and v11 and PHP v8.0 and v8.1 are no longer supported**
    - The package requires PHP `^8.2` and Laravel `^12.0` or `^13.0`.
    - **Why:** These versions are past their maintenance windows. Every Laravel v11 release has unpatched security advisories, so Composer refuses to install it by default.
    - **How:** Upgrade your application to Laravel v12+ and PHP v8.2+ before moving to `boldlygrow/gitlab-api-client:^5.0`. Applications on older Laravel versions should stay on `provisionesta/gitlab-api-client:^4.0`.
- The `provisionesta/gitlab-laravel-actions` package suggestion and README references.

### Fixed

- **Every 4xx and 5xx response throws the exception for its status code**
    - In 4.x, a status code without its own log event type, such as `206`, `304`, `409`, `410`, `502`, or `504`, failed with an `ErrorException` (`Undefined array key`) while the response was being logged, even with `GITLAB_API_EXCEPTIONS=false`.
    - A `409` now throws `ConflictException`, which 4.x never reached. Any other 4xx or 5xx code without its own exception throws `BadRequestException` or `ServerErrorException`, as the README's exceptions table describes. The Cloudflare `52x` codes keep their own exceptions.
    - A `501` returned the response without an exception in 4.x, and now throws `ServerErrorException` when `GITLAB_API_EXCEPTIONS=true`.
    - A `409` is logged as `gitlab.api.{method}.error.conflict`. Other status codes without their own event type are logged as `gitlab.api.{method}.success` (2xx), `gitlab.api.{method}.error.client-error` (4xx), `gitlab.api.{method}.critical.server-error` (5xx), or `gitlab.api.{method}.warning.unexpected-status` (anything else). The README's **Event Types** table lists them.
    - **Why:** An `ErrorException` doesn't include the status code or the reason that GitLab returned, and it was thrown before the request was logged, so these failures couldn't be handled or searched for.
    - **How:** If you catch `ErrorException` around API calls, catch the exceptions in `BoldlyGrow\Gitlab\Exceptions` instead. If you handle a `501` response object with exceptions enabled, catch `ServerErrorException` instead.
- **Pagination handles more than one `Link` header and a missing next link**
    - A response with more than one `Link` header failed with a `TypeError` from `explode()`. Each header value is now read.
    - A response with an `X-Next-Page` header but no `rel="next"` link failed with a `TypeError`. It is now returned as a single page.
- The `gitlab.api.get.process.pagination.finished` log has the number of combined records in `count_records`, which was always missing because the records were counted after they were cast to an object.
- **`put()` and `delete()` build the same URL as `get()` and `post()`**
    - A URI with a leading slash, or a connection `url` with a trailing slash, sent a URL with a double slash (ex. `https://gitlab.com/api/v4//projects/123`).
    - **Why:** The docblocks say that the URI works with or without a leading slash, and `get()` and `post()` already trimmed both.
- **`delete()` logs its request data**
    - `delete()` didn't pass its `data` to the log, so `DELETE` request data was never logged, and the `log.request_data.delete` config and `GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED` had no effect.
    - As with the other methods, the `key` and `password` keys are never logged.
    - **How:** If your `DELETE` request data has values that shouldn't be logged, add their keys to `log.request_data.delete.excluded` in the published config, or set `GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED=false`.
- **Applications that use `CarbonImmutable` no longer get a `TypeError` on every request**
    - The request duration timestamp is typed as `CarbonInterface` instead of `Carbon`.
    - **Why:** With `Date::use(CarbonImmutable::class)`, `now()` returns a `CarbonImmutable`, which the `Carbon` type hint rejected.
    - The package requires `boldlygrow/audit-log` 2.0.1 or newer, since 2.0.0 had the same type hint.

### Upgrade Steps

1. **Upgrade Laravel and PHP first.** Your application must run Laravel v12 or v13 on PHP v8.2 or newer. Applications on older Laravel versions must upgrade the framework first, or stay on `provisionesta/gitlab-api-client:^4.0`.
2. **Check your token.** If `GITLAB_API_TOKEN` or a connection array `token` doesn't start with `glpat-`, replace it with a personal, group, or project access token.
3. **Update the package.** Require `boldlygrow/gitlab-api-client:^5.0` in `composer.json` and run `composer update`.
4. **Update the namespace.** Find and replace `Provisionesta\Gitlab` with `BoldlyGrow\Gitlab` across your code base.
5. **Update audit logger references.** If you reference the audit logger directly, replace `Provisionesta\Audit\Log` with `BoldlyGrow\AuditLog\AuditLog`, and `Log::create()` with `AuditLog::create()`.
6. **Review error handling.** Replace any `ErrorException` handling for unlisted status codes with the package exceptions (see **Fixed**).
7. **(Optional) Re-publish the config file** to get the new docblocks: `php artisan vendor:publish --tag=gitlab-api-client`.
8. This is not an exhaustive list. Review the `README.md` for the latest syntax and usage guidelines.

*Releases prior to 5.0 were developed on GitLab as `provisionesta/gitlab-api-client` (4.x), `gitlab-it/gitlab-sdk` (3.x), and `glamstack/gitlab-sdk` (2.x).*

## [4.2.0] - 2025-04-27

### Added

- **Laravel v12 support**
    - The `illuminate/*` constraints include `^12.0`, and PHP v8.4 is listed as supported.

### Changed

- The minimum `provisionesta/audit` version is `^1.2`.

## [4.1.0] - 2024-03-22

### Changed

- **`nesbot/carbon` allows `^3.0`**
    - The constraint is `^2.67 || ^3.0`.
    - **Why:** Laravel v11 applications can use Carbon 3, which the `^2.67` constraint didn't allow.
- The minimum `provisionesta/audit` version is `^1.1`.

## [4.0.0] - 2024-03-18

### Overview

This release focuses on developer experience. It simplifies or removes features that were no longer relevant based on lessons learned, and makes the API easier to use with named arguments.

- **The package is renamed** from `gitlab-it/gitlab-sdk` to `provisionesta/gitlab-api-client`.
- **Connection keys are removed.** The client uses one set of environment variables, and you can pass a connection array for anything else.
- **The API is static.** Use `ApiClient::get()` instead of `new ApiClient()`.
- **Exceptions are thrown for 4xx and 5xx responses by default.**

### Added

- Laravel v11 and PHP v8.3 support
- **Rate limit backoff**
    - When less than 20% of the rate limit remains, each request waits 10 seconds before continuing.
    - **Why:** There was no graceful way to handle the rate limit for large data sets, so long paginated requests failed. The delay gives the rate limit time to reset while the remaining pages are fetched.
- **Cloudflare exceptions for GitLab.com `52x` responses**
    - `520`, `521`, `522`, `523`, `524`, `525`, `526`, and `530` throw `CloudflareUnknownErrorException`, `CloudflareConnectionRefusedException`, `CloudflareRequestTimeoutException`, `CloudflareConnectionUnreachableException`, `CloudflareResponseTimeoutException`, `CloudflareSslHandshakeException`, `CloudflareSslCertificateException`, and `CloudflareInternalErrorException`.
    - A `PUT` request that returns a `520` is retried up to 10 times, 2 seconds apart, and the retry is logged as `gitlab.api.put.cloudflare.unknown.retrying`.
- **Request data log configuration**
    - Disable request data logging per method with `GITLAB_API_LOG_REQUEST_DATA_GET_ENABLED`, `GITLAB_API_LOG_REQUEST_DATA_POST_ENABLED`, `GITLAB_API_LOG_REQUEST_DATA_PUT_ENABLED`, and `GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED`.
    - Exclude specific keys in the `log.request_data.{method}.excluded` arrays of the published config. By default, `key` and `password` are excluded from `GET` requests, and `content` from `POST` and `PUT` requests.
    - **Why:** Request data makes logs more useful, but some keys hold secrets or large payloads, such as the base64 encoded content of repository files.
- Standardized logging with `provisionesta/audit`
- README examples for named arguments and Laravel Collections.

### Changed

#### Package Architecture

- **Renamed to `provisionesta/gitlab-api-client` with the `Provisionesta\Gitlab` namespace**
    - The Composer package changed from `gitlab-it/gitlab-sdk` to `provisionesta/gitlab-api-client`, and the PHP namespace changed from `GitlabIt\Gitlab` to `Provisionesta\Gitlab`.
    - **Why:** The maintainers are no longer in GitLab IT, so the project moved to the maintainer's personal open source namespace (codename Provisionesta) for ongoing contributions.
    - The license copyright owner changed from `GitLab B.V.` to `Jefferson Martin`.

#### Connections and Configuration

- **`GITLAB_API_URL` and `GITLAB_API_TOKEN` replace the per-connection variables**
    - `GITLAB_{CONNECTION}_BASE_URL` is now `GITLAB_API_URL`, and `GITLAB_{CONNECTION}_ACCESS_TOKEN` is now `GITLAB_API_TOKEN`.
    - **Why:** The variables match the package name, after the rename from `gitlab-sdk` to `gitlab-api-client`. No other URLs are configured, so `BASE_` was cosmetic and was dropped for simplicity.
    - **How:** Rename the variables in your `.env` file.
- **The config file is `config/gitlab-api-client.php`**
    - It is a flat array of the `.env` values, so you only need to publish it to rename the variables.
    - **Why:** With one set of environment variables and connections passed at runtime, a customized configuration file is no longer needed.

#### API Requests

- **The API client methods are static**
    - **How:** Refactor `$gitlab_api->get('endpoint')` to `ApiClient::get('endpoint')`, and remove each `new ApiClient()`.
- **The `request_data` argument is renamed to `data`**
    - **Why:** `data` is shorter to type with named arguments, and avoids naming variations such as `query_string` or `form_data`.
- **`connection_config` is renamed to `connection` and is the last argument**
    - **Why:** Without connection keys it is optional, so it follows the URI and data arguments. With named arguments, you can pass it in any position.
- **Exceptions are thrown for 4xx and 5xx responses by default**
    - **Why:** Applications needed a lot of code to check each status code. Throwing exceptions centrally lets you catch them only where it matters.
    - **How:** Set `GITLAB_API_EXCEPTIONS=false` to disable exceptions and check the `status` of each response instead.

#### API Responses

- **The `object` property is renamed to `data`**
    - **Why:** Without the `json` property (see **Removed**), the name `object` no longer made sense, and `data` matches other API schema standards.
- **The response keys are ordered `data`, `headers`, and `status`**
    - **Why:** `data` is what most code reads, so it comes first.

#### Logging

- **The log format and event types changed**
    - Logs use the `provisionesta/audit` format, and event types use dot notation (ex. `gitlab.api.get.success`). See the README's **Log Examples** for every event type.
    - **How:** Update any log platform searches or alerts to use the new `event_type` values.

### Removed

- **Connection keys**
    - **Why:** The advanced use cases for connection keys usually loaded credentials from a database or secrets manager. Developers frequently commented out one block of environment variables and uncommented another instead of switching the default connection. One set of variables aligns with common configuration in the Laravel ecosystem.
    - **How:** Pass a connection array with `url` and `token` at runtime for any other GitLab instance or token.
- **Custom log channels**
    - **Why:** Production applications usually export logs to a centralized service, often combined with `STDOUT` and `STDERR` for container or serverless environments. Using the application's default log channel or stack removes that complexity.
- **The `json` response property**
    - **Why:** It doubled the payload size of large responses, which increased memory use, and it was rarely used.
    - **How:** Use `json_encode($response->data)`.
- **`config/gitlab-sdk.php`**
    - **How:** Delete the file, and search your code base for `config('gitlab-sdk.` to replace any references.

### Upgrade Steps

1. Re-read the `README.md` file to see the latest syntax and usage guidelines.
2. Update your `.env` file.
    - Choose your primary connection.
    - Rename `GITLAB_{PRIMARY_CONNECTION}_BASE_URL` to `GITLAB_API_URL`, and `GITLAB_{PRIMARY_CONNECTION}_ACCESS_TOKEN` to `GITLAB_API_TOKEN`.
    - If you have additional connections, comment out those variables and pass connection arrays instead.
    - Exceptions are thrown for 4xx and 5xx errors. To disable them, add `GITLAB_API_EXCEPTIONS=false` to your `.env` file.
3. Delete the `config/gitlab-sdk.php` file.
4. Remove `gitlab-it/gitlab-sdk` from `composer.json`, add `"provisionesta/gitlab-api-client": "^4.0"`, and run `composer update`.
5. Find and replace `GitlabIt\Gitlab` with `Provisionesta\Gitlab` across your code base.
6. Search your code base for `config('gitlab-sdk.` and replace each reference, since the file no longer exists.
7. Search your code base where the API client is used.
    - `->json`: Use `json_encode($response->data)` instead.
    - `->object`: Use `->data` instead.
    - `new ApiClient`: Remove each instance, and refactor `$gitlab_api->get('endpoint')` to `ApiClient::get('endpoint')`.
    - `->status`: Catch the exceptions instead.
8. Update your centralized log platform to use the new `event_type` values.
9. This is not an exhaustive list. Evaluate all of your code that uses the API after reading the latest version of the README.

## [3.0.0] - 2023-05-22

### Overview

The `glamstack/gitlab-sdk` package is renamed to `gitlab-it/gitlab-sdk`, connections are configured in a new `config/gitlab-sdk.php` file, and 4xx and 5xx responses can throw exceptions. The configuration is not backwards compatible, so follow the **Upgrade Steps**.

### Added

- Laravel v10 and PHP v8.2 support
- **A `dev` connection key**
    - Set `GITLAB_DEV_BASE_URL` and `GITLAB_DEV_ACCESS_TOKEN` in your `.env` file.
    - **Why:** Developers often commented and uncommented different `GITLAB_COM_ACCESS_TOKEN` values in their `.env` file to switch environments.
- **Switch the default connection with `GITLAB_DEFAULT_CONNECTION`**
    - It applies to `new ApiClient()` without a connection key, and any connection key can be the default.
    - **Why:** It makes it easy to switch between environments during development, such as between `saas` and `dev`.
- Log channels per connection key
- **Exceptions for 4xx and 5xx responses**
    - Set `GITLAB_{CONNECTION}_EXCEPTIONS=true` to throw `GitlabIt\Gitlab\Exceptions\ApiResponseException` after the log entry is created.
    - **Why:** In v2, an error response appeared in the log but was rarely and inconsistently handled.
- **A log event type for each HTTP status code**
    - 400: `gitlab-api-bad-request-error` (warning)
    - 401: `gitlab-api-unauthorized-error` (critical)
    - 403: `gitlab-api-forbidden-error` (critical)
    - 404: `gitlab-api-not-found-error` (warning)
    - 429: `gitlab-api-rate-limit-error` (error), with the rate limit timestamps if they exist in the response headers
    - 5xx: `gitlab-api-response-server-error` (critical)
    - Other: `gitlab-api-response-unknown-error` (critical)

### Changed

- **Renamed to `gitlab-it/gitlab-sdk` with the `GitlabIt\Gitlab` namespace**
    - The `glamstack/gitlab-sdk` package is abandoned, and the PHP namespace changed from `Glamstack\Gitlab` to `GitlabIt\Gitlab`.
    - **How:** Require `gitlab-it/gitlab-sdk:^3.0`, and find and replace the namespace.
- **`config/gitlab-sdk.php` replaces `config/glamstack-gitlab.php`**
    - The configuration is not backwards compatible, so any custom configuration needs to be recreated in the new file.
    - The publish tag changed from `glamstack-gitlab` to `gitlab-sdk` (`php artisan vendor:publish --tag=gitlab-sdk`).
- **Connection keys are renamed**
    - `gitlab_com` is now `saas`, and `gitlab_private` is now `self_managed`.
- **A custom token is passed as a connection array**
    - In v2, the second argument of `new ApiClient('gitlab_com', 'Y0urT0k3nG0esH3r3')` was a token string.
    - In v3, it is an array in the same format as the connection configuration: `new ApiClient(null, ['base_url' => 'https://gitlab.com', 'access_token' => 'Y0urT0k3nG0esH3r3', 'log_channels' => 'single'])`.
- Response headers are an array instead of an object
- `get()`, `post()`, `put()`, and `delete()` return an `object` instead of `object|string`.
- Configuration and connection test errors throw a `GitlabIt\Gitlab\Exceptions\ConfigurationException` instead of an `abort()` HTTP exception.
- **Logging uses cross-SDK standards in the new `ResponseLog` trait**
    - The generic `gitlab-api-response-error` event type is replaced by an event type for each status code (see **Added**).
    - The `gitlab-api-config-missing-error`, `gitlab-api-config-override-warning`, and `gitlab-api-config-invalid-error` log messages, status codes, and context keys changed slightly.
    - In `gitlab-api-response-info`, the context array keys are alphabetical, and `gitlab_instance` is renamed to `connection_key`.
    - **How:** If your logging platform filters on specific event types, messages, or context keys, update those filters.
- **Versions use [Semantic Versioning](https://semver.org/)** instead of a modified [Calendar Versioning](https://calver.org/).
    - **How:** Use the `^3.0` constraint in `composer.json`.
- The package requires the individual `illuminate/*` components instead of `laravel/framework`.
- The license changed from Apache 2.0 to MIT.

### Removed

- **The `try`/`catch` around HTTP calls**
    - **Why:** Laravel's HTTP client doesn't throw client and server error exceptions, so it never ran. `ApiResponseException` covers client and server error responses instead.

### Fixed

- **Pagination uses the `X-Next-Page` header**
    - **Why:** The `X-Total-Pages` header that the loop counted is not included in responses with more than 10,000 records or for some endpoints ([GitLab documentation](https://docs.gitlab.com/ee/api/rest/index.html#pagination-response-headers)), so those responses were not paginated.

### Upgrade Steps

1. Remove `glamstack/gitlab-sdk` from `composer.json`, add `"gitlab-it/gitlab-sdk": "^3.0"`, and run `composer update`.
2. Publish the new configuration file with `php artisan vendor:publish --tag=gitlab-sdk`.
3. Find and replace `Glamstack\Gitlab` with `GitlabIt\Gitlab` across your code base.
4. If you only use GitLab.com SaaS:
    1. Find and replace `ApiClient('gitlab_com')` with `ApiClient()`.
    2. In your `.env` file, remove `GITLAB_COM_BASE_URL`, `GITLAB_PRIVATE_BASE_URL`, and `GITLAB_PRIVATE_ACCESS_TOKEN` if they exist.
    3. In your `.env` file, rename `GITLAB_COM_ACCESS_TOKEN` to `GITLAB_SAAS_ACCESS_TOKEN`.
    4. In your `.env` file, add `GITLAB_DEFAULT_CONNECTION="saas"` and `GITLAB_DEV_ACCESS_TOKEN=`.
5. If you only use a single GitLab self-managed instance:
    1. Find and replace `ApiClient('gitlab_private')` with `ApiClient()`.
    2. In your `.env` file, remove `GITLAB_COM_BASE_URL` if it exists. Remove `GITLAB_COM_ACCESS_TOKEN` if it is empty, or evaluate which connection it refers to if it has a value.
    3. In your `.env` file, rename `GITLAB_PRIVATE_BASE_URL` to `GITLAB_SELF_MANAGED_BASE_URL`, and `GITLAB_PRIVATE_ACCESS_TOKEN` to `GITLAB_SELF_MANAGED_ACCESS_TOKEN`.
    4. In your `.env` file, add `GITLAB_DEFAULT_CONNECTION="self_managed"`, `GITLAB_DEV_BASE_URL="https://gitlab.example.com"` (replace `gitlab.example.com`), and `GITLAB_DEV_ACCESS_TOKEN=`.
6. If you use a combination of GitLab.com SaaS and GitLab self-managed:
    1. Find and replace `ApiClient('gitlab_com')` with `ApiClient('saas')`, and `ApiClient('gitlab_private')` with `ApiClient('self_managed')`.
    2. In your `.env` file, remove `GITLAB_COM_BASE_URL` if it exists.
    3. In your `.env` file, rename `GITLAB_COM_ACCESS_TOKEN` to `GITLAB_SAAS_ACCESS_TOKEN`, `GITLAB_PRIVATE_BASE_URL` to `GITLAB_SELF_MANAGED_BASE_URL`, and `GITLAB_PRIVATE_ACCESS_TOKEN` to `GITLAB_SELF_MANAGED_ACCESS_TOKEN`.
    4. In your `.env` file, optionally add `GITLAB_DEFAULT_CONNECTION=`.
    5. In your `.env` file, add `GITLAB_DEV_BASE_URL="https://gitlab.com"` (or your self-managed URL) and `GITLAB_DEV_ACCESS_TOKEN=`.
7. If you have other self-managed or least privilege custom connection keys in `config/glamstack-gitlab.php`:
    1. Copy the connection keys and arrays to the `connections` array in `config/gitlab-sdk.php`.
    2. Check that the `.env` variable names match the `GITLAB_{CONNECTION_KEY}_BASE_URL` and `GITLAB_{CONNECTION_KEY}_ACCESS_TOKEN` naming convention.
8. If your `log_channels` in `config/glamstack-gitlab.php` is not `['single']`, copy it to the `log_channels` of each connection in `config/gitlab-sdk.php`. Log channels are configured separately for each connection key.
9. (Unlikely) Find and replace `config('glamstack-gitlab.gitlab_com` with `config('gitlab-sdk.connections.saas`, and `config('glamstack-gitlab.gitlab_private` with `config('gitlab-sdk.connections.self_managed`.
10. (Unlikely) Search for any other `config('glamstack-gitlab` references and update them to the new configuration.
11. (Unlikely) Search for a connection key followed by a second argument, such as `ApiClient('gitlab_com',`, to find any tokens passed as a string, and refactor them to a connection array.

## [2.1.14] - 2022-01-14

### Added

- First release of the GitLab SDK (`glamstack/gitlab-sdk`), maintained by GitLab IT Engineering for connecting Laravel applications to multiple GitLab instances.
- `ApiClient` class that can be used in any Laravel class, such as controllers, models, and services.
- **`get()`, `post()`, `put()`, and `delete()` methods**
    - `get()` supports list and single record requests, and automatically paginates with the `X-Total-Pages` header.
- Multiple GitLab instances, and different access tokens for groups and projects, configured as instance keys in `config/glamstack-gitlab.php`.
- **Logging for every API request**
    - Info and error log entries for each request, sent to one or more Laravel log channels.
    - The GitLab version is detected and included in each log entry.
- A `User-Agent` request header with the Composer package, Laravel, and PHP versions, which appears in the GitLab instance's audit logs.

[5.0.0]: https://github.com/boldlygrow/gitlab-api-client/compare/4.2...5.0.0
[4.2.0]: https://github.com/boldlygrow/gitlab-api-client/compare/4.1...4.2
[4.1.0]: https://github.com/boldlygrow/gitlab-api-client/compare/4.0...4.1
[4.0.0]: https://github.com/boldlygrow/gitlab-api-client/compare/3.0...4.0
[3.0.0]: https://github.com/boldlygrow/gitlab-api-client/compare/2.1.14...3.0
[2.1.14]: https://github.com/boldlygrow/gitlab-api-client/releases/tag/2.1.14
