# Developing Gateway-Compliant Activities

## Table of Contents

* [The Main Service Contract](#the-main-service-contract)
    * [Stability with Dedicated Names](#stability-with-dedicated-names)
* [Naming and Routing](#naming-and-routing)
* [Object-Bound Activities](#object-bound-activities)
* [Authorization Responsibility](#authorization-responsibility)
* [Execution Workflow: perform vs. maybePerformAs](#execution-workflow-perform-vs-maybeperformas)
* [Structured Error Handling](#structured-error-handling)
* [Legacy Dependencies ($DIC)](#legacy-dependencies-dic)
* [Upcoming / Planned Features](#upcoming--planned-features)

This guide defines the standards for implementing `ILIAS\Component\Activities\Activity` classes that are fully compatible with the **ApiGateway component** (The Service Facade). Following these patterns ensures your component automatically benefits from schema generation and structured error handling, regardless of the underlying protocol (e.g., REST, GraphQL).

## Security Warning
The `ApiGateway` does not perform automatic input sanitization for Activities. Every Activity is responsible for its own data integrity. Note that each new Activity inherently increases the attack surface, introducing risks such as injection attacks and unauthorized access.

- **Validation & Sanitization**: Developers must perform robust input validation and output sanitization within the `Activity` logic or the dependencies it uses. 
- **Layered Defense**: Middleware handles protocol-level authentication (tokens/scopes), but this is not a replacement for proper validation within the `Activity`.
- **Principle of Least Privilege**: Ensure `isAllowedToPerform()` correctly enforces business-level authorization to prevent unauthorized object access.

---

## The Main Service Contract

The Gateway uses your Activity as the **primary source** for the service contract.

- **`getInputDescription()`**: Defines the machine-readable input schema. The Gateway uses this to validate incoming payloads before your code is executed.
- **`getOutputDescription()`**: Defines the machine-readable output schema. This drives the auto-generation of protocol-specific schemas.

### Stability with Dedicated Names
To ensure contract stability, always use `withDedicatedName()` for your fields in **both** input and output descriptions. This separates the external API identifiers from your internal PHP implementation details.

#### The Fallback Logic (Final Resort)
The Gateway implements a fallback mechanism to prevent empty identifiers:
- **Input**: If `withDedicatedName()` is missing, the Gateway falls back to the internal PHP array key defined in the `group()` or `section()`.
- **Output**: If `withDedicatedName()` is missing, the Gateway attempts to use the field's **Label/Title** as the identifier.

Warning: Relying on these fallbacks is a final resort and **is not recommended**.
- **Identifier Fragility**: Identifiers derived from Labels/Titles are often non-standard, contain spaces, or result in "ugly" and unreadable keys (e.g., `"User's First Name"` instead of `firstName`).
- **Localization Risk**: Labels are often translated into different languages. If you rely on the fallback, your API identifiers will change based on the user's language, which will break the contract for international clients. Changing a UI label will silently break your API contract if you rely on the fallback.

#### Best Practice: Case Consistency
Always maintain a consistent casing strategy for your keys (e.g., `camelCase` or `snake_case`) across the entire activity to ensure a professional and predictable API experience.

#### In Input Description
```php
// In getInputDescription
$f = $this->uiFactory->input()->field();

$username = $f->text("Username")
    ->withRequired(true)
    ->withDedicatedName('username'); // RECOMMENDED: Stable, clean identifier
```

#### In Output Description
```php
// In getOutputDescription
return $f->dictionary([
    'id' => $f->int()->withDedicatedName('userId'),
    'email' => $f->text()->withDedicatedName('userEmail')
]);
```

## Naming and Routing

The Gateway automatically generates the endpoint path and HTTP method based on your Activity's class name and type.

### 1. HTTP Method Mapping
The Gateway assigns the HTTP method based on the `ActivityType`:
- **`ActivityType::Query`** → `GET` (for data retrieval).
- **`ActivityType::Command`** → `POST` (for data modification).

### 2. Automatic Path Generation
The URL path is derived from your Activity's namespace and class name:
`Vendor\Component\ActionNameActivity` → `/vendor/component/actionname`

#### Clean Naming Rules:
To keep URLs concise, the Gateway automatically trims prefixes and suffixes:
- **Trimming**: Prefixes like "Query" or "Get" and suffixes like "Query" or "Activity" are removed.
- **Casing**: All generated path segments are converted to **lowercase**.
- **Core Exception**: If the vendor is `ILIAS`, it is omitted from the path (e.g., `ILIAS\User\GetAllUsersActivity` → `/user/allusers`).

## Object-Bound Activities

If your Activity refers to a specific object (repository object or internal component entity), it should implement **`ILIAS\Component\Activities\ObjectActivity`**.

- **Automatic Routing**: The Gateway automatically appends **`/{id}`** to the URL path.
- **Accessing the ID**: The value of this `{id}` will be provided in the parameter array passed to your `perform` method under the key `'id'`. This `'id'` value will override any client-provided input with the same key.
- **Fixed Input**: You should include this `'id'` in your `Activity`'s `getInputDescription()` method so it is included in the automated validation as a required field (typically an integer or a string/UUID).
- **Metadata**: You must implement `getTargetType()`, returning a short alphanumeric string representing the object type (e.g., `crs`, `usr`).

## Authorization Responsibility

The Gateway handles **Token Validation** and **Protocol-level Scopes**. The Activity is responsible for **Object-Level Authorization** through business rules.

### Implementing Permissions
Implement all business-rule checks in **`isAllowedToPerform(int $usr_id, mixed $parameters)`**.

- **Side-Effect Free**: This method **must not** cause any data changes or observable side effects. It is a read-only check.
- **Business Logic**: Verify if the user `$usr_id` has sufficient rights (RBAC, positions, ownership) to perform the action on the resource identified by `$parameters`.
- **Calling Convention**: The Gateway does **not** call this method directly. Instead, it calls `maybePerformAs()`, which is responsible for invoking this check before proceeding to execution.

## Execution Workflow: perform vs. maybePerformAs

The `Activity` interface provides two distinct ways to execute logic, catering to different levels of data trust.

### 1. `perform(mixed $parameters)`
This is the **core execution logic**. 
- **Assumption**: It assumes parameters are already validated and the user is authorized.
- **Return**: Should return data matching `getOutputDescription()`.
- **Exceptions**: May throw standard SPL exceptions if an unrecoverable error occurs.

### 2. `maybePerformAs(int $usr_id, array $raw_parameters)`
This is the **secure entry point** for untrusted data (e.g., from a REST request).
- **Responsibility**: It handles the orchestration of authorization and execution. It calls `isAllowedToPerform()`, and if successful, delegates to `perform()`.
- **Input Validation**: Note that **input validation against the schema is already handled by the Gateway** before this method is invoked.
- **Result Wrapping**: It returns an `ILIAS\Data\Result`, wrapping either the output or a classification of why it failed.

```php
public function maybePerformAs(int $usr_id, array $raw_parameters): Result {
    try {
        // Validation is already done by the Gateway.
        // Convert raw parameters as needed for isAllowedToPerform and perform.
        $parameters = $raw_parameters; 

        if (!$this->isAllowedToPerform($usr_id, $parameters)) {
            return $this->data_factory->result()->error(
                new ResultError('FORBIDDEN', StatusCode::HTTP_FORBIDDEN)
            );
        }

        return $this->data_factory->result()->ok($this->perform($parameters));
    } catch (Throwable $e) {
        return $this->data_factory->result()->error($e->getMessage());
    }
}
```

## Structured Error Handling

To allow the Service Facade to return correct status codes, Activities should return a structured error classification using the `ILIAS\Component\Activities\ResultError` value object inside an `ILIAS\Data\Result`.

### The Error Pattern
Return a `Result\Error` object wrapping a classification instance. Use string identifiers (e.g., `NOT_FOUND`, `FORBIDDEN`) and standard HTTP status codes for mapping.
> **Note**: The `ResultError` class used in the examples below **does not currently exist** in the ILIAS core, and there is no current plan to include it. It is provided here as a **conceptual example** to demonstrate the recommended pattern for structured error classification. If you implement a similar mechanism in your component, it should follow this idea:

```php
// CONCEPTUAL EXAMPLE ONLY (This class does not exist in core)
namespace ILIAS\Component\Activities;

use Exception;

/**
 * Conceptual example of a standardized error classification.
 */
final class ResultError extends Exception
{
...
    public function __construct(
        private readonly string $type,
        private readonly int $code,
        private readonly string $message,
    ) {
        parent::__construct($message, $code);
    }

    public function getType(): string
    {
        return $this->type;
    }
}
```

#### Usage Example

```php
use ILIAS\Component\Activities\ResultError;
use ILIAS\HTTP\StatusCode;

public function perform(mixed $parameters): mixed {
    if (!$resource_exists) {
        return $this->data_factory->result()->error(
            new ResultError(
                'NOT_FOUND',
                StatusCode::HTTP_NOT_FOUND, // 404
                'The requested resource does not exist.'
            )
        );
    }
    
    return $this->data_factory->result()->ok($data);
}
```

## Legacy Dependencies ($DIC)

While the Gateway is built on the modern ILIAS DI system, accessing the legacy global `$DIC` is allowed during the transition phase, but it is **not recommended**.

- **When to use**: Use it only if a required dependency is not yet available in the new DI system.
- **Support Status**: Support for legacy `$DIC` keys is currently temporary and partial.
- **Expansion**: If you need a specific `$DIC` key that isn't working, more keys can be supported by request.
- **Best Practice**: Always prefer constructor injection for modern services and use legacy adapters as a temporary bridge only when strictly necessary.

## Upcoming / Planned Features

The following sections are planned for future integration into this standard:

### Observability & Tracing
Every request is assigned a unique **Correlation ID**. Standards for propagating this ID and trace context across the Facade and Component layers will be documented here.

### Reference Implementation
A comprehensive reference implementation covering all major input types and complex output schemas will be provided as a baseline for new activities.
