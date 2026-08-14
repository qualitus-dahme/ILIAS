
# Maintenance Documentation

This guide is for developers maintaining or extending the core functionality of the `ApiGateway` component.

## Table of Contents

* [Architecture Overview](#architecture-overview)
* [Core Components & Concepts](#core-components--concepts)
* [Request Lifecycle](#request-lifecycle)
* [How to Extend the ApiGateway Core](#how-to-extend-the-apigateway-core)
* [Exceptions Handling](#how-to-handle-exceptions)

## Architecture Overview

The `ApiGateway` component provides a modern, modular architecture for building APIs in ILIAS. It is built on the [Slim Framework](https://www.slimframework.com/), which handles the underlying request-response lifecycle.

**Key Principles:**

* **Single Entry Point:** All REST API requests enter through `/rest/index.php`, which boots the ILIAS environment and hands control to the `WebApp`.
* **Dependency Injection:** Services are wired together in `ApiGateway.php` using the ILIAS component system's dependency management features (`$define`, `$contribute`, `$pull`, etc.).
* **Layered Structure:** The component follows principles of layered architecture, separating concerns into `Domain`, `Application`, and `Infrastructure` layers, although this is a work in progress.
* **Extensibility:** The system is designed to be extended by other components, primarily through the contribution of `Route` and `Activity` objects.

## Core Components & Concepts

* **`ApiGateway.php`**: The central service provider for the component. It defines and instantiates all core services, middlewares, and routes, acting as a dependency injection container.
* **`RestAppEntryPoint.php`**: The official ILIAS entry point. Its main job is to initialize the ILIAS environment and start the `WebApp`.
* **`Application/WebApp.php`**: The primary orchestrator. It configures the Slim application, registers all routes from the `RoutesRegistry`, adds global middlewares, and starts the request handling process.
* **`Webservice/`**: This is the "View" or "Formatting" layer.
  * `RestWebservice.php` is responsible for serializing data into the final JSON response structure for both successful and failed requests.
* **`Routing/`**:
  * `Route.php`: An interface that defines what an API endpoint is (path, method, action).
  * `RoutesRegistry.php`: A singleton that collects all `Route` objects contributed by different components and ensures there are no duplicates.
  * `RoutesAutoloader.php`: Discovers and loads all `Route` objects contributed to the component system.
* **`Activity/`**:
  * `ActivityRoutesAutoloader.php`: Discovers all `ILIAS\Component\Activities\Activity` objects from other components and automatically converts them into API routes.
  * `ActivityNamespace.php`: Contains the logic for auto-generating a URL path from an `Activity`'s class name.
* **`Auth/`**:
  * `AuthenticationMiddleware.php`: A global middleware that intercepts incoming requests, extracts the bearer token, and uses the `Authentication` service to validate it.
  * `Service/Authentication.php`: The domain service containing the core logic for creating, validating, and refreshing tokens.
  * `Repository/`: Defines interfaces for data persistence (`UserRepository`, `RefreshTokenRepository`), with concrete implementations in the `Infrastructure` directory.
* **`Configuration/`**:
  * `ConfigurationService.php`: Provides a unified API for accessing configuration values, abstracting whether the source is an admin setting, `client.ini.php`, or a constant.
  * `ilApiGatewaySettings.php` & `classes/class.ilObjApiGatewayGUI.php`: The legacy implementation of the administration panel UI for managing settings.

## Request Lifecycle

1. An HTTP request arrives at `/rest/index.php`.
2. `RestAppEntryPoint` is invoked. It initializes the ILIAS environment via `AllModernComponents` and then runs the `WebApp`.
3. `WebAppFactory` constructs the `WebApp` instance, injecting all its dependencies.
    * During this process, `RoutesAutoloader` and `ActivityRoutesAutoloader` run, discovering all available routes and registering them in the `RoutesRegistry`.
4. The `WebApp` configures the Slim application with global middlewares (like the `ErrorMiddleware`) and all the routes from the registry.
5. The Slim application's routing middleware matches the incoming request to a registered route.
6. Route-specific middlewares (e.g., `AuthenticationMiddleware`) are executed.
7. If all middlewares pass, the `ResponseHandler` is invoked. It calls the route's specific action (its `__invoke` method).
8. The return value from the action is wrapped in a `Payload` object and passed to `RestWebservice` to be formatted into a standard JSON response.
9. If at any point an exception is thrown, the `ErrorHandler` catches it and uses `RestWebservice` to format a standard JSON error response.

## How to Extend the ApiGateway Core

* **Adding a New Core Route:**
    1. Create a new class that implements `ILIAS\ApiGateway\Routing\Route`. For simple cases, extend `ILIAS\ApiGateway\Routes\ApiRoute`.
    2. Instantiate and contribute the new route class within `ApiGateway.php`.

* **Adding a New Global Middleware:**
    1. Create a class that implements `Psr\Http\Server\MiddlewareInterface`.
    2. Instantiate and contribute the middleware in `ApiGateway.php`.
    3. Add the middleware to the `WebApp`'s middleware stack, typically in `WebApp::registerMiddlewares()`.

* **Adding a New Configuration Option:**
    1. Add the new setting to the `Configuration/Domain/Enum/SystemSetting.php` enum.
    2. Update `Configuration/Infrastructure/ConfigurationService.php` to expose the new setting.
    3. Update `Configuration/ilApiGatewaySettings.php` and `classes/class.ilObjApiGatewayGUI.php` to include the new setting in the administration UI.

## How to Handle Exceptions

The component employs a centralized error handling strategy to ensure API responses are consistent and predictable. A 'safety net' at the application's edge catches any unhandled `Throwable` and formats it into a standard JSON error response.

### Core Principles

When an error occurs, the code that understands the failure should throw a semantically meaningful exception. This provides context to the centralized error handler, which then generates the appropriate HTTP status code.

* **Use Specific Exceptions:** Whenever possible, throw a specific exception from `src/Application/Exception/`, such as `AuthenticationException` (401) or `AuthorizationException` (403).
* **Avoid Generic Exceptions:** Do not throw generic exceptions like `\Exception` or `ilException` from application logic. This results in a generic "500 Internal Server Error" and loses important context about what failed.

**Example:**

```php
// From AuthenticationMiddleware.php
if (empty($authHeader)) {
    throw new AuthenticationException('Authorization header missing or invalid.');
}
```

### Handling External Exceptions

When interacting with external libraries or legacy ILIAS services, catch their exceptions and re-throw a more specific exception from the component. This practice, known as "exception translation," prevents implementation details of dependencies from leaking into the core logic and ensures a consistent error contract for the API.

The original exception should be passed as the "previous" exception to preserve the stack trace for debugging.

**Example:**

```php
// From JwtService.php
try {
    $decoded = JWT::decode(...);
} catch (DomainException | UnexpectedValueException $e) {
    // Translate the library's error into our application's error contract
    throw new AuthenticationException(
        'The provided token is invalid or expired.',
        $e // Preserve the original exception
    );
}
```

### Creating Custom Exceptions

If a new, distinct error condition is needed that is not covered by existing exceptions, a new exception class can be created within the `src/Application/Exception/` directory. This ensures the new error type integrates with the centralized handler and can be assigned a specific HTTP status code.

### The Central Error Handler

The `Application/ErrorHandler.php` middleware serves as the centralized "safety net." It is registered globally and is the final handler in the chain. Its sole responsibility is to catch any unhandled `Throwable`, inspect its code to determine the correct HTTP status, and delegate the serialization of the final error. For REST requests, this serialization is handled by the `RestWebservice` to produce the standard JSON format. No manual intervention is typically needed with this component.
