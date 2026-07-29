### WPKit

---

# usage

1. Add this repository in composer.json

```
"repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Bit-Apps-Pro/wp-kit"
    }
  ]
```

2. Then install the package

```
composer require bitapps/wp-kit:dev-main
```

## Security defaults

- Route middleware fails closed. Every alias passed to `middleware()` must be
  registered with `Router::setMiddlewares()`, and its class must define
  `handle()`.
- WPKit does not infer route authorization. Protect non-public REST/static
  routes with middleware or a request `authorize()` method, and add capability
  plus nonce checks to state-changing AJAX routes.
- `Request::ip()` uses `REMOTE_ADDR` unless that address is a configured trusted
  proxy. Configure exact proxy addresses or CIDR ranges before accepting
  `X-Forwarded-For`:

  ```php
  Request::setTrustedProxies(['10.0.0.0/8', '2001:db8::/32']);
  ```

- `HttpClient` uses `wp_safe_remote_request()` by default. Internal or otherwise
  unsafe URLs require an explicit opt-in:

  ```php
  $client->allowUnsafeUrls();
  ```

## Upgrade notes

- `Response::headers()` now requires an array and validates every entry through
  `Response::header()`; invalid header names, values containing CR/LF/NUL, and
  non-scalar values throw `InvalidArgumentException` instead of being stored
  silently.
- `Router::getRegisteredMiddleware()` throws
  `MiddlewareConfigurationException` for unregistered aliases, missing classes,
  or classes without `handle()` — it previously returned `null`. Route dispatch
  catches this internally and responds with `MIDDLEWARE_CONFIGURATION`; only
  direct callers need to migrate.
- `Router::instance($type)` now returns the router of the requested type (or
  creates one) instead of silently returning whatever router was constructed
  last. No-argument calls keep returning the current router. Creating a router
  (directly or via `instance($type)` miss) still makes it the current router,
  so declare routes before constructing transports.
- `Response::getCode()` returns `null` (previously `''`) when neither a code
  nor a status has been set.
- `RouteRegister::handleMiddleware()` called directly now returns `true`/`false`
  (previously `null`) and records the denial response without emitting it;
  full dispatch through `handleRequest()` is unchanged.
- Direct calls to `RouteRegister::getRequest()`/`getParamValue()` on a denying
  or invalid request record the failure response and return the built request /
  `null` respectively — they never throw.
- Route paths are compiled by one grammar for REST, AJAX, and static routes:
  literal segments are regex-quoted, `{param?}` now also matches with no
  trailing separator (`entries/{slug?}` matches `entries`), and duplicate or
  invalid parameter names throw `InvalidArgumentException` at registration.
  Trade-off: an optional param no longer matches the empty-value-with-trailing-
  slash form (`entries/`) on AJAX routes — use `entries` (no slash) instead.

## Tests

```bash
composer test
composer coverage
```

PHPUnit tests are grouped by public feature under `tests/` to protect
compatibility while internals are refactored. The PHPDBG coverage command
enforces 100% executable-line coverage for selected HTTP hardening methods.
