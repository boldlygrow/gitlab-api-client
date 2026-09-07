<?php

return [
    /**
     * The URL of your GitLab instance
     *
     * Use `https://gitlab.com` for GitLab.com SaaS, or the FQDN of your
     * self-managed instance (ex. `https://gitlab.example.com`).
     */
    'url' => env('GITLAB_API_URL'),

    /**
     * The access token used to authenticate with the GitLab API
     *
     * This can be a personal, group, or project access token generated on your
     * GitLab instance (ex. `glpat-...`).
     *
     * @see https://docs.gitlab.com/ee/api/rest/#authentication
     */
    'token' => env('GITLAB_API_TOKEN'),

    /**
     * Whether PHP exceptions are thrown if the API experiences an error
     *
     * All requests including 4xx and 5xx errors are logged using the audit and
     * event log, including with ERROR and CRITICAL log levels. If your log
     * level in Laravel catches the problem with your bug report, then you may
     * not need these exceptions. If you want to handle problems behind the
     * scenes without users seeing an error message, then you can disable this
     * and inspect the `status` array returned in each response instead.
     *
     * @see vendor/boldlygrow/gitlab-api-client/src/Exceptions
     */
    'exceptions' => env('GITLAB_API_EXCEPTIONS', true),

    /**
     * Request data logging configuration
     *
     * The `data` key/value pairs sent with requests are logged to improve the
     * usefulness of logs. You can disable logging of `request_data` per method
     * with the `enabled` flag, and omit sensitive keys per method with the
     * `excluded` array (ex. `content` for base64 repository file payloads).
     */
    'log' => [
        'request_data' => [
            'get' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_GET_ENABLED', true),
                'excluded' => [
                    'key',
                    'password',
                ],
            ],
            'post' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_POST_ENABLED', true),
                'excluded' => [
                    'content', // https://docs.gitlab.com/ee/api/repository_files.html
                ],
            ],
            'put' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_PUT_ENABLED', true),
                'excluded' => [
                    'content', // https://docs.gitlab.com/ee/api/repository_files.html
                ],
            ],
            'delete' => [
                'enabled' => env('GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED', true),
                'excluded' => [],
            ],
        ],
    ],

    /**
     * The GitLab REST API version used to build request URLs
     *
     * GitLab currently uses `v4`. This is provided as a config value so the
     * version can be overridden without a package change if needed.
     */
    'version' => env('GITLAB_API_VERSION', 4),
];
