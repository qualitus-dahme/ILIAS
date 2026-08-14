# REST Webservice Routing

## Table of Contents

* [TL;DR](#tldr)
  * [Register a new endpoint](#register-a-new-endpoint)
  * [An Important Rule: Duplicate Routes](#an-important-rule-duplicate-routes)
* [1. `Activity`: The Automated Approach](#1-activity-the-automated-approach)
  * [How It Looks](#how-it-looks)
  * [How the Route is Built Automatically](#how-the-route-is-built-automatically)
* [2. `ApiRoute`: The Direct Approach](#2-apiroute-the-direct-approach)
  * [How It Looks](#how-it-looks-1)
  * [What It Means](#what-it-means)
* [3. Your Own Route Class: The Advanced Approach](#3-your-own-route-class-the-advanced-approach)
  * [How It Looks](#how-it-looks-2)

In the API Gateway, an endpoint (like `/ping` or `/users/123`) is called a **Route**. The system is flexible, offering three main ways to create routes. No matter which method you choose, the basic process is the same: you define your route and then register it so the system can find it.

This registration happens by adding your `Route` or `Activity` to the `$contribute` array within the `init()` method of your `Component.php` file.

Let's break down the three approaches.

---

## Security Warning
The `ApiGateway` does not perform automatic input sanitization for routes. Every Route is responsible for its own data integrity. Note that each new endpoint inherently increases the attack surface, introducing risks such as injection attacks and unauthorized access.

- **Validation & Sanitization**: Developers must perform robust input validation and output sanitization within the `Route` action or the dependencies it uses. 
- **Layered Defense**: Middleware handles protocol-level authentication (tokens/scopes), but this is not a replacement for proper validation within the `Route` logic.

---

## TL;DR

### Register a new endpoint

In your `Component.php`, you define the contribution depending on which approach you choose.

| **Approach**    | **When to use?**                                                                                 | **Parent**                            |
|-----------------|--------------------------------------------------------------------------------------------------|---------------------------------------|
| `Activity`      | For routes tied to **domain logic**. The path and method are created **automatically**.          | `ILIAS\Component\Activities\Activity` |
| `ApiRoute`      | **Simple endpoints** with basic logic that can be written in a single function.                  | `ILIAS\ApiGateway\Routing\Route`      |
| `Route`         | **Complex endpoints** that need their own dependencies or detailed setup.                        | `ILIAS\ApiGateway\Routing\Route`      |

---

### An Important Rule: Duplicate Routes

No matter which approach you use, the routing system enforces uniqueness. If you register two routes that have the **exact same path and HTTP method** (e.g., two routes for `GET /users/{id}`), the webservice will fail to start and throw an exception. It will not silently ignore one of the routes.

---

## 1. `Activity`: The Automated Approach

This is a more advanced way to create routes. Instead of defining the route directly, you create an **Activity** class, and the system automatically turns it into a usable API route.

### How It Looks

First, create a simple `Activity` class. Then, register it in your `Component.php` file by "contributing" it to `ILIAS\Component\Activities\Activity::class`.

```php
## Components/Vendor/MyModule/SaveUserDetailsActivity.php

namespace Vendor\MyModule;

use ILIAS\Component\Activities\Activity;
use ILIAS\Component\Activities\ActivityType;

class SaveUserDetailsActivity implements Activity
{
    public function getType(): ActivityType
    {
        return ActivityType::Command;
    }

    // ... implementation of Activity methods
}

## Components/Vendor/MyModule/MyModule.php

$contribute[\ILIAS\Component\Activities\Activity::class] = static fn(): Activity => new SaveUserDetailsActivity();
```

By doing this, the system automatically creates the endpoint `/myvendor/mymodule/saveuserdetails` for you.

### How the Route is Built Automatically

* **Path is Auto-Generated:** The URL path is created automatically from your `Activity`'s class name (e.g., `MyVendor\MyComponent\QueryUserDetailsActivity`).
  * The system extracts the vendor, component, and the activity's core name.
  * It then cleans up the name by removing prefixes like "Query" or "Get" and suffixes like "Activity".
  * All parts are converted to lowercase and joined with slashes.
  * Additionally, if the `Activity` is an instance of `ObjectActivity`, then `/{id}` will be appended to the generated route path. For example, a `QueryUserActivity` (an `ObjectActivity`) might result in `/myvendor/mymodule/user/{id}`.
* **Special Rule for Core:** If the `Activity` is part of the core `ILIAS` vendor, the "ilias" part is left out of the URL to keep it shorter (e.g., `ILIAS\User\GetAllUsersActivity` becomes `/user/allusers`).
* **HTTP Method is Inferred:** The system assigns `GET` for a `Query` activity and `POST` for a `Command` activity.
* **Handler is the Activity:** Your `Activity` class itself contains the logic for the route.

#### Handler Parameters and Validation

When implementing the logic for an `Activity`-based route, there are two important considerations:

* **Input and Output Validation:** The API Gateway does not yet provide automatic validation for `Activity` inputs and outputs. You are responsible for implementing any necessary validation and sanitization within your `Activity`'s logic to ensure data integrity and security.

* **Accessing the Authenticated User:** The API Gateway passes the authenticated user's ID separately to `Activity::maybePerformAs()`. The Activity uses this ID when checking `isAllowedToPerform()`. Authentication information is not injected into the Activity's input parameters.

* **Accessing Object IDs:** If your `Activity` implements `ObjectActivity`, its route path will automatically include an `/{id}` segment. The value of this ID will be provided in the parameter array passed to your `perform` method under the key `'id'`. This `'id'` value will override any client-provided input with the same key. You should include this `'id'` in your `Activity`'s `getInputDescription()` method so it is included in the automated validation as a required field (typically an integer or a string/UUID).

---

## 2. `ApiRoute`: The Direct Approach

Use `ApiRoute` for simple, standalone endpoints. It's perfect for prototyping or when you don't need a lot of complex logic.

You define the path, HTTP methods, and handler function all in one place. You then register it by "contributing" the `ApiRoute` to `ApiGateway\Routing\Route::class` within your component's `Component.php` file.

### How It Looks

Here's an example from the `Component.php` file, which creates the `/ping` endpoint:

```php
## Components/Vendor/MyModule/MyModule.php

use ILIAS\ApiGateway\Routes\ApiRoute;
use ILIAS\ApiGateway\Routing\Route;

$contribute[Route::class] = static fn(): Route =>
    new ApiRoute(
        name: 'Ping',
        path: "/ping",
        method: 'GET',
        description: 'A simple ping pong route for testing purposes.',
        action: fn(): string => 'Pong!',
    );
```

This approach is straightforward and easy to understand for anyone familiar with modern PHP frameworks.

### What It Means

* **`path: "/ping"`**: This is the URL fragment for the endpoint.
* **`method: 'GET'`**: This route will only respond to `GET` requests.
* **`action: fn(): string => 'Pong!'`**: This is the actual code that executes. In this case, it's a simple anonymous function (a `Closure`) that returns the string "Pong!". This is what gets sent back to the user as the response body.

---

## 3. Your Own Route Class: The Advanced Approach

For the most complex scenarios, create a custom class that implements the `Route` interface. This gives maximum flexibility and is ideal when a route has its own dependencies or complex logic.

The primary reason to choose this approach over `ApiRoute` is for **dependency injection**. Because this is a dedicated class, services (like repositories, factories, etc.) can be injected into its constructor, which is not possible with the simple `Closure` handler used by `ApiRoute`.

### How It Looks

A class is created that defines all the route's properties and logic. Just like `ApiRoute`, it is then registered in the `Component.php` by contributing an instance of the new class to `ApiGateway\Routing\Route::class`.

```php
## Components/Vendor/MyModule/GetCourseByIdRoute.php

use ILIAS\ApiGateway\Auth\Domain\Model\AuthUser;
use ILIAS\ApiGateway\Routing\Action;
use ILIAS\ApiGateway\Routing\Route;

class GetCourseByIdRoute implements Route, Action
{
    // Dependencies can be injected via the constructor
    public function __construct(private CourseRepository $repository)
    {
        //
    }

    // Define the path and any placeholders
    public function getPath(): string
    {
        return '/courses/{id}';
    }

    // Define the allowed method
    public function getMethod(): string
    {
        return 'GET';
    }

    // The __invoke method contains the route's logic
    public function __invoke(array $params, ?AuthUser $user) : Course
    {
        $courseId = (int) $params['id'];

        return $this->repository->get($courseId);
    }
    
    // ... other methods required by the Route interface
}

## Components/Vendor/MyModule/MyModule.php

$contribute[\ILIAS\ApiGateway\Routing\Route::class] = static fn(): Route =>
    new GetCourseByIdRoute(
        $pull[CourseRepository::class],
    );
```
