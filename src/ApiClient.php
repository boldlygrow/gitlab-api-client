<?php

namespace BoldlyGrow\Gitlab;

use BoldlyGrow\AuditLog\AuditLog;
use BoldlyGrow\Gitlab\Exceptions\BadRequestException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareConnectionRefusedException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareConnectionUnreachableException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareInternalErrorException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareRequestTimeoutException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareResponseTimeoutException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareSslCertificateException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareSslHandshakeException;
use BoldlyGrow\Gitlab\Exceptions\CloudflareUnknownErrorException;
use BoldlyGrow\Gitlab\Exceptions\ConfigurationException;
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
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiClient
{
    /**
     * Test the connection to the GitLab API using a simple API endpoint
     *
     * Example Usage:
     * ```php
     * use BoldlyGrow\Gitlab\ApiClient;
     * ApiClient::testConnection();
     * ```
     *
     * @link https://docs.gitlab.com/ee/api/version.html
     *
     * @param  array  $connection  (optional)
     *                             An array with `url` and `token`.
     *                             If not set, the `config('gitlab-api-client')` array will be used that
     *                             uses the GITLAB_API_* variables from your .env file.
     */
    public static function testConnection(array $connection = []): bool
    {
        $response = self::get(
            uri: 'version',
            connection: $connection
        );

        AuditLog::create(
            event_type: 'gitlab.api.test.success',
            level: 'debug',
            message: 'Success',
            method: __METHOD__,
            transaction: false
        );

        return true;
    }

    /**
     * Encode a string with GitLab URL Encoding Syntax
     *
     * When using project and file paths in URLs or query string URLs, you need
     * to use GitLab's URL encoding syntax.
     *
     * Example Usage:
     * ```
     * use BoldlyGrow\Gitlab\ApiClient;
     *
     * $response = ApiClient::get(
     *     uri: 'projects/' . ApiClient::urlencode('group_name/child_group_name/project_name')
     * );
     *
     * $response = ApiClient::get(
     *     uri: implode('', [
     *         'projects/' . $project_id . '/repository/files/',
     *         ApiClient::urlencode('app/Actions/ClassName.php'),
     *         '?ref=master'
     *     ])
     * );
     * ```
     *
     * @link https://docs.gitlab.com/ee/api/rest/index.html#namespaced-path-encoding
     * @link https://docs.gitlab.com/ee/api/rest/index.html#file-path-branches-and-tags-name-encoding
     */
    public static function urlencode(string $string): string
    {
        $string = urlencode($string);
        $string = str_replace('.', '%2E', $string);
        $string = str_replace('-', '%2D', $string);
        $string = str_replace('/', '%2F', $string);

        return $string;
    }

    /**
     * GitLab API Get Request
     *
     * Example Usage:
     * ```php
     * use BoldlyGrow\Gitlab\ApiClient;
     *
     * $response = ApiClient::get(
     *     uri: 'users/' . $id
     * );
     * ```
     *
     * @param  string  $uri
     *                              The URI with or without leading slash after `/api/v4/`
     * @param  array   $data        (optional)
     *                              Query data to apply to GET request
     * @param  array   $connection  (optional)
     *                              An array with `url` and `token`.
     *                              If not set, the `config('gitlab-api-client')` array will be used that
     *                              uses the GITLAB_API_* variables from your .env file.
     * @param  int     $per_page
     *                              The number of results for each paginated request. The default for the API is 20. To avoid rate limits, we
     *                              increase this to 100. This can be overridden by passing the argument in the `get()` method.
     *
     * @return object
     *                See parseApiResponse() method. The content and schema of the data
     *                array can be found in the API documentation for the endpoint.
     */
    public static function get(
        string $uri,
        array $data = [],
        array $connection = [],
        int $per_page = 100
    ): object {
        $connection = self::validateConnection($connection);
        $event_ms = now();

        try {
            $request = Http::withHeaders(self::getRequestHeaders($connection))->get(
                url: implode('/', [
                    rtrim($connection['url'], '/'),
                    'api/v' . config('gitlab-api-client.version'),
                    ltrim($uri, '/'),
                ]),
                query: array_merge(['per_page' => $per_page], $data)
            );
        } catch (RequestException $exception) {
            return self::handleException(
                exception: $exception,
                method: __METHOD__,
                uri: ltrim($uri, '/')
            );
        }

        $response = self::parseApiResponse($request);
        $query_string = $data ? '?' . http_build_query($data) : '';
        self::logResponse(
            event_ms: $event_ms,
            method: __METHOD__,
            url: implode('/', [
                rtrim($connection['url'], '/'),
                'api/v' . config('gitlab-api-client.version'),
                ltrim($uri, '/') . $query_string,
            ]),
            request_data: $data,
            response: $response
        );
        self::throwExceptionIfEnabled(
            method: 'get',
            url: implode('/', [
                rtrim($connection['url'], '/'),
                'api/v' . config('gitlab-api-client.version'),
                ltrim($uri, '/') . $query_string,
            ]),
            response: $response
        );

        if (self::checkForPagination($response->headers) == true) {
            AuditLog::create(
                event_type: 'gitlab.api.get.process.pagination.started',
                level: 'debug',
                message: 'Paginated Results Process Started',
                metadata: [
                    'uri' => ltrim($uri, '/'),
                ],
                method: __METHOD__,
                transaction: false
            );

            $response->paginated_results = self::getPaginatedResults(
                connection: $connection,
                paginated_url: self::generateNextPaginatedResultUrl(
                    headers: $response->headers,
                ),
                data: $response->data
            );

            // Parse API Response and convert to returnable object with expected format
            $response = self::parseApiResponse($response);

            $count_records = is_countable($response->data) ? count($response->data) : null;
            $duration_ms_per_record = $count_records ? (int) ($event_ms->diffInMilliseconds() / $count_records) : null;

            AuditLog::create(
                count_records: $count_records,
                duration_ms: $event_ms,
                duration_ms_per_record: $duration_ms_per_record,
                event_type: 'gitlab.api.get.process.pagination.finished',
                level: 'debug',
                message: 'Paginated Results Process Complete',
                metadata: [
                    'uri' => ltrim($uri, '/'),
                ],
                method: __METHOD__,
                transaction: false
            );
        }

        return $response;
    }

    /**
     * GitLab API POST Request
     *
     * This method is called from other services to perform a POST request and return a structured object.
     *
     * Example Usage:
     * ```php
     * use BoldlyGrow\Gitlab\ApiClient;
     *
     * $response = ApiClient::post(
     *     uri: 'projects',
     *     data: [
     *         'name' => 'My Cool Project',
     *         'path' => 'my-cool-project'
     *     ]
     * );
     * ```
     *
     * @param  string  $uri
     *                              The URI with or without leading slash after `/api/v4/`
     * @param  array   $data        (optional)
     *                              Post Body array
     * @param  array   $connection  (optional)
     *                              An array with `url` and `token`.
     *                              If not set, the `config('gitlab-api-client')` array will be used that
     *                              uses the GITLAB_API_* variables from your .env file.
     *
     * @return object
     *                See parseApiResponse() method. The content and schema of the data
     *                array can be found in the API documentation for the endpoint.
     */
    public static function post(
        string $uri,
        array $data = [],
        array $connection = []
    ): object {
        $connection = self::validateConnection($connection);
        $event_ms = now();

        try {
            $request = Http::withHeaders(self::getRequestHeaders($connection))->post(
                url: implode('/', [
                    rtrim($connection['url'], '/'),
                    'api/v' . config('gitlab-api-client.version'),
                    ltrim($uri, '/'),
                ]),
                data: $data
            );
        } catch (RequestException $exception) {
            return self::handleException(
                exception: $exception,
                method: __METHOD__,
                uri: ltrim($uri, '/')
            );
        }

        $response = self::parseApiResponse($request);
        self::logResponse(
            event_ms: $event_ms,
            method: __METHOD__,
            url: implode('/', [
                rtrim($connection['url'], '/'),
                'api/v' . config('gitlab-api-client.version'),
                ltrim($uri, '/'),
            ]),
            request_data: $data,
            response: $response
        );
        self::throwExceptionIfEnabled(
            method: 'post',
            url: implode('/', [
                rtrim($connection['url'], '/'),
                'api/v' . config('gitlab-api-client.version'),
                ltrim($uri, '/'),
            ]),
            response: $response
        );

        return $response;
    }

    /**
     * GitLab API PUT Request
     *
     * This method is called from other services to perform a PUT request and
     * return a structured object.
     *
     * Example Usage:
     * ```php
     * use BoldlyGrow\Gitlab\ApiClient;
     *
     * $project_id = '12345678';
     * $response = ApiClient::put(
     *     uri: 'projects/' . $project_id,
     *     data: [
     *         'description' => 'This is an updated project description'
     *     ]
     * );
     * ```
     *
     * @param  string  $uri
     *                              The URI with or without leading slash after `/api/v4/`
     * @param  array   $data        (optional)
     *                              Request data to send with PUT request
     * @param  array   $connection  (optional)
     *                              An array with `url` and `token`.
     *                              If not set, the `config('gitlab-api-client')` array will be used that
     *                              uses the GITLAB_API_* variables from your .env file.
     *
     * @return object
     *                See parseApiResponse() method. The content and schema of the data
     *                array can be found in the API documentation for the endpoint.
     */
    public static function put(
        string $uri,
        array $data = [],
        array $connection = []
    ): object {
        $connection = self::validateConnection($connection);
        $event_ms = now();

        try {
            $request = Http::withHeaders(self::getRequestHeaders($connection))->put(
                url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . $uri,
                data: $data
            );
        } catch (RequestException $exception) {
            return self::handleException(
                exception: $exception,
                method: __METHOD__,
                uri: ltrim($uri, '/')
            );
        }

        if ($request->status() === 520) {
            AuditLog::create(
                errors: [],
                event_ms: $event_ms,
                event_type: implode('.', [
                    'gitlab',
                    'api',
                    'put',
                    'cloudflare',
                    'unknown',
                    'retrying',
                ]),
                level: 'warning',
                message: 'Sleeping and Retrying Request',
                metadata: [
                    'uri' => $uri,
                ],
                method: __METHOD__,
                transaction: false
            );

            $i = 1;

            do {
                $request = Http::withHeaders(self::getRequestHeaders($connection))->put(
                    url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . $uri,
                    data: $data
                );

                sleep(2);

                $i++;
            } while ($request->status() == 520 && $i <= 10);
        }

        $response = self::parseApiResponse($request);

        self::logResponse(
            event_ms: $event_ms,
            method: __METHOD__,
            url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . ltrim($uri, '/'),
            request_data: $data,
            response: $response
        );

        self::throwExceptionIfEnabled(
            method: 'put',
            url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . ltrim($uri, '/'),
            response: $response
        );

        return $response;
    }

    /**
     * GitLab API DELETE Request
     *
     * This method is called from other services to perform a DELETE request and return a structured object.
     *
     * Example Usage:
     * ```php
     * use BoldlyGrow\Gitlab\ApiClient;
     *
     * $user_id = '12345678';
     * $response = ApiClient::delete('users/' . $user_id);
     * ```
     *
     * @param  string  $uri
     *                              The URI with or without leading slash after `/api/v4/`
     * @param  array   $data        (optional)
     *                              Request data to send with DELETE request
     * @param  array   $connection  (optional)
     *                              An array with `url` and `token`.
     *                              If not set, the `config('gitlab-api-client')` array will be used that
     *                              uses the GITLAB_API_* variables from your .env file.
     *
     * @return object
     *                See parseApiResponse() method. The content and schema of the object
     *                and json arrays can be found in the REST API documentation for the
     *                specific endpoint.
     */
    public static function delete(string $uri, array $data = [], array $connection = []): object
    {
        $connection = self::validateConnection($connection);
        $event_ms = now();

        try {
            $request = Http::withHeaders(self::getRequestHeaders($connection))->delete(
                url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . ltrim($uri, '/'),
                data: $data
            );
        } catch (RequestException $exception) {
            return self::handleException(
                exception: $exception,
                method: __METHOD__,
                uri: ltrim($uri, '/')
            );
        }

        $response = self::parseApiResponse($request);
        self::logResponse(
            event_ms: $event_ms,
            method: __METHOD__,
            url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . ltrim($uri, '/'),
            response: $response
        );
        self::throwExceptionIfEnabled(
            method: 'delete',
            url: $connection['url'] . '/api/v' . config('gitlab-api-client.version') . '/' . ltrim($uri, '/'),
            response: $response
        );

        return $response;
    }

    /**
     * Validate connection config array
     *
     * @param  array  $connection
     *                             An array with `url` and `token`.
     */
    private static function validateConnection(array $connection): array
    {
        if (empty($connection)) {
            $connection = config('gitlab-api-client');
        }

        $validator = Validator::make($connection, [
            'url' => 'required|url:https',
            // Matches legacy glpat-XXXX (20 chars) OR modern glpat-XXXX.XX.XXXXX formats
            'token' => [
                'required',
                'regex:/^glpat-[A-Za-z0-9_-]{20,40}(\.[a-z0-9]{2}\.[a-z0-9]{5,15})?$/',
            ],
        ]);

        if ($validator->fails()) {
            AuditLog::create(
                errors: $validator->errors()->all(),
                event_type: 'gitlab.api.validate.error',
                level: 'critical',
                message: 'Error',
                method: __METHOD__,
                transaction: true
            );
            throw new ConfigurationException(implode(' ', [
                'Gitlab API configuration validation error.',
                'This occurred in ' . __METHOD__ . '.',
                '(Solution) ' . implode(' ', $validator->errors()->all()),
            ]));
        }

        return $validator->validated();
    }

    /**
     * Set the request headers for the GitLab API request
     *
     * @param  array  $connection
     *                             An array with `url` and `token`.
     */
    private static function getRequestHeaders(array $connection): array
    {
        $composer_array = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $package_name = Str::title($composer_array['name']);

        return [
            'Authorization' => 'Bearer ' . $connection['token'],
            'User-Agent' => implode(' ', [
                $package_name,
                'boldlygrow/gitlab-api-client',
                'Laravel/' . app()->version(),
                'PHP/' . phpversion(),
            ]),
        ];
    }

    /**
     * Convert API Response Headers to Array
     *
     * This method is called from the parseApiResponse method to prettify the Guzzle Headers that are an array with
     * nested array for each value, and converts the single array values into strings and converts to an object for
     * easier and consistent accessibility with the parseApiResponse format.
     *
     * @param  array  $header_response
     *                                  [
     *                                  "Date" => [
     *                                  "Tue, 02 Nov 2021 16:00:30 GMT",
     *                                  ],
     *                                  "Content-Type" => [
     *                                  "application/json",
     *                                  ],
     *                                  "Transfer-Encoding" => [
     *                                  "chunked",
     *                                  ],
     *                                  "Connection" => [
     *                                  "keep-alive",
     *                                  ],
     *                                  "Cache-Control" => [
     *                                  "max-age=0, private, must-revalidate",
     *                                  ],
     *                                  "Etag" => [
     *                                  "W/"ef80161dad0045459a87879e4d6b0769"",
     *                                  ],
     *                                  ...(truncated)
     *                                  ]
     *
     * @return array
     *               {
     *               "Date" => "Tue, 02 Nov 2021 16:28:37 GMT",
     *               "Content-Type" => "application/json",
     *               "Transfer-Encoding" => "chunked",
     *               "Connection" => "keep-alive",
     *               "Cache-Control" => "max-age=0, private, must-revalidate",
     *               "Etag" => "W/"534830b145cda36bcd6bcd91c3ed3742"",
     *               "Link": (truncated),
     *               "Vary" => "Origin",
     *               "X-Content-Type-Options" => "nosniff",
     *               "X-Frame-Options" => "SAMEORIGIN",
     *               "X-Next-Page" => "",
     *               "X-Page" => "1",
     *               "X-Per-Page" => "20",
     *               "X-Prev-Page" => "",
     *               "X-Request-Id" => "01FKGQPA4V7TPC70J60J72GJ30",
     *               "X-Runtime" => "0.148641",
     *               "X-Total" => "1",
     *               "X-Total-Pages" => "1",
     *               "RateLimit-Observed" => "2",
     *               "RateLimit-Remaining" => "1998",
     *               "RateLimit-Reset" => "1635870577",
     *               "RateLimit-ResetTime" => "Tue, 02 Nov 2021 16:29:37 GMT",
     *               "RateLimit-Limit" => "2000",
     *               "GitLab-LB" => "fe-14-lb-gprd",
     *               "GitLab-SV" => "localhost",
     *               "CF-Cache-Status" => "DYNAMIC",
     *               "Expect-CT" => "max-age=604800, report-uri="https://report-uri.cloudflare.com/cdn-cgi/beacon/expect-ct"",
     *               "Strict-Transport-Security" => "max-age=31536000",
     *               "Server" => "cloudflare",
     *               "CF-RAY" => "6a7ebcad3ce908db-SEA",
     *               }
     */
    private static function convertHeadersToArray(array $header_response): array
    {
        $headers = [];

        foreach ($header_response as $header_key => $header_value) {
            if (is_array($header_value)) {
                // If array has multiple keys, leave as array
                if (count($header_value) > 1) {
                    $headers[$header_key] = $header_value;
                } else {
                    $headers[$header_key] = $header_value[0];
                }
            } else {
                $headers[$header_key] = $header_value;
            }
        }

        return $headers;
    }

    /**
     * Check if the responses uses pagination and contains multiple pages
     *
     * @see https://docs.gitlab.com/ee/api/rest/index.html#other-pagination-headers
     *
     * @param  array  $headers
     *                          API response headers from API request or parsed response.
     *
     * @return bool
     *              True if the response requires multiple pages
     *              False if response is a single page
     */
    private static function checkForPagination(array $headers): bool
    {
        if (array_key_exists('x-next-page', $headers) && $headers['x-next-page'] != '') {
            return true;
        } elseif (array_key_exists('X-Next-Page', $headers) && $headers['X-Next-Page'] != '') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Parse the header array for the paginated URL that contains `next`.
     *
     * @see https://docs.gitlab.com/ee/api/rest/index.html#pagination-link-header
     *
     * @param  array  $headers
     *                          API response headers from GitLab request or parsed response.
     *
     * @return ?string
     *                 https://gitlab.com/api/v4/projects/123456/issues/236/notes?page=3&per_page=100
     */
    private static function generateNextPaginatedResultUrl(
        array $headers,
    ): ?string {
        $links = [];
        if (array_key_exists('Link', $headers)) {
            $links = explode(', ', $headers['Link']);
        } elseif (array_key_exists('link', $headers)) {
            $links = explode(', ', $headers['link']);
        }

        foreach ($links as $link_key => $link_url) {
            if (Str::contains($link_url, 'next')) {
                // Remove the '<' and '>; rel="next"' that is around the next api_url
                // Before: <https://gitlab.com/api/v4/projects/123456/issues/236/notes?page=3&per_page=100>; rel="next"
                // After: https://gitlab.com/api/v4/projects/123456/issues/236/notes?page=3&per_page=100
                $url_query = Str::remove('<', $links[$link_key]);
                $url_query = Str::remove('>; rel="next"', $url_query);

                return $url_query;
            }
        }

        return null;
    }

    /**
     * Helper function used to get GitLab API results that require pagination.
     *
     * @link https://docs.gitlab.com/ee/api/rest/#pagination
     *
     * @param  array   $connection
     *                                 An array with `url` and `token`.
     * @param  string  $paginated_url
     *                                 The paginated URL generated in the get() method
     * @param  array   $data
     *                                 An array of records from the first page to append to paginated results
     *
     * @return array
     *               An array of the response objects for each page combined.
     */
    private static function getPaginatedResults(
        array $connection,
        string $paginated_url,
        array $data = []
    ): array {
        do {
            $event_ms = now();

            $request = Http::withHeaders(self::getRequestHeaders($connection))->get(
                url: $paginated_url
            );

            $response = self::parseApiResponse($request);
            self::logResponse(
                event_ms: $event_ms,
                method: __METHOD__,
                url: $paginated_url,
                response: $response
            );
            self::throwExceptionIfEnabled(
                method: 'get|paginated',
                url: $paginated_url,
                response: $response
            );

            $data[] = $response->data;

            if (self::checkForPagination($response->headers)) {
                $paginated_url = self::generateNextPaginatedResultUrl($response->headers);
            } else {
                $paginated_url = null;
            }
        } while ($paginated_url != null);

        return collect($data)->flatten(1)->toArray();
    }

    /**
     * Parse the API response and return custom formatted response for consistency
     *
     * @link https://laravel.com/docs/10.x/http-client#making-requests
     *
     * @param  object  $response  Response object from API results
     *
     * @return object Custom response returned for consistency
     *                {
     *                +"data": {
     *                +"id": 12345678
     *                +"name": "Dade Murphy"
     *                +"username": "z3r0c00l"
     *                +"state": "active"
     *                }
     *                +"headers": {
     *                +"Date": "Fri, 12 Nov 2021 20:13:55 GMT"
     *                +"Content-Type": "application/json"
     *                +"Content-Length": "1623"
     *                +"Connection": "keep-alive"
     *                }
     *                +"status": {
     *                +"code": 200
     *                +"ok": true
     *                +"successful": true
     *                +"failed": false
     *                +"serverError": false
     *                +"clientError": false
     *                }
     *                }
     */
    private static function parseApiResponse(object $response): object
    {
        if (property_exists($response, 'paginated_results')) {
            return (object) [
                'data' => (object) $response->paginated_results,
                'headers' => self::convertHeadersToArray($response->headers),
                'status' => $response->status,
            ];
        } else {
            return (object) [
                'data' => $response->object(),
                'headers' => self::convertHeadersToArray($response->headers()),
                'status' => (object) [
                    'code' => $response->status(),
                    'ok' => $response->ok(),
                    'successful' => $response->successful(),
                    'failed' => $response->failed(),
                    'serverError' => $response->serverError(),
                    'clientError' => $response->clientError(),
                ],
            ];
        }
    }

    /**
     * Handle GitLab API Exception
     *
     * @param  RequestException  $exception  An instance of the exception
     * @param  string            $method
     *                                       The upstream method that invoked this method for traceability
     *                                       Ex. __METHOD__
     * @param  string            $uri
     *                                       HTTP Request URI
     *
     * @return object
     *                {
     *                +"error": {
     *                +"code": "<string>"
     *                +"message": "<string>"
     *                +"method": "<string>"
     *                +"uri": "<string>"
     *                }
     *                +"status": {
     *                +"code": 400
     *                +"ok": false
     *                +"successful": false
     *                +"failed": true
     *                +"serverError": false
     *                +"clientError": true
     *                }
     */
    private static function handleException(
        RequestException $exception,
        $method,
        $uri
    ): object {
        AuditLog::create(
            errors: [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ],
            event_type: 'gitlab.api.' . explode('::', $method)[1] . '.error.http.exception',
            level: 'error',
            message: 'HTTP Response Exception',
            metadata: [
                'uri' => ltrim($uri, '/'),
            ],
            method: $method,
            transaction: true
        );

        return (object) [
            'error' => (object) [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'method' => $method,
                'uri' => ltrim($uri, '/'),
            ],
            'status' => (object) [
                'code' => $exception->getCode(),
                'ok' => false,
                'successful' => false,
                'failed' => true,
                'serverError' => true,
                'clientError' => false,
            ],
        ];
    }

    /**
     * Create a log entry for an API call
     *
     * This method is called from other methods and create log entry and throw exception
     *
     * @param  string            $method
     *                                      The upstream method that invoked this method for traceability
     *                                      Ex. __METHOD__
     * @param  string            $url
     *                                      The URL of the API call including the concatenated base URL and URI
     * @param  object            $response
     *                                      The raw unformatted HTTP client response
     * @param  ?CarbonInterface  $event_ms
     *                                      A process start timestamp used to calculate duration in ms for logs
     */
    private static function logResponse(
        string $method,
        string $url,
        object $response,
        array $request_data = [],
        ?CarbonInterface $event_ms = null
    ): void {
        $log_type = [
            200 => ['event_type' => 'success', 'level' => 'debug'],
            201 => ['event_type' => 'success', 'level' => 'debug'],
            202 => ['event_type' => 'success', 'level' => 'debug'],
            204 => ['event_type' => 'success', 'level' => 'debug'],
            400 => ['event_type' => 'warning.bad-request', 'level' => 'warning'],
            401 => ['event_type' => 'error.unauthorized', 'level' => 'error'],
            403 => ['event_type' => 'error.forbidden', 'level' => 'error'],
            404 => ['event_type' => 'warning.not-found', 'level' => 'warning'],
            405 => ['event_type' => 'error.method-not-allowed', 'level' => 'error'],
            412 => ['event_type' => 'error.precondition-failed', 'level' => 'error'],
            422 => ['event_type' => 'error.unprocessable', 'level' => 'error'],
            429 => ['event_type' => 'critical.rate-limit', 'level' => 'critical'],
            500 => ['event_type' => 'critical.server-error', 'level' => 'critical'],
            501 => ['event_type' => 'error.not-implemented', 'level' => 'error'],
            503 => ['event_type' => 'critical.server-unavailable', 'level' => 'critical'],
            520 => ['event_type' => 'critical.cloudflare.unknown.retryable', 'level' => 'warning'],
            521 => ['event_type' => 'critical.cloudflare.connection-refused', 'level' => 'critical'],
            522 => ['event_type' => 'critical.cloudflare.request-timeout', 'level' => 'critical'],
            523 => ['event_type' => 'critical.cloudflare.connection-unreachable', 'level' => 'critical'],
            524 => ['event_type' => 'critical.cloudflare.response-timeout', 'level' => 'critical'],
            525 => ['event_type' => 'critical.cloudflare.ssl-handshake', 'level' => 'critical'],
            526 => ['event_type' => 'critical.cloudflare.ssl-certificate', 'level' => 'critical'],
            530 => ['event_type' => 'critical.cloudflare.internal-error', 'level' => 'critical'],
        ];

        $errors = [];
        if (isset($response->data->message)) {
            $errors['message'] = $response->data->message;
        }

        $message = 'Success';
        if ($response->status->clientError) {
            $message = 'Client Error';
        }
        if ($response->status->serverError) {
            $message = 'Server Error';
        }

        $count_records = null;
        if ($response->status->ok && is_countable($response->data)) {
            $count_records = count($response->data);
        }

        $event_ms_per_record = null;
        if ($event_ms && $count_records && $count_records > 1) {
            $event_ms_per_record = (int) ($event_ms->diffInMilliseconds() / $count_records);
        }

        $metadata = [];
        $metadata['url'] = $url;

        $rate_limit_remaining = null;
        if (isset($response->headers['RateLimit-Remaining'])) {
            $rate_limit_remaining = $response->headers['RateLimit-Remaining'];
            $metadata['rate_limit_remaining'] = $rate_limit_remaining;
        } elseif (isset($response->headers['ratelimit-remaining'])) {
            $rate_limit_remaining = $response->headers['ratelimit-remaining'];
            $metadata['rate_limit_remaining'] = $rate_limit_remaining;
        }

        $method_suffix = explode('::', $method)[1];

        if (! empty($request_data)) {
            unset($request_data['key']);
            unset($request_data['password']);

            // If the method is in config/gitlab-api-client.php, check if request_data is enabled and whether any keys
            // should be excluded from being logged (usually for sanitization or length reasons)
            if (in_array($method_suffix, array_keys(config('gitlab-api-client.log.request_data')))) {
                if (config('gitlab-api-client.log.request_data.' . $method_suffix . '.enabled')) {
                    $metadata['request_data'] = collect($request_data)->except(
                        config('gitlab-api-client.log.request_data.' . $method_suffix . '.excluded')
                    )->toArray();
                }
            } else {
                $metadata['request_data'] = $request_data;
            }
        }

        AuditLog::create(
            count_records: $count_records,
            errors: $errors,
            event_ms: $event_ms,
            event_ms_per_record: $event_ms_per_record,
            event_type: implode('.', [
                'gitlab',
                'api',
                $method_suffix,
                $log_type[$response->status->code]['event_type'],
            ]),
            level: $log_type[$response->status->code]['level'],
            message: $message,
            metadata: $metadata,
            method: $method,
            transaction: false
        );

        if ($rate_limit_remaining) {
            self::checkIfRateLimitApproaching($method, $url, $response);
            self::checkIfRateLimitExceeded($method, $url, $response);
        }
    }

    /**
     * Throw an exception for a 4xx or 5xx response for an API call
     *
     * This method checks whether the .env variable or config value for `GITLAB_API_EXCEPTIONS=true`
     *
     * @param  string  $method
     *                            The lowercase name of the method that calls this function (ex. `get`)
     * @param  string  $url
     *                            The URL of the API call including the concatenated base URL and URI
     * @param  object  $response
     *                            The HTTP response formatted with $this->parseApiResponse()
     *
     * @throws BadRequestException
     * @throws CloudflareConnectionRefusedException
     * @throws CloudflareConnectionUnreachableException
     * @throws CloudflareRequestTimeoutException
     * @throws CloudflareResponseTimeoutException
     * @throws CloudflareSslCertificateException
     * @throws CloudflareSslHandshakeException
     * @throws CloudflareUnknownErrorException
     * @throws ConflictException
     * @throws ForbiddenException
     * @throws MethodNotAllowedException
     * @throws NotFoundException
     * @throws PreconditionFailedException
     * @throws RateLimitException
     * @throws ServerErrorException
     * @throws UnauthorizedException
     * @throws UnprocessableException
     */
    private static function throwExceptionIfEnabled(
        string $method,
        string $url,
        object $response
    ): void {
        if (config('gitlab-api-client.exceptions') == true) {
            $message = implode(' ', [
                Str::upper($method),
                $response->status->code,
                $url,
                isset($response->data->message) ? $response->data->message : null,
            ]);

            switch ($response->status->code) {
                case 400:
                    throw new BadRequestException($message);
                case 401:
                    $message = implode(' ', [
                        'The `GITLAB_API_TOKEN` has been configured but is invalid.',
                        '(Reason) This usually happens if it does not exist, expired, or does not have permissions.',
                        '(Solution) Please generate a new API Token and update the variable in your `.env` file.',
                    ]);
                    throw new UnauthorizedException($message);
                case 403:
                    throw new ForbiddenException($message);
                case 404:
                    throw new NotFoundException($message);
                case 405:
                    throw new MethodNotAllowedException($message);
                case 409:
                    throw new ConflictException($message);
                case 412:
                    throw new PreconditionFailedException($message);
                case 422:
                    throw new UnprocessableException($message);
                case 429:
                    throw new RateLimitException($message);
                case 500:
                    throw new ServerErrorException(json_encode($response->data));
                case 503:
                    throw new ServiceUnavailableException();
                case 520:
                    throw new CloudflareUnknownErrorException(json_encode($response->data));
                case 521:
                    throw new CloudflareConnectionRefusedException(json_encode($response->data));
                case 522:
                    throw new CloudflareRequestTimeoutException(json_encode($response->data));
                case 523:
                    throw new CloudflareConnectionUnreachableException(json_encode($response->data));
                case 524:
                    throw new CloudflareResponseTimeoutException(json_encode($response->data));
                case 525:
                    throw new CloudflareSslHandshakeException(json_encode($response->data));
                case 526:
                    throw new CloudflareSslCertificateException(json_encode($response->data));
                case 530:
                    throw new CloudflareInternalErrorException(json_encode($response->data));
            }
        }
    }

    /**
     * Create a warning log entry for an API call if the rate limit remaining is less than 10 percent
     *
     * @param  string  $method
     *                            The lowercase name of the method that calls this function (ex. `get`)
     * @param  string  $url
     *                            The URL of the API call including the concatenated base URL and URI
     * @param  object  $response
     *                            The HTTP response formatted with $this->parseApiResponse()
     */
    private static function checkIfRateLimitApproaching(
        string $method,
        string $url,
        object $response
    ): void {
        $rate_limit_remaining = null;
        $percent_remaining = null;
        if (isset($response->headers['RateLimit-Remaining'])) {
            $rate_limit_remaining = (int) $response->headers['RateLimit-Remaining'];
            $rate_limit = (int) $response->headers['RateLimit-Limit'];
            $percent_remaining = round(($rate_limit_remaining / $rate_limit) * 100);
        } elseif (isset($response->headers['ratelimit-remaining'])) {
            $rate_limit_remaining = (int) $response->headers['ratelimit-remaining'];
            $rate_limit = (int) $response->headers['ratelimit-limit'];
            $percent_remaining = round(($rate_limit_remaining / $rate_limit) * 100);
        }

        if ($rate_limit_remaining && $percent_remaining <= 20) {
            AuditLog::create(
                event_type: 'gitlab.api.rate-limit.approaching',
                level: 'critical',
                message: implode(' ', [
                    'Rate Limit Approaching (' . $percent_remaining . '% Remaining).',
                    'Sleeping for 10 seconds between requests to let the API catch a breath.',
                ]),
                metadata: [
                    'gitlab_rate_limit_limit' => $response->headers['RateLimit-Limit'] ?? null,
                    'gitlab_rate_limit_percent' => $percent_remaining,
                    'gitlab_rate_limit_remaining' => $response->headers['RateLimit-Remaining'] ?? null,
                    'gitlab_rate_limit_used' => $response->headers['RateLimit-Observed'] ?? null,
                    'url' => $url,
                ],
                method: $method,
                transaction: false
            );

            sleep(10);
        }
    }

    /**
     * Create an error log entry for an API call if the rate limit remaining is equal to zero (0) or one (1),
     * indicating that this is the last request that will be successful.
     *
     * @param  string  $method
     *                            The upstream method that invoked this method for traceability
     *                            Ex. __METHOD__
     * @param  string  $url
     *                            The URL of the API call including the concatenated base URL and URI
     * @param  object  $response
     *                            The HTTP response formatted with $this->parseApiResponse()
     */
    private static function checkIfRateLimitExceeded(
        string $method,
        string $url,
        object $response
    ): void {

        $rate_limit_remaining = null;
        $percent_remaining = null;
        if (isset($response->headers['RateLimit-Remaining'])) {
            $rate_limit_remaining = (int) $response->headers['RateLimit-Remaining'];
            $rate_limit = (int) $response->headers['RateLimit-Limit'];
            $percent_remaining = round(($rate_limit_remaining / $rate_limit) * 100);
        } elseif (isset($response->headers['ratelimit-remaining'])) {
            $rate_limit_remaining = (int) $response->headers['ratelimit-remaining'];
            $rate_limit = (int) $response->headers['ratelimit-limit'];
            $percent_remaining = round(($rate_limit_remaining / $rate_limit) * 100);
        }

        if ($rate_limit_remaining && $rate_limit_remaining <= 1) {
            AuditLog::create(
                event_type: 'gitlab.api.rate-limit.exceeded',
                level: 'critical',
                message: implode(' ', [
                    'Rate Limit Exceeded.',
                    'This request should be refactored so we do not cause the API any further harm.',
                ]),
                metadata: [
                    'gitlab_rate_limit_limit' => $response->headers['RateLimit-Limit'] ?? null,
                    'gitlab_rate_limit_percent' => $percent_remaining,
                    'gitlab_rate_limit_remaining' => $response->headers['RateLimit-Remaining'] ?? null,
                    'gitlab_rate_limit_used' => $response->headers['RateLimit-Observed'] ?? null,
                    'gitlab_rate_limit_reset_timestamp' => $response->headers['RateLimit-Reset'] ?? null,
                    'gitlab_rate_limit_reset_datetime' => $response->headers['RateLimit-ResetTime'] ?? null,
                    'url' => $url,
                ],
                method: $method,
                transaction: true
            );

            throw new RateLimitException('GitLab API rate limit exceeded. See logs for details.');
        }
    }
}
