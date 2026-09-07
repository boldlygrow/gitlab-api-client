# GitLab API Client

[[_TOC_]]

## Overview

The GitLab API Client is an open source [Composer](https://getcomposer.org/) package for use in Laravel applications for connecting to GitLab SaaS or self-managed instances for provisioning and deprovisioning of users, groups, projects, and other related functionality.

Please use at your own risk and create merge requests for any bugs that you encounter.

### Problem Statement

Instead of providing an SDK method for every endpoint in the API documentation, we have taken a simpler approach by providing a universal `ApiClient` that can perform `GET`, `POST`, `PUT`, and `DELETE` requests to any endpoint that you find in the [GitLab API documentation](https://docs.gitlab.com/ee/api/api_resources.html).

This builds upon the simplicity of the [Laravel HTTP Client](https://laravel.com/docs/10.x/http-client) that is powered by the [Guzzle HTTP client](http://docs.guzzlephp.org/en/stable/) to provide "last lines of code parsing" for GitLab API responses to improve the developer experience.

The value of this API Client is that it handles the API request logging, response pagination, rate limit backoff, and 4xx/5xx exception handling for you.

### Example Usage

```php
use BoldlyGrow\Gitlab\ApiClient;

// Get a list of records (positional arguments)
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$projects = ApiClient::get('/projects');

// Get list of records (named arguments)
$projects = ApiClient::get(
    uri: '/projects'
);

// Search for records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$projects = ApiClient::get(
    uri: '/projects',
    data: [
        'search' => 'my-project-name',
        'membership' => true
    ]
);

// Get a specific record (positional arguments)
// https://docs.gitlab.com/ee/api/projects.html#get-single-project
$project = ApiClient::get('/projects/123456789');

// Get a specific record with URL encoded path
$project = ApiClient::get('/projects/' . ApiClient::urlencode('group-name/child-group-name/project-name'));

// Create a project
// https://docs.gitlab.com/ee/api/projects.html#create-project
$group_id = '12345678';
$record = ApiClient::post(
    uri: '/projects',
    data: [
        'name' => 'My Cool Project',
        'path' => 'my-cool-project',
        'namespace_id' => $group_id
    ]
);

// Update a project
// https://docs.gitlab.com/ee/api/projects.html#edit-project
$project_id = '123456789';
$record = ApiClient::put(
    uri: '/projects/' . $project_id,
    data: [
        'description' => 'This is a cool project that we created for a demo.'
    ]
);

// Delete a project
// https://docs.gitlab.com/ee/api/projects.html#delete-project
$project_id = '123456789';
$record = ApiClient::delete(
    uri: '/projects/' . $project_id
);
```

### Issue Tracking and Bug Reports

We do not maintain a roadmap of feature requests, however we invite you to contribute and we will gladly review your merge requests.

Please create an [issue](https://github.com/boldlygrow/gitlab-api-client/issues) for bug reports.

### Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) to learn more about how to contribute.

### Maintainers

| Name | GitHub Handle | Email |
|------|---------------|-------|
| [Jeff Martin](https://www.linkedin.com/in/jeffersonmmartin/) | [@jeffersonmartin](https://github.com/jeffersonmartin) | `jeff [at] boldlygrow [dot] us` |

### Contributor Credit

- Dillon Wheeler
- Jeff Martin

## Installation

### Requirements

| Requirement | Version                     |
|-------------|-----------------------------|
| PHP         | `^8.2`, `^8.3`, `^8.4`      |
| Laravel     | `^11.0`, `^12.0`, `^13.0`   |

### Upgrade Guide

See the [changelog](https://github.com/boldlygrow/gitlab-api-client/tree/main/changelog) for release notes.

### Add Composer Package

```plain
composer require boldlygrow/gitlab-api-client:^5.0
```

If you are contributing to this package, see [CONTRIBUTING.md](CONTRIBUTING.md) for instructions on configuring a local composer package with symlinks.

### Publish the configuration file

**This is optional**. The configuration file specifies which `.env` variable names that that the API connection is stored in. You only need to publish the configuration file if you want to rename the `GITLAB_API_*` `.env` variable names.

```plain
php artisan vendor:publish --tag=gitlab-api-client
```

## Connection Credentials

### Environment Variables

Add the following variables to your `.env` file. You can add these anywhere in the file on a new line, or add to the bottom of the file (your choice).

```php
GITLAB_API_URL="https://gitlab.com"
GITLAB_API_TOKEN=""
```

If you have your connection secrets stored in your database or secrets manager, you can override the `config/gitlab-api-client.php` configuration or provide a connection array on each request. See [connection arrays](#connection-arrays) to learn more.

#### URL

If you are using GitLab.com SaaS (you don't host your own GitLab instance), then the URL is `https://gitlab.com`. If you're just getting started, it is recommended to sign up for a free account on [GitLab.com](https://gitlab.com). You can use the API with projects in your personal namespace or for open source or organization groups.

```php
GITLAB_API_URL="https://gitlab.com"
GITLAB_API_TOKEN=""
```

If you host your own GitLab self-managed instance, then the URL is the FQDN of your instance that you use to sign in (ex. `https://gitlab.example.com`).

```php
GITLAB_API_URL="https://gitlab.example.com"
GITLAB_API_TOKEN=""
```

If your GitLab instance is behind a firewall, then you will need to work with your IT or Infrastructure team to allow the Laravel application to connect to the GitLab instance. This configuration varies based on your environment and no support is provided. You can perform testing using CURL commands to `https://gitlab.example.com/api/v4/version` when establishing initial connectivity.

#### API Tokens

You need to generate an access token on your GitLab instance and update the `GITLAB_API_TOKEN` variable in your `.env` file.

```php
GITLAB_API_TOKEN="glpat-S3cr3tK3yG03sH3r3"
```

See [Security Best Practices](#security-best-practices) before creating an API token.

### Connection Arrays

The variables that you define in your `.env` file are used by default unless you set the connection argument with an array containing the URL and the API token.

> **Security Warning:** Do not commit a hard coded API token into your code base. This should only be used when using dynamic variables that are stored in your database or secrets manager.

```php
$connection = [
    'url' => 'https://gitlab.com',
    'token' => 'glpat-S3cr3tK3yG03sH3r3'
];
```

```php
use BoldlyGrow\Gitlab\ApiClient;

class MyClass
{
    private array $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getGroup($group_id)
    {
        return ApiClient::get(
            connection: $this->connection,
            uri: 'groups/' . $group_id
        )->data;
    }
}
```

### Security Best Practices

#### No Shared Tokens

Do not use an API token that you have already created for another purpose. You should generate a new API Token for each use case.

This is helpful during security incidents when a key needs to be revoked on a compromised system and you do not want other systems that use the same user or service account to be affected since they use a different key that wasn't revoked.

#### API Token Storage

Do not add your API token to any `config/*.php` files to avoid committing to your repository (secret leak).

All API tokens should be defined in the `.env` file which is included in `.gitignore` and not committed to your repository.

For advanced use cases, you can store your variables in CI/CD variables or a secrets vault (ex. Ansible Vault, AWS Parameter Store, GCP Secrets Manager, HashiCorp Vault, etc.).

#### API Token Permissions

We recommend reading more about [Personal Access Tokens](#personal-access-tokens), [Group Access Tokens](#group-access-tokens), [Project Access Tokens](#project-access-tokens), and [Security Best Practices](#security-best-practices) **before creating an API token** for your application.

All API endpoints require the `api` or `read_api` scope. If you are not performing any read-write operations, it is recommended to use the `read_api` scope as a proactive security measure.

If you are using a **personal access token**, the API token uses the permissions for the user that it belongs to, so it is a best practice to create a service account (bot) user for production application use cases. For safety reasons, most service accounts should be a `Regular` user. Be very careful if your user account has `Administrator` access.

#### GitLab Project Permissions

Each API call maps to a specific permission that is allowed for one or more roles.

You should not configure `Owner` or `Maintainer` over-permissive roles for a Group Access Token or Project Access Token unless you have API calls that specifically require this permission level.

[https://docs.gitlab.com/ee/user/permissions.html#project-members-permissions](https://docs.gitlab.com/ee/user/permissions.html#project-members-permissions)

#### Personal Access Tokens

A Personal Access Token provides access to all of the groups and projects that your user account has access to. **This is a widely permissive token and should be used carefully.** This is only recommended for use cases that perform admin-level API calls or need access to multiple groups that cannot be performed with a [Group Access Token](#group-access-tokens).

For safety reasons, most service accounts should be a `Regular` user. Be very careful if your user account has `Administrator` access.

[https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html](https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html)

#### Group Access Tokens

A Group Access Token only provides access to the specific GitLab group and any child groups and GitLab projects. **This is the recommended type of token for most use cases.**

[https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html](https://docs.gitlab.com/ee/user/group/settings/group_access_tokens.html)

#### Project Access Tokens

A Project Access Token only provides access to the specific GitLab project that it is created for and is associated with a bot user based on the name of the API key that you create. Unless you are only using this API Client for performing operations in a single project, it is recommended to use a Group Access Token or Personal Access Token.

[https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html](https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html)

## API Requests

You can make an API request to any of the resource endpoints in the [GitLab REST API Documentation](https://docs.gitlab.com/ee/api/api_resources.html).

**Just getting started?** Explore the [users](https://docs.gitlab.com/ee/api/users.html), [groups](https://docs.gitlab.com/ee/api/groups.html), [projects](https://docs.gitlab.com/ee/api/projects.html), and [issues](https://docs.gitlab.com/ee/api/issues.html) endpoints.

| Endpoint                                           | API Documentation                                                                                                                                                           |
|----------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `users`                                            | [List all users](https://docs.gitlab.com/ee/api/users.html#list-users)                                                                                                      |
| `users/{id}`                                       | [Get user by ID](https://docs.gitlab.com/ee/api/users.html#single-user)                                                                                                     |
| `users/{id}/projects`                              | [Get projects that user is a member of](https://docs.gitlab.com/ee/api/projects.html#list-user-projects)                                                                    |
| `users`                                            | [Create a user](https://docs.gitlab.com/ee/api/users.html#user-creation)                                                                                                    |
| `groups`                                           | [List of all groups](https://docs.gitlab.com/ee/api/groups.html#list-groups)                                                                                                |
| `groups/{id}/descendent_groups`                    | [List all descendent child groups for a parent group](https://docs.gitlab.com/ee/api/groups.html#list-a-groups-descendant-groups)                                           |
| `groups/{id}`                                      | [Get specific group by ID](https://docs.gitlab.com/ee/api/groups.html#details-of-a-group)                                                                                   |
| `projects/{id}/members`                            | [List all direct group members](https://docs.gitlab.com/ee/api/members.html#list-all-members-of-a-group-or-project)                                                         |
| `projects/{id}/members/all`                        | [List all direct and inherited group members](https://docs.gitlab.com/ee/api/members.html#list-all-members-of-a-group-or-project-including-inherited-and-invited-members)   |
| `projects/{id}/members`                            | [Add a member to a group](https://docs.gitlab.com/ee/api/members.html#add-a-member-to-a-group-or-project)                                                                   |
| `projects/{id}/members`                            | [Remove a member from a group](https://docs.gitlab.com/ee/api/members.html#remove-a-member-from-a-group-or-project)                                                         |
| `projects`                                         | [List all projects](https://docs.gitlab.com/ee/api/projects.html#list-all-projects)                                                                                         |
| `projects/{id}`                                    | [Get specific project by ID](https://docs.gitlab.com/ee/api/projects.html#get-single-project)                                                                               |
| `projects/{id}/members`                            | [List all direct project members](https://docs.gitlab.com/ee/api/members.html#list-all-members-of-a-group-or-project)                                                       |
| `projects/{id}/members/all`                        | [List all direct and inherited project members](https://docs.gitlab.com/ee/api/members.html#list-all-members-of-a-group-or-project-including-inherited-and-invited-members) |
| `projects/{id}/members`                            | [Add a member to a project](https://docs.gitlab.com/ee/api/members.html#add-a-member-to-a-group-or-project)                                                                 |
| `projects/{id}/members`                            | [Remove a member from a project](https://docs.gitlab.com/ee/api/members.html#remove-a-member-from-a-group-or-project)                                                       |
| `projects/{id}/issues`                             | [List project issues](https://docs.gitlab.com/ee/api/issues.html#list-project-issues)                                                                                       |
| `projects/{id}/issues/{id}`                        | [Get specific project issue](https://docs.gitlab.com/ee/api/issues.html#single-project-issue)                                                                               |
| `projects/{id}/issues`                             | [Create a new issue](https://docs.gitlab.com/ee/api/issues.html#new-issue)                                                                                                  |
| `projects/{id}/merge_requests`                     | [List project merge requests](https://docs.gitlab.com/ee/api/merge_requests.html#list-project-merge-requests)                                                               |
| `projects/{id}/repository/files/{urlencoded_path}` | [Get file metadata and contents from repository](https://docs.gitlab.com/ee/api/repository_files.html#get-file-from-repository)                                             |

### Dependency Injection

If you include the fully-qualified namespace at the top of of each class, you can use the class name inside the method where you are making an API call.

```php
use BoldlyGrow\Gitlab\ApiClient;

class MyClass
{
    public function getGroup($group_id)
    {
        return ApiClient::get('groups/' . $group_id)->data;
    }
}
```

If you do not use dependency injection, you need to provide the fully qualified namespace when using the class.

```php
class MyClass
{
    public function getGroup($group_id)
    {
        return \BoldlyGrow\Gitlab\ApiClient::get('groups/' . $group_id)->data;
    }
}
```

### Class Instantiation

We transitioned to using static methods in v4.0 and you do not need to instantiate the ApiClient class.

```php
ApiClient::get('groups');
ApiClient::post('groups', []);
ApiClient::get('groups/12345678');
ApiClient::put('groups/12345678', []);
ApiClient::delete('groups/12345678');
```

### Named vs Positional Arguments

You can use named arguments/parameters (introduced in PHP 8) or positional function arguments/parameters.

It is recommended is to use named arguments if you are specifying request data and/or are using a connection array. You can use positional arguments if you are only specifying the URI.

Learn more in the PHP documentation for [function arguments](https://www.php.net/manual/en/functions.arguments.php), [named parameters](https://php.watch/versions/8.0/named-parameters), and this helpful [blog article](https://stitcher.io/blog/php-8-named-arguments).

```php
// Named Arguments
ApiClient::get(
    uri: 'groups'
);

// Positional Arguments
ApiClient::get('groups');
```

### GET Requests

The endpoint starts with or without a leading `/` after `/api/v4/`. The GitLab API documentation provides the endpoint with a leading slash with the `/api/v4` already implied. It is up to you (only cosmetic) whether or not you want to include the leading slash for the endpoint. The API client automatically handles the string concatenation for `https://gitlab.com/api/v4/uri`.

With the API Client, you use the `get()` method with the endpoint `groups` as the `uri` argument.

```php
ApiClient::get('groups');
```

You can also use variables or database models to get data for constructing your endpoints.

```php
// Get a list of records
$records = ApiClient::get('groups');

// Use variable for endpoint
$endpoint = 'groups';
$records = ApiClient::get($endpoint);

// Get a specific record
$group_id = '12345678';
$record = ApiClient::get('groups/' . $group_id);

// Get a specific record using a variable
// This assumes that you have a database column named `api_group_id` that
// contains the string with the GitLab Group ID `12345678`.
$gitlab_group = \App\Models\GitlabGroup::where('id', $id)->firstOrFail();
$record = ApiClient::get('groups/' . $gitlab_group->api_group_id);
```

#### GET Requests with Query String Parameters

The second argument of a `get()` method is an optional array of parameters that is parsed by the API Client and the [Laravel HTTP Client](https://laravel.com/docs/8.x/http-client#get-request-query-parameters) and rendered as a query string with the `?` and `&` added automatically.

##### API Request Filtering

Some API endpoints use a `search` query string or other parameters to limit results. See the [list users](https://docs.gitlab.com/ee/api/users.html#list-users) and [list projects](https://docs.gitlab.com/ee/api/projects.html#list-all-projects) API documentation for examples. Each endpoint offers different options that can be reviewed when using each endpoint.

```php
// Search for records
// https://docs.gitlab.com/ee/api/projects.html#list-all-projects
$records = ApiClient::get('projects', [
    'search' => 'my-project-name',
    'membership' => true
]);

// This will parse the array and render the query string
// https://gitlab.com/api/v4/projects?search=my-project-name&membership=true
```

##### API Response Filtering

You can also use [Laravel Collections](https://laravel.com/docs/10.x/collections#available-methods) to filter and transform results, either using a full data set or one that you already filtered with your API request.

See [Using Laravel Collections](#using-laravel-collections) to learn more.

### POST Requests

The `post()` method works almost identically to a `get()` request with an array of parameters, however the parameters are passed as form data using the `application/json` content type rather than in the URL as a query string. This is industry standard and not specific to the API Client.

You can learn more about request data in the [Laravel HTTP Client documentation](https://laravel.com/docs/10.x/http-client#request-data).

```php
// Create a project
// https://docs.gitlab.com/ee/api/projects.html#create-project
$group_id = '12345678';
$record = ApiClient::post(
    uri: '/projects',
    data: [
        'name' => 'My Cool Project',
        'path' => 'my-cool-project',
        'namespace_id' => $group_id
    ]
);
```

### PUT Requests

The `put()` method is used for updating the attributes for an existing record.

You need to ensure that the ID of the record that you want to update is provided in the first argument (URI). In most applications, this will be a variable that you get from your database or another location and won't be hard-coded.

```php
// Update a project
// https://docs.gitlab.com/ee/api/projects.html#edit-project
$project_id = '123456789';
$record = ApiClient::put(
    uri: '/projects/' . $project_id,
    data: [
        'description' => 'This is a cool project that we created for a demo.'
    ]
);
```

### DELETE Requests

The `delete()` method is used for methods that will destroy the resource based on the ID that you provide.

Keep in mind that `delete()` methods will return different status codes depending on the vendor (ex. 200, 201, 202, 204, etc). GitLab's API will return a `204` status code for successfully deleted resources and a `202` status code for resources scheduled for deletion. You should use the `$response->status->successful` boolean for checking results.

```php
// Delete a project
// https://docs.gitlab.com/ee/api/projects.html#delete-project
$project_id = '123456789';
$record = ApiClient::delete('projects/' . $project_id);
```

### Class Methods

The examples above show basic inline usage that is suitable for most use cases. If you prefer to use classes and constructors, the example below will provide a helpful example.

```php
<?php

use BoldlyGrow\Gitlab\ApiClient;
use BoldlyGrow\Gitlab\Exceptions\NotFoundException;

class GitlabProjectService
{
    private $connection;

    public function __construct(array $connection = [])
    {
        // If connection is null, use the environment variables
        $this->connection = !empty($connection) ? $connection : config('gitlab-api-client');
    }

    public function listProjects($query = [])
    {
        $projects = ApiClient::get(
            connection: $this->connection,
            uri: 'projects',
            data: $query
        );

        return $projects->data;
    }

    public function getProject($id, $query = [])
    {
        try {
            $project = ApiClient::get(
                connection: $this->connection,
                uri: 'projects/' . $id,
                data: $query
            );
        } catch (NotFoundException $e) {
            // Custom logic to handle a record not found. For example, you could
            // redirect to a page and flash an alert message.
        }

        return $project->data;
    }

    public function storeProject($request_data)
    {
        $project = ApiClient::post(
            connection: $this->connection,
            uri: 'projects',
            data: $request_data
        );

        // To return an object with the newly created project
        return $project->data;

        // To return the ID of the newly created project
        // return $project->data->id;

        // To return the status code of the form request
        // return $project->status->code;

        // To return a bool with the status of the form request
        // return $project->status->successful;

        // To return the entire API response with the data, headers, and status
        // return $project;
    }

    public function updateProject($id, $request_data)
    {
        try {
            $project = ApiClient::put(
                connection: $this->connection,
                uri: 'projects/' . $id,
                data: $request_data
            );
        } catch (NotFoundException $e) {
            // Custom logic to handle a record not found. For example, you could
            // redirect to a page and flash an alert message.
        }

        // To return an object with the updated created project
        return $project->data;

        // To return a bool with the status of the form request
        // return $project->status->successful;
    }

    public function deleteProject($id)
    {
        try {
            $project = ApiClient::delete(
                connection: $this->connection,
                uri: 'projects/' . $id
            );
        } catch (NotFoundException $e) {
            // Custom logic to handle a record not found. For example, you could
            // redirect to a page and flash an alert message.
        }

        return $project->status->successful;
    }
}
```

### Rate Limits

In v4.0, we added automatic backoff when 20% of rate limit is remaining. This slows down the requests by implementing a `sleep(10)` with each request. Since the rate limit resets at 60 seconds, this will slow the next 5-6 requests until the rate limit resets.

If the GitLab rate limit is exceeded for an endpoint, a `BoldlyGrow\Gitlab\Exceptions\RateLimitException` will be thrown.

The backoff will slow the requests, however if the rate limit is exceeded, the request will fail and terminate.

## API Responses

This API Client uses the BoldlyGrow standards for API response formatting.

```php
// API Request
$group = ApiClient::get('groups/80039310');

// API Response
$group->data; // object
$group->headers; // array
$group->status; // object
$group->status->code; // int (ex. 200)
$group->status->ok; // bool (is 200 status)
$group->status->successful; // bool (is 2xx status)
$group->status->failed; // bool (is 4xx/5xx status)
$group->status->clientError; // bool (is 4xx status)
$group->status->serverError; // bool (is 5xx status)
```

### Response Data

The `data` property contains the contents of the Laravel HTTP Client `object()` method that has been parsed and has the final merged output of any paginated results.

```php
$group = ApiClient::get('groups/80039310');
$group->data;
```

```json
{
    +"id": 80039310,
    +"web_url": "https://gitlab.com/groups/provisionesta",
    +"name": "provisionesta",
    +"path": "provisionesta",
    +"description": "Provisionesta is a library of open source packages, projects, and tools created by Jeff Martin mostly related to IAM/RBAC and REST API infrastructure and SaaS application provisioning.",
    +"visibility": "public",
    +"share_with_group_lock": false,
    +"require_two_factor_authentication": false,
    +"two_factor_grace_period": 48,
    +"project_creation_level": "developer",
    +"auto_devops_enabled": null,
    +"subgroup_creation_level": "maintainer",
    +"emails_disabled": false,
    +"emails_enabled": true,
    +"mentions_disabled": null,
    +"lfs_enabled": true,
    +"default_branch_protection": 2,
    +"default_branch_protection_defaults": {},
    +"avatar_url": "https://gitlab.com/uploads/-/system/group/avatar/80039310/121-automate.png",
    +"request_access_enabled": true,
    +"full_name": "provisionesta",
    +"full_path": "provisionesta",
    +"created_at": "2023-12-24T19:28:45.322Z",
    +"parent_id": null,
    +"organization_id": 1,
    +"shared_runners_setting": "enabled",
    +"ldap_cn": null,
    +"ldap_access": null,
    +"marked_for_deletion_on": null,
    +"wiki_access_level": "enabled",
    +"shared_with_groups": [],
    +"runners_token": "REDACTED",
    +"prevent_sharing_groups_outside_hierarchy": false,
    +"projects": [],
    +"shared_projects": [],
    +"shared_runners_minutes_limit": 50000,
    +"extra_shared_runners_minutes_limit": null,
    +"prevent_forking_outside_group": false,
    +"service_access_tokens_expiration_enforced": true,
    +"membership_lock": false,
    +"ip_restriction_ranges": null,
    +"unique_project_download_limit": 0,
    +"unique_project_download_limit_interval_in_seconds": 0,
    +"unique_project_download_limit_allowlist": [],
    +"unique_project_download_limit_alertlist": [
      4572001,
    ],
    +"auto_ban_user_on_excessive_projects_download": false,
}
```

#### Access a single record value

You can access these variables using object notation. This is the most common use case for handling API responses.

```php
$group = ApiClient::get('groups/80039310')->data;

$group_name = $group->path;
// provisionesta
```

#### Looping through records

If you have an array of multiple objects, you can loop through the records. The API Client automatically paginates and merges the array of records for improved developer experience.

```php
$groups = ApiClient::get('groups')->data;

foreach($groups as $group) {
    dd($group->path);
    // provisionesta
}
```

#### Caching responses

The API Client does not use caching to avoid any constraints with you being able to control which endpoints you cache.

You can wrap an endpoint in a cache facade when making an API call. You can learn more in the [Laravel Cache](https://laravel.com/docs/10.x/cache) documentation.

```php
use Illuminate\Support\Facades\Cache;
use BoldlyGrow\Gitlab\ApiClient;

$groups = Cache::remember('gitlab_groups', now()->addHours(12), function () {
    return ApiClient::get('groups')->data;
});

foreach($groups as $group) {
    dd($group->path);
    // provisionesta
}
```

When getting a specific ID or passing additional arguments, be sure to pass variables into `use($var1, $var2)`.

```php
$group_id = '12345678';

$groups = Cache::remember('gitlab_group_' . $group_id, now()->addHours(12), function () use ($group_id) {
    return ApiClient::get('groups/' . $group_id)->data;
});
```

#### Date Formatting

You can use the [Carbon](https://carbon.nesbot.com/docs/) library for formatting dates and performing calculations.

```php
$created_date = Carbon::parse($group->data->created_at)->format('Y-m-d');
// 2023-01-01
```

```php
$created_age_days = Carbon::parse($group->data->created_at)->diffInDays();
// 265
```

#### Using Laravel Collections

You can use [Laravel Collections](https://laravel.com/docs/10.x/collections#available-methods) which are powerful array helper tools that are similar to array searching and SQL queries that you may already be familiar with.

See the [Parsing Responses with Laravel Collections](#parsing-responses-with-laravel-collections) documentation to learn more.

### Response Headers

> The headers are returned as an array instead of an object since the keys use hyphens that conflict with the syntax of accessing keys and values easily.

```php
$group = ApiClient::get('groups/12345678');
$group->headers;
```

```php
[
    +"Date": "Thu, 06 Jan 2022 21:40:18 GMT",
    +"Content-Type": "application/json",
    +"Transfer-Encoding": "chunked",
    +"Connection": "keep-alive",
    +"Cache-Control": "max-age=0, private, must-revalidate",
    +"Etag": "W/"ed65096017d349b25371385b9b96d102"",
    +"Vary": "Origin",
    +"X-Content-Type-Options": "nosniff",
    +"X-Frame-Options": "SAMEORIGIN",
    +"X-Request-Id": "01FRRNBQCPG9XKBY8211NEH285",
    +"X-Runtime": "0.100822",
    +"Strict-Transport-Security": "max-age=31536000",
    +"Referrer-Policy": "strict-origin-when-cross-origin",
    +"RateLimit-Observed": "6",
    +"RateLimit-Remaining": "1994",
    +"RateLimit-Reset": "1641505278",
    +"RateLimit-ResetTime": "Thu, 06 Jan 2022 21:41:18 GMT",
    +"RateLimit-Limit": "2000",
    +"GitLab-LB": "fe-12-lb-gprd",
    +"GitLab-SV": "localhost",
    +"CF-Cache-Status": "DYNAMIC",
    +"Expect-CT": "max-age=604800, report-uri="https://report-uri.cloudflare.com/cdn-cgi/beacon/expect-ct"",
    +"Report-To": "{"endpoints":[{"url":"https:\/\/a.nel.cloudflare.com\/report\/v3?s=LWJRP1mJdxCzclW3zKzqg40CbYJeUJ2mf2aRLBRfzxWvAgh15LrCQwpmqtk%2B4cJoDWsX3bx1yAkEB9HuokEMgKg%2FMkFXLoy2N8oE09KfHIH%2B8YWjBmX%2BdUD4hkg%3D"}],"group":"cf-nel","max_age":604800}",
    +"NEL": "{"success_fraction":0.01,"report_to":"cf-nel","max_age":604800}",
    +"Server": "cloudflare",
    +"CF-RAY": "6c981a9ba95639a7-SEA",
]
```

#### Getting a Header Value

```php
$content_type = $group->headers['Content-Type'];
// application/json
```

### Response Status

See the [Laravel HTTP Client documentation](https://laravel.com/docs/10.x/http-client#error-handling) to learn more about the different status booleans.

```php
$group = ApiClient::get('groups/12345678');
$group->status;
```

```php
{
  +"code": 200 // int (ex. 200)
  +"ok": true // bool (is 200 status)
  +"successful": true // bool (is 2xx status)
  +"failed": false // bool (is 4xx/5xx status)
  +"serverError": false // bool (is 4xx status)
  +"clientError": false // bool (is 5xx status)
}
```

#### API Response Status Code

```php
$group = ApiClient::get('groups/12345678');

$status_code = $group->status->code;
// 200
```

## Error Responses

An exception is thrown for any 4xx or 5xx responses. All responses are automatically logged.

### Exceptions

| Code | Exception Class                                               |
|------|---------------------------------------------------------------|
| N/A  | `BoldlyGrow\Gitlab\Exceptions\ConfigurationException`      |
| 400  | `BoldlyGrow\Gitlab\Exceptions\BadRequestException`         |
| 401  | `BoldlyGrow\Gitlab\Exceptions\UnauthorizedException`       |
| 403  | `BoldlyGrow\Gitlab\Exceptions\ForbiddenException`          |
| 404  | `BoldlyGrow\Gitlab\Exceptions\NotFoundException`           |
| 409  | `BoldlyGrow\Gitlab\Exceptions\ConflictException`           |
| 412  | `BoldlyGrow\Gitlab\Exceptions\PreconditionFailedException` |
| 422  | `BoldlyGrow\Gitlab\Exceptions\UnprocessableException`      |
| 429  | `BoldlyGrow\Gitlab\Exceptions\RateLimitException`          |
| 500  | `BoldlyGrow\Gitlab\Exceptions\ServerErrorException`        |
| 503  | `BoldlyGrow\Gitlab\Exceptions\ServiceUnavailableException` |

### Catching Exceptions

You can catch any exceptions that you want to handle silently. Any uncaught exceptions will appear for users and cause 500 errors that will appear in your monitoring software.

```php
use BoldlyGrow\Gitlab\Exceptions\NotFoundException;

try {
    $group = ApiClient::get('groups/12345678');
} catch (NotFoundException $e) {
    // Group is not found. You can create a log entry, throw an exception, or handle it another way.
    Log::error('GitLab group could not be found', ['gitlab_group_id' => $group_id]);
}
```

### Disabling Exceptions

If you do not want exceptions to be thrown, you can globally disable exceptions for the GitLab API Client and handle the status for each request yourself. Simply set the `GITLAB_API_EXCEPTIONS=false` in your `.env` file.

```php
GITLAB_API_EXCEPTIONS=false
```

## Parsing Responses with Laravel Collections

You can use [Laravel Collections](https://laravel.com/docs/10.x/collections#available-methods) which are powerful array helper tools that are similar to array searching and SQL queries that you may already be familiar with.

```php
$project_id = '12345678';

$issues = ApiClient::get('projects/' . $project_id . '/issues');

$issue_collection = collect($issues->data)->where('state', 'closed')->toArray();

// This will return an array of all issues that have been closed
```

For syntax conventions and readability, you can easily collapse this into a single line. Since the ApiClient automatically handles any 4xx or 5xx error handling, you do not need to worry about try/catch exceptions.

```php
$users = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->toArray();
```

This approach allows you to have the same benefits as if you were doing a SQL query and will feel familiar as you start using collections.

```sql
SELECT * FROM issues WHERE project_id='12345678' AND state='closed';
```

### Collection Methods

The most common methods that are useful for filtering data are:

| Laravel Docs                                                              | Usage Example                         |
|---------------------------------------------------------------------------|---------------------------------------|
| [count](https://laravel.com/docs/10.x/collections#method-count)           | [Usage Example](#count-methods)       |
| [countBy](https://laravel.com/docs/10.x/collections#method-countBy)       | [Usage Example](#count-methods)       |
| [except](https://laravel.com/docs/10.x/collections#method-except)         | N/A                                   |
| [filter](https://laravel.com/docs/10.x/collections#method-filter)         | N/A                                   |
| [flip](https://laravel.com/docs/10.x/collections#method-flip)             | N/A                                   |
| [groupBy](https://laravel.com/docs/10.x/collections#method-groupBy)       | [Usage Example](#group-method)        |
| [keyBy](https://laravel.com/docs/10.x/collections#method-keyBy)           | N/A                                   |
| [only](https://laravel.com/docs/10.x/collections#method-only)             | N/A                                   |
| [pluck](https://laravel.com/docs/10.x/collections#method-pluck)           | [Usage Example](#pluck-method)        |
| [sort](https://laravel.com/docs/10.x/collections#method-sort)             | [Usage Example](#sort-methods)        |
| [sortBy](https://laravel.com/docs/10.x/collections#method-sortBy)         | [Usage Example](#sort-methods)        |
| [sortKeys](https://laravel.com/docs/10.x/collections#method-sortKeys)     | [Usage Example](#sort-methods)        |
| [toArray](https://laravel.com/docs/10.x/collections#method-toArray)       | N/A                                   |
| [transform](https://laravel.com/docs/9.x/collections#method-transform)    | [Usage Example](#transforming-arrays) |
| [unique](https://laravel.com/docs/10.x/collections#method-unique)         | [Usage Example](#unique-method)       |
| [values](https://laravel.com/docs/10.x/collections#method-values)         | [Usage Example](#values-method)       |
| [where](https://laravel.com/docs/10.x/collections#method-where)           | N/A                                   |
| [whereIn](https://laravel.com/docs/10.x/collections#method-whereIn)       | N/A                                   |
| [whereNotIn](https://laravel.com/docs/10.x/collections#method-whereNotIn) | N/A                                   |

### Collection Simplified Arrays

#### Pluck Method

You can use collections to get a specific attribute using the [pluck](https://laravel.com/docs/10.x/collections#method-pluck) method.

```php
// Get an array of issue titles
$issue_titles = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('title')
    ->toArray();

// [
//     0 => 'Lorem ipsum dolor sit amet',
//     1 => 'Donec malesuada leo et efficitur imperdiet',
//     2 => 'Aliquam dignissim tortor faucibus',
//     3 => 'Sed convallis velit id massa',
//     4 => 'Vivamus congue quam eget nisl pharetra',
//     5 => 'Suspendisse finibus odio vitae',
// ]
```

You can also use the [pluck](https://laravel.com/docs/10.x/collections#method-pluck) method to get two attributes and set one as the array key and the other as the array value.

```php
// Get an array with title keys and author array values
$issue_titles_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('author', 'title')
    ->toArray();

// [
//     'Lorem ipsum dolor sit amet' => {
//         +"id": 123456,
//         +"username": "z3r0c00l.example",
//         +"name": "Dade Murphy",
//         +"state": "active",
//         +"locked": false,
//         +"web_url": "https://gitlab.com/z3r0c00l.example",
//     },
//     'Donec malesuada leo et efficitur imperdiet' => {
//         // truncated for docs
//      },
//     'Aliquam dignissim tortor faucibus' => {
//         // truncated for docs
//      },
//     'Sed convallis velit id massa' => {
//         // truncated for docs
//      },
//     'Vivamus congue quam eget nisl pharetra' => {
//         // truncated for docs
//      },
//     'Suspendisse finibus odio vitae' => {
//         // truncated for docs
//      },
// ]
```

#### Using Dot Notation for Nested Array Attributes

If you only want to return a string, you can use dot notation when using the pluck method. You can also use dot notation with most other collection methods including [where](https://laravel.com/docs/10.x/collections#method-where) and [groupBy](https://laravel.com/docs/10.x/collections#method-groupBy) methods.

```php
$issue_titles_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('author.name', 'title')
    ->toArray();

// [
//     'Lorem ipsum dolor sit amet' => "Dade Murphy",
//     'Donec malesuada leo et efficitur imperdiet' => "Kate Libby",
//     'Aliquam dignissim tortor faucibus' => "Kate Libby",
//     'Sed convallis velit id massa' => "Dade Murphy",
//     'Vivamus congue quam eget nisl pharetra' => "Paul Cook",
//     'Suspendisse finibus odio vitae' => "Joey Pardella",
// ]
```

#### Transforming Arrays

When working with a record returned from the API, you will have a lot of data that you don't need for the current use case.

You can use the [transform](https://laravel.com/docs/10.x/collections#method-transform) method to perform a foreach loop over each record and create a new array with the specific fields that you want.

You can think of the `$item` variable as `foreach($users as $item) { }` that has all of the metadata for a specific record.

The transform method uses a function (a.k.a. closure) to return an array that should become the new value for this specific array key.

```php
$issue_titles_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('author', 'title')
    ->transform(function($item) {
        return [
            'id' => $item->id,
            'name' => $item->name
        ];
    })->toArray();

// [
//     'Lorem ipsum dolor sit amet' => {
//         +"id": 123456,
//         +"name": "Dade Murphy",
//     },
//     'Donec malesuada leo et efficitur imperdiet' => {
//         // truncated for docs
//      },
//     'Aliquam dignissim tortor faucibus' => {
//         // truncated for docs
//      },
//     'Sed convallis velit id massa' => {
//         // truncated for docs
//      },
//     'Vivamus congue quam eget nisl pharetra' => {
//         // truncated for docs
//      },
//     'Suspendisse finibus odio vitae' => {
//         // truncated for docs
//      },
// ]
```

##### Arrow Functions

If all of your transformations can be done in-line in the array and don't require defining additional variables, you can use the shorthand arrow functions. This is a personal preference and not a requirement.

```php
$users = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('author', 'title')
    ->transform(fn($item) => [
        'id' => $item->id,
        'name' => $item->name
    ])->toArray();
```

#### Calculated Values

If you want to return an array or string that you have calculated or performed an additional calculation with, you can perform them inside the [transform](https://laravel.com/docs/10.x/collections#method-transform) method just like you would with a normal function with inputs and outputs.

```php
$issue_titles_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->pluck('author', 'title')
    ->transform(function($item) {
        // Disclaimer: Performing individual API calls on large data sets
        // may exhaust rate limits and is computationally intensive.
        $user = ApiClient::get('users/' . $item->id)->data;
        return $user->email;
    })->toArray();

// [
//     'Lorem ipsum dolor sit amet' => "dmurphy@example.com",
//     'Donec malesuada leo et efficitur imperdiet' => "klibby@example.com",
//     'Aliquam dignissim tortor faucibus' => "klibby@example.com",
//     'Sed convallis velit id massa' => "dmurphy@example.com",
//     'Vivamus congue quam eget nisl pharetra' => "pcook@example.com",
//     'Suspendisse finibus odio vitae' => "jpardella@example.com",
// ]
```

#### Unique Method

You can use the [unique](https://laravel.com/docs/10.x/collections#method-unique) method to get a list of unique attribute values (ex. author names).

```php
$unique_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->unique('author.name')
    ->pluck('author.name')
    ->toArray();

// [
//     36 => 'Dade Murphy',
//     111 => 'Kate Libby',
//     238 => 'Paul Cook',
//     288 => 'Joey Pardella'
// ]
```

#### Values Method

When using the `unique` method, it is using the key of the first record that it found. You should add [values](https://laravel.com/docs/10.x/collections#method-values) method near the end to reset all of the key integers based on the number of results that you have.

```php
$unique_authors = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->unique('author.name')
    ->pluck('author.name')
    ->values()
    ->toArray();

// [
//     0 => 'Dade Murphy',
//     1 => 'Kate Libby',
//     2 => 'Paul Cook',
//     3 => 'Joey Pardella'
// ]
```

#### Sort Methods

You can alphabetically sort by an attribute value. Simply provide the attribute to [sortBy](https://laravel.com/docs/10.x/collections#method-sortBy) method (nested array values are supported). If you have already used the pluck method and the array value is a string, you can use [sort](https://laravel.com/docs/10.x/collections#method-sort) which doesn't accept an argument.

```php
// Option 1
$unique_job_titles = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->sortBy('author.name')
    ->unique('author.name')
    ->pluck('author.name')
    ->values()
    ->toArray();

// Option 2
$unique_job_titles = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->unique('author.name')
    ->pluck('author.name')
    ->sort()
    ->values()
    ->toArray();

// [
//     0 => 'Dade Murphy',
//     1 => 'Joey Pardella'
//     2 => 'Kate Libby',
//     3 => 'Paul Cook',
// ]
```

If you have array key strings, you can use the [sortKeys](https://laravel.com/docs/10.x/collections#method-sortKeys) method to sort the resulting array keys alphabetically.

#### Count Methods

You can use the [count](https://laravel.com/docs/10.x/collections#method-count) method to get a count of the total number of results after all methods have been applied. This is used as an alternative to [toArray](https://laravel.com/docs/10.x/collections#method-toArray) so you get an integer value instead of needing to do a `count($collection_array)`.

```php
// Get a count of issues
$unique_job_titles = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->count();

// 376
```

You can use the [countBy](https://laravel.com/docs/10.x/collections#method-countBy) method to get a count of unique attribute values. You should use the [sortKeys](https://laravel.com/docs/10.x/collections#method-sortKeys) method to sort the resulting array keys alphabetically.

```php
// Get a count of unique job titles
$unique_job_titles = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->countBy('author.name')
    ->sortKeys()
    ->toArray();

// [
//     'Dade Murphy' => 2,
//     'Joey Pardella' => 1,
//     'Kate Libby' => 2,
//     'Paul Cook' => 1
// ]
```

#### Group Method

Although you can use a [groupBy](https://laravel.com/docs/10.x/collections#method-groupBy) method with a raw response, it is very difficult to manipulate the data once it's grouped, so it is recommended to transform your data and then add the `groupBy('attribute_name')` to the end of your collection chain. Keep in mind that you renamed your array value keys (attributes) when you transformed the data so you want to use the new array key.

```php
$users = collect(ApiClient::get('projects/' . $project_id . '/issues')->data)
    ->where('state', 'closed')
    ->transform(fn($item) => [
        'id' => $item->id,
        'title' => $item->title,
        'author_name' => $item->author->name,
    ])->sortBy('author_name')
    ->groupBy('author_name')
    ->toArray();

// "Dade Murphy" => [
//     [
//         "id" => "36",
//         "title" => "Lorem ipsum dolor sit amet",
//         "author_name" => "Dade Murphy",
//     ],
//     [
//         "id" => "368",
//         "title" => "Sed convallis velit id massa",
//         "author_name" => "Dade Murphy",
//     ]
// ],
// "Joey Pardella" => [
//     [
//         "id" => "288",
//         "title" => "Suspendisse finibus odio vitae",
//         "author_name" => "Joey Pardella",
//     ],
// ],
// "Kate Libby" => [
//     [
//         "id" => "111",
//         "title" => "Donec malesuada leo et efficitur imperdiet",
//         "author_name" => "Kate Libby",
//     ],
//     [
//         "id" => "219",
//         "title" => "Aliquam dignissim tortor faucibus",
//         "author_name" => "Kate Libby",
//     ]
// ],
// "Paul Cook" => [
//     [
//         "id" => "238",
//         "title" => "Vivamus congue quam eget nisl pharetra",
//         "author_name" => "Paul Cook",
//     ],
// ]
```

### Additional Reading

See the [Laravel Collections](https://laravel.com/docs/10.x/collections) documentation for additional usage.

## Log Examples

This package uses the [boldlygrow/audit-log](https://github.com/boldlygrow/audit-log) package for standardized logs.

### Request Data Log Configuration

To improve the usefulness of logs, the `data` key/value pairs sent with POST and PUT requests are logged. You can choose to disable (exclude) the `request_data` from the logs for specific methods in your `.env` file.

```bash
GITLAB_API_LOG_REQUEST_DATA_GET_ENABLED=true
GITLAB_API_LOG_REQUEST_DATA_POST_ENABLED=false # default is true
GITLAB_API_LOG_REQUEST_DATA_PUT_ENABLED=false # default is true
GITLAB_API_LOG_REQUEST_DATA_DELETE_ENABLED=true
```

If you want to exclude specific keys, they can be set in the `config/gitlab-api-client.php` file after you [publish the configuration file](#publish-the-configuration-file).

By default, the `key` and `password` fields are excluded from `GET` requests and `content` is excluded from `POST` and `PUT` requests (ex. base64 encoded content for repository files).

### Event Types

The `event_type` key should be used for any categorization and log searches.

- **Format:** `gitlab.api.{method}.{result/log_level}.{reason?}`
- **Methods:** `get|post|patch|put|delete`

| Status Code | Event Type                                        | Log Level |
|-------------|---------------------------------------------------|-----------|
| N/A         | `gitlab.api.test.success`                         | DEBUG     |
| N/A         | `gitlab.api.test.error`                           | CRITICAL  |
| N/A         | `gitlab.api.validate.error`                       | CRITICAL  |
| N/A         | `gitlab.api.get.process.pagination.started`       | DEBUG     |
| N/A         | `gitlab.api.get.process.pagination.finished`      | DEBUG     |
| N/A         | `gitlab.api.rate-limit.approaching`               | CRITICAL  |
| N/A         | `gitlab.api.rate-limit.exceeded` (Pre-Exception)  | CRITICAL  |
| N/A         | `gitlab.api.{method}.error.http.exception`        | ERROR     |
| 200         | `gitlab.api.{method}.success`                     | DEBUG     |
| 201         | `gitlab.api.{method}.success`                     | DEBUG     |
| 202         | `gitlab.api.{method}.success`                     | DEBUG     |
| 204         | `gitlab.api.{method}.success`                     | DEBUG     |
| 400         | `gitlab.api.{method}.warning.bad-request`         | WARNING   |
| 401         | `gitlab.api.{method}.error.unauthorized`          | ERROR     |
| 403         | `gitlab.api.{method}.error.forbidden`             | ERROR     |
| 404         | `gitlab.api.{method}.warning.not-found`           | WARNING   |
| 405         | `gitlab.api.{method}.error.method-not-allowed`    | ERROR     |
| 412         | `gitlab.api.{method}.error.precondition-failed`   | DEBUG     |
| 422         | `gitlab.api.{method}.error.unprocessable`         | DEBUG     |
| 429         | `gitlab.api.{method}.critical.rate-limit`         | CRITICAL  |
| 500         | `gitlab.api.{method}.critical.server-error`       | CRITICAL  |
| 501         | `gitlab.api.{method}.error.not-implemented`       | ERROR     |
| 503         | `gitlab.api.{method}.critical.server-unavailable` | CRITICAL  |

### Test Connection

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Success {"event_type":"gitlab.api.get.success","method":"BoldlyGrow\\Gitlab\\ApiClient::get","event_ms":493,"metadata":{"url":"https://gitlab.example.com/api/v4/version"}}
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::testConnection Success {"event_type":"gitlab.api.test.success","method":"BoldlyGrow\\Gitlab\\ApiClient::testConnection"}
```

### Successful Requests

#### GET Request Log

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Success {"event_type":"gitlab.api.get.success","method":"BoldlyGrow\\Gitlab\\ApiClient::get","event_ms":885,"metadata":{"url":"https://gitlab.example.com/api/v4/groups/25"}}
```

#### GET Paginated Request Log

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Success {"event_type":"gitlab.api.get.success","method":"BoldlyGrow\\Gitlab\\ApiClient::get","count_records":100,"event_ms":986,"event_ms_per_record":9,"metadata":{"rate_limit_remaining":null,"url":"https://gitlab.example.com/api/v4/groups"}}
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Paginated Results Process Started {"event_type":"gitlab.api.get.process.pagination.started","method":"BoldlyGrow\\Gitlab\\ApiClient::get","metadata":{"uri":"groups"}}
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::getPaginatedResults Success {"event_type":"gitlab.api.getPaginatedResults.success","method":"BoldlyGrow\\Gitlab\\ApiClient::getPaginatedResults","count_records":100,"event_ms":904,"event_ms_per_record":9,"metadata":{"rate_limit_remaining":null,"url":"https://gitlab.example.com/api/v4/groups?order_by=name&owned=false&page=2&per_page=100&sort=asc&statistics=false&with_custom_attributes=false"}}
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::getPaginatedResults Success {"event_type":"gitlab.api.getPaginatedResults.success","method":"BoldlyGrow\\Gitlab\\ApiClient::getPaginatedResults","count_records":20,"event_ms":391,"event_ms_per_record":19,"metadata":{"rate_limit_remaining":null,"url":"https://gitlab.example.com/api/v4/groups?order_by=name&owned=false&page=3&per_page=100&sort=asc&statistics=false&with_custom_attributes=false"}}
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Paginated Results Process Complete {"event_type":"gitlab.api.get.process.pagination.finished","method":"BoldlyGrow\\Gitlab\\ApiClient::get","duration_ms":2287,"metadata":{"uri":"groups"}}
```

#### GET Request with URL Encoded Path

```plain
cool-group/my-cool-project

[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::get Success {"event_type":"gitlab.api.get.success","method":"BoldlyGrow\\Gitlab\\ApiClient::get","event_ms":1160,"metadata":{"url":"https://gitlab.example.com/api/v4/projects/cool%2Dgroup%2Fmy%2Dcool%2Dproject"}}
```

#### POST Request Log

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::post Success {"event_type":"gitlab.api.post.success","method":"BoldlyGrow\\Gitlab\\ApiClient::post","event_ms":1552,"metadata":{"url":"https://gitlab.example.com/api/v4/projects","request_data":{"name":"My Cool Project3","path":"my-cool-project3","namespace_id":"123"}}}
```

#### PUT Success Log

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::put Success {"event_type":"gitlab.api.put.success","method":"BoldlyGrow\\Gitlab\\ApiClient::put","event_ms":423,"metadata":{"url":"https://gitlab.example.com/api/v4/projects/12345","request_data":{"description":"cool project description2"}}}
```

#### DELETE Success Log

> A scheduled deletion will return a 202 status code instead of a 204 status code.

```plain
[YYYY-MM-DD HH:II:SS] local.DEBUG: ApiClient::delete Success {"event_type":"gitlab.api.delete.success","method":"BoldlyGrow\\Gitlab\\ApiClient::delete","errors":{"message":"202 Accepted"},"event_ms":373,"metadata":{"url":"https://gitlab.example.com/api/v4/projects/12345"}}
```

### Errors

#### 401 Unauthorized

##### Environment Variables Not Set

```plain
[YYYY-MM-DD HH:II:SS] local.CRITICAL: ApiClient::validateConnection Error {"event_type":"gitlab.api.validate.error","method":"BoldlyGrow\\Gitlab\\ApiClient::validateConnection","errors":["The url field is required.","The token field is required."]}

```

```plain
BoldlyGrow\Gitlab\Exceptions\ConfigurationException

Gitlab API configuration validation error. This occurred in BoldlyGrow\Gitlab\ApiClient::validateConnection. (Solution) The url field is required. The token field is required.
```

##### Invalid Token

```plain
[YYYY-MM-DD HH:II:SS] local.ERROR: ApiClient::get Client Error {"event_type":"gitlab.api.get.error.unauthorized","method":"BoldlyGrow\\Gitlab\\ApiClient::get","errors":{"message":"401 Unauthorized"},"event_ms":225,"metadata":{"url":"https://gitlab.com/api/v4/projects/12345678","rate_limit_remaining":"1999"}}

```

```plain
BoldlyGrow\Gitlab\Exceptions\UnauthorizedException

The `GITLAB_API_TOKEN` has been configured but is invalid. (Reason) This usually happens if it does not exist, expired, or does not have permissions. (Solution) Please generate a new API Token and update the variable in your `.env` file.
```

#### 404 Not Found

```plain
[YYYY-MM-DD HH:II:SS] local.WARNING: ApiClient::get Client Error {"event_type":"gitlab.api.get.warning.not-found","method":"BoldlyGrow\\Gitlab\\ApiClient::get","errors":{"message":"404 Project Not Found"},"event_ms":253,"metadata":{"url":"https://gitlab.com/api/v4/projects/12345678"}}
```
