# ILIAS Webservices: REST API

*Note: This is a living document that will be updated as the project progresses through each phase.*

This document outlines the **ApiGateway component**, a foundational service for creating modern, modular REST APIs within ILIAS. It replaces the previous webservice infrastructure with a flexible system built on the [Slim Framework](https://www.slimframework.com/), designed to be the standard for future API development. This guide covers both usage for API consumers and the internal architecture for maintainers.

## Table of Contents

- [Getting Started](#getting-started)
- [Authentication](#authentication)
- [Configuration](#configuration)
- [Creating New API Endpoints](#creating-new-api-endpoints)
    - [Developing Gateway-Compliant Activities](docs/activities.md)
- [Standard Response Format](#standard-response-format)
- [Middlewares](#middlewares)
- [Logging](#logging)
- [Validation](#validation)
- [OpenAPI Specification](#openapi-specification)
- [Maintainer Guidelines](docs/maintenance.md)
- [Testing during development](docs/development.md)
- [Future Work](ROADMAP.md)

### Getting Started

Before you can use the API, you need to set it up and ensure it's running correctly.

#### Installation and Updates

- **Fresh Installation:** If you are installing ILIAS for the first time with this component, no special action is needed. The setup process will handle the component automatically.
- **Updating an Existing ILIAS Instance:** If you are adding this component to an existing ILIAS installation, you must run the update command to apply the necessary database changes:

    ```bash
    php cli/setup.php update --yes
    ```

- **After a Failed Update:** If a previous update failed, you may need to reset the failed steps before running the update command again:

    ```bash
    php cli/setup.php achieve database.resetFailedSteps --yes
    php cli/setup.php update --yes
    ```

1. **Install Dependencies:** From the ILIAS root directory, run `composer install`. This will download the necessary libraries (like the Slim Framework) for the API Gateway.

2. **Enable the API:**
    To enable the REST API, you must navigate to the ILIAS administration dashboard:
    `Administration > System settings and maintenance > Webservices`
    Under the **REST Settings** tab, check the **Active** checkbox and save the changes.

    > **Recommendation:** For security reasons, it is strongly recommended to configure a strong and unique secret key before activating the service. Avoid using empty or weak secrets.

    *Note: For development purposes, setting `DEVMODE` to "1" in your `client.ini.php` will enable detailed error logging, but it does **not** activate the webservice itself.*

3. **Test the Setup:**
    You can verify that the API is running by sending a request to the `/ping` endpoint.

    **Example Request:**

    ```bash
    curl --location 'http://<ILIAS_BASE_URL>/rest/ping' \
    --header 'Accept: application/json'
    ```

    **Expected Response:**

    ```json
    {
        "success": true,
        "data": "pong"
    }
    ```

#### Postman Collection

For a more interactive testing experience, you can import our pre-configured [Postman Collection](docs/rest/ILIAS-postman_collection.json). It includes automated scripts for token management and examples for all core routes.

### Authentication

The REST API uses **Bearer Tokens (JWT)** for authentication. Any request to a protected endpoint must include an `Authorization` header.

```
Authorization: Bearer <your_access_token>
```

For detailed information on how to obtain and refresh tokens, please refer to the [**REST Webservice Authentication documentation**](docs/rest/authentication.md).

### Configuration

The API Gateway's behavior, particularly authentication, can be configured from the administration dashboard. These settings include the secret key, encryption and hashing algorithms, and token expiry times.

For a detailed explanation of each setting and the system impact of changing them, please refer to the [**Configuration documentation**](docs/configuration.md).

### Creating New API Endpoints

The API Gateway is extensible, allowing components to expose their functionality. There are three primary ways to create a new route. All routes are registered by contributing them in the component's `Component.php` file.

*For a detailed guide, see the [**REST Webservice Routing documentation**](docs/rest/routing.md).*

| Approach | When to use? | Parent Class |
| :--- | :--- | :--- |
| **`ApiRoute`** | Simple endpoints with logic that fits in a single function. | `ILIAS\ApiGateway\Routing\Route` |
| **Custom `Route` Class** | Complex endpoints that require their own dependencies or have detailed setup logic. | `ILIAS\ApiGateway\Routing\Route` |
| **`Activity`** | For routes tied to core domain logic. The path and HTTP method are generated **automatically**. | `ILIAS\Component\Activities\Activity` |

### Standard Response Format

The API returns responses in a standardized JSON format.

#### Successful Response

Successful requests will have `success` as `true` and the result of the operation in the `data` field.

```json
{
    "success": true,
    "data": {
        "user_id": 1337,
        "username": "root"
    }
}
```

#### Error Response

Failed requests will have `success` as `false` and include an `error` message.

```json
{
    "success": false,
    "error": "You are not allowed to perform this activity."
}
```

If the **Log Error Details** setting is enabled (or `DEVMODE` is active), a `stack` trace may also be included in the payload.

**Example Error Response (with Stack Trace)**

```json
{
    "success": false,
    "error": "Something went wrong.",
    "stack": [
        {
            "file": "/var/www/html/ilias/components/ILIAS/ApiGateway/src/Some/Class.php",
            "line": 123,
            "function": "someFunction",
            "class": "ILIAS\ApiGateway\Some\Class",
            "type": "->"
        },
        {
            "file": "/var/www/html/ilias/components/ILIAS/ApiGateway/src/Application/WebApp.php",
            "line": 456,
            "function": "handleRequest",
            "class": "ILIAS\ApiGateway\Application\WebApp",
            "type": "->"
        }
    ]
}
```

### Middlewares

The system includes a global authentication middleware. The following middlewares are currently available:

- `ILIAS\ApiGateway\Middleware\AuthenticationMiddleware`
- ... more will be available in future phases, check [ROADMAP.md](ROADMAP.md#backlog)

### Logging

*(Documentation for API logging and how to access logs will be available in a future update.)*

### Validation

*(Documentation for input and output validation schemas will be available in a future update.)*

### OpenAPI Specification

*(An OpenAPI (Swagger) specification for interactive API documentation, and automated client generation will be available in a future update.)*
