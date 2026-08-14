# REST Webservice Authentication

## Table of Contents
* [1. Requesting an Authentication Token](#1-requesting-an-authentication-token)
* [2. Refreshing an Authentication Token](#2-refreshing-an-authentication-token)
* [3. Using the Authenticated User in Actions](#3-using-the-authenticated-user-in-actions)

The REST API uses bearer tokens for authentication. API requests that require authentication must include an `Authorization` header with a valid access token.

```
Authorization: Bearer <your_access_token>
```

The following endpoints are available to obtain and refresh tokens.

## 1. Requesting an Authentication Token

This endpoint authenticates a user with their username and password and returns a new set of access and refresh tokens.

* **Endpoint:** `POST /rest/auth/token`
* **Request Body:** A JSON object containing the user's `username` and `password`.

**Example Request:**

```bash
curl --location 'http://<ILIAS_BASE_URL>/rest/auth/token' \
--header 'Content-Type: application/json' \
--data '{ 
    "username": "your_username",
    "password": "your_password"
}'
```

**Example Successful Response:**

```json
{
    "success": true,
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "def5020023e63064d77170889...",
        "expires_at": 1701384000
    }
}
```

**Example Error Response:**

```json
{
    "success": false,
    "error": "Wrong username or password."
}
```

## 2. Refreshing an Authentication Token

When an access token expires, a new one can be obtained by sending the `refresh_token` to this endpoint. This will issue a new token set and invalidate the old refresh token.

* **Endpoint:** `POST /rest/auth/refresh`
* **Request Body:** A JSON object containing the `refresh_token`.

**Example Request:**

```bash
curl --location 'http://<ILIAS_BASE_URL>/rest/auth/refresh' \
--header 'Content-Type: application/json' \
--data '{ 
    "refresh_token": "<your_refresh_token>"
}'
```

**Example Successful Response:**

```json
{
    "success": true,
    "data": {
        "access_token": "abc1234567890...",
        "refresh_token": "ghi0987654321...",
        "expires_at": 1701387600
    }
}
```

**Example Error Response:**

```json
{
    "success": false,
    "error": "Refresh token is invalid or has been revoked."
}
```

## 3. Using the Authenticated User in Actions

Once a request has been successfully authenticated by the system's middleware, the details of the authenticated user are made available to the route handler.

The `__invoke` method of any `Action` (whether it's from an `ApiRoute`, `Activity`, or custom `Route` class) receives the authenticated user as its second parameter, an instance of `ILIAS\ApiGateway\Auth\Domain\Model\AuthUser` (or `null` if the route doesn't require authentication, or if the user is not authenticated for some reason).

```php
use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;

// Inside the Action's __invoke method:
public function __invoke(array $params, ?AuthUser $user)
{
    if ($user !== null) {
        // User is authenticated, their ID can be accessed
        $userId = $user->getId();
        // ... perform actions using the authenticated user ...
        return "Hello, User ID: " . $userId;
    }

    // User is not authenticated (e.g., this is a public route, or authentication failed earlier)
    return "Hello, Guest!";
}
```

This ensures that business logic within route actions can directly access user context without needing to manually parse tokens or perform authentication checks again. If a route *requires* authentication and it fails, the request will be rejected by the middleware *before* it reaches the `__invoke` method.
