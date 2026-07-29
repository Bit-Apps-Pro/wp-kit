# WPKit

A toolkit for building WordPress plugins: a routing layer over REST, AJAX and
static pages; form requests with validation and authorization; a fluent
response builder; a safe HTTP client; and thin facades over hooks, shortcodes,
migrations and the activation lifecycle. Public API is kept backward compatible.

## Install

Add the repository to your `composer.json`:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Bit-Apps-Pro/wp-kit" }
]
```

Then require the package:

```bash
composer require bitapps/wp-kit:dev-main
```

## Quick start

### 1. Wire a router (in your plugin bootstrap)

```php
use BitApps\WPKit\Http\Router\Router;

$api = new Router('api', 'myplugin', 'v1');          // REST namespace: myplugin/v1
$api->setMiddlewares(['auth' => AuthMiddleware::class]);
$api->registerFile(__DIR__ . '/routes/api.php');     // define routes there
add_action('rest_api_init', [$api, 'register']);
```

Use `'ajax'` for admin-ajax routes and register those on `init`. Construct the
router before declaring its routes — a new router becomes the current one.

### 2. Declare routes with the `Route` facade

```php
// routes/api.php
use BitApps\WPKit\Http\Router\Route;

Route::get('entries', [EntryController::class, 'index']);
Route::post('entries/{id}', [EntryController::class, 'update'])->middleware('auth');

Route::prefix('admin')->group(function () {
    Route::get('stats', [StatsController::class, 'show'])->middleware('auth');
});
```

Path params (`{id}`, optional `{slug?}`) are injected by name into the action.

### 3. Return responses

```php
use BitApps\WPKit\Http\Response;

class EntryController
{
    public function index()
    {
        return Response::success(['items' => []]);        // 200
    }

    public function update($id)
    {
        return Response::error(['id' => $id], 404)         // custom status
            ->code('NOT_FOUND')
            ->message('Entry not found');
    }
}
```

Returning a plain array/string wraps it in a success envelope automatically.

### 4. Form requests — validation + authorization

Type-hint a `Request` subclass on the action; wp-kit builds it, authorizes,
and validates before the action runs. Failures short-circuit with an error
response and the action never executes.

```php
use BitApps\WPKit\Http\Request\Request;

class EntryRequest extends Request
{
    public function authorize()
    {
        return current_user_can('edit_posts');
    }

    public function rules()
    {
        return ['title' => ['required'], 'body' => ['required']];
    }
}

// EntryController::store(EntryRequest $request)
$data = $request->all();
$title = $request->input('title');
$ip = $request->ip();
```

### 5. Middleware

Register aliases on the router, then apply them per route or group. A middleware
class defines `handle(Request $request, ...$params)` and returns `true` to pass
or a `Response` to block. Middleware fails closed — an unregistered alias, a
missing class, or one without `handle()` is rejected.

```php
$router->setMiddlewares(['auth' => AuthMiddleware::class, 'role' => RoleMiddleware::class]);

Route::post('entries', [EntryController::class, 'store'])->middleware('auth', 'role:editor');
```

### 6. HTTP client

```php
use BitApps\WPKit\Http\Client\Http;
use BitApps\WPKit\Http\Client\HttpClient;

// Facade: returns the decoded JSON body (array) or raw string, or a WP_Error
$data = Http::post('https://api.example.com/hooks', ['event' => 'created']);

// Instance: when you also need the status code / headers
$client = new HttpClient();
$body   = $client->request('https://api.example.com/hooks', 'POST', ['event' => 'created']);
$code   = $client->getResponseCode();
```

Safe by default (`wp_safe_remote_request`); call `$client->allowUnsafeUrls()` to
reach internal hosts.

### 7. Hooks, shortcodes, lifecycle

```php
use BitApps\WPKit\Hooks\Hooks;
use BitApps\WPKit\Shortcode\Shortcode;

Hooks::addAction('init', [$plugin, 'boot']);
Shortcode::addShortcode('myplugin_widget', [$plugin, 'renderWidget']);
```

`Installer` handles activation/deactivation/uninstall and requirement checks;
`Migration` + `MigrationHelper` run schema migrations; `StaticRouter` maps custom
front-end page URLs (via rewrite rules) to routes.

## Components

- `Http\Router` — `Router`, `Route`/`RouteBase`, REST/AJAX transports, `StaticRouter`, `RequestType`
- `Http\Request\Request` — form requests, input access, IP resolution
- `Http\Response` — fluent response builder
- `Http\Client` — `HttpClient`, `Http` facade
- `Http\Detection` — `ClientIpResolver`, `UserAgent`
- `Hooks`, `Shortcode` — WordPress facades
- `Installer`, `Migration` — plugin lifecycle
- `Helpers` — `Arr`, `JSON`, `Slug`, `DateTimeHelper`; `Utils\Capabilities`

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
- IP/device detection classes moved to `Http\Detection\` (`ClientIpResolver`,
  `UserAgent`). The `Http\IpTool` trait and `Request::ip()`/`device()` facade
  are unchanged.

## Tests

```bash
composer test
composer coverage
```

PHPUnit tests are grouped by public feature under `tests/` to protect
compatibility while internals are refactored. The PHPDBG coverage command
enforces 100% executable-line coverage for selected HTTP hardening methods.
