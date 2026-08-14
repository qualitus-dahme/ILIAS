# ILIAS Webservices Roadmap

**Work in progress**

*Note: This is a living document that will be updated as the project progresses through each phase.*

## Overview

The new API uses a modular architecture centered around the `ApiGateway` component, which leverages the [Slim Framework](https://www.slimframework.com/) to handle the request lifecycle. An incoming API request comes through a single entry point, which boots the ILIAS environment and passes the request to the Slim application.

The request lifecycle uses the [Slim Framework](https://www.slimframework.com/). An incoming API request comes through a single entry point, which boots up the ILIAS environment and passes the request to the Slim application to handle.

This architecture separates concerns by having a generic `ApiGateway` component. For context on the previous design, which was centered around a more abstract 'Activities' concept, you can read the [previous concept document](https://github.com/jeph864/ILIAS/blob/11/rest/components/ILIAS/rest/README.md).

## Phases

- **Phase 1:** REST API: Concepts and Basic Objects \[[WIKI](https://docu.ilias.de/go/wiki/wpage_8205_1357)\]
- **Phase 2:** REST API: Authentication and Service Control \[[WIKI](https://docu.ilias.de/go/wiki/wpage_8803_1357)\]
- **Phase 3:** REST API: Middlewares, Documentation and Error Handling \[[WIKI](https://docu.ilias.de/go/wiki/wpage_8825_1357)\]
- **Phase 4:** Activity I/O Validation Schemas
- **Phase 5:** SOAP Webservice Integration

## Backlog

- Middlewares & related Exceptions:
  - `ApiServiceStatusMiddleware` (Checks if the API is globally enabled)
  - `TransportSecurityMiddleware` (Adds HSTS header to enforce HTTPS)
  - `BrowserSecurityMiddleware` (Adds CSP, X-Frame-Options, etc. to harden browser clients)
  - `CorsMiddleware` (Manages which other domains are allowed to make requests to this API)
  - `JsonContentNegotiationMiddleware` / `NotAcceptableException` (Ensures the client is sending and accepting application/json)
  - `JsonBodyParserMiddleware` / `InvalidJsonException` (Safely parses the request body as JSON)
  - `RateLimitingMiddleware` / `RateLimitExceededException` (Protects the API from abuse by limiting request frequency)
  - `RequestLoggingMiddleware` (Logs request/response data for debugging and monitoring)
- I/O validation schemas for request and response data.
- Enhanced error handling and reporting.
- **Configuration:** Read environment variables and prioritize over dashboard to facilitate CI. (optional)
