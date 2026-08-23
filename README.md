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

Dynamic instance verbs include `get`, `post`, `put`, `patch`, `delete`, `head`,
and `options`. Query parameters are appended safely when the request path already
contains a query string.

Multipart requests use standards-compliant boundaries and CRLF framing. Multipart
mode cannot be combined with JSON, form parameters, or a separate request body;
field names, filenames, boundaries, and part headers reject control characters.

Safe by default (`wp_safe_remote_request`). To reach known internal hosts, an
administrator must explicitly enable unsafe URLs and allowlist each exact trusted
endpoint host:

```php
$client = (new HttpClient())
    ->allowUnsafeUrls(true, ['10.0.0.20', 'internal-api.example']);
```

Authorization is host-only: URLs must use HTTP or HTTPS, but any port and path
on an allowlisted host remain reachable. Validate untrusted URL components
separately.

Only administrator-configured trusted endpoints belong in this allowlist; never
derive hosts from arbitrary request values. Unsafe requests never follow redirects.

### 7. Hooks, shortcodes, lifecycle

```php
use BitApps\WPKit\Hooks\Hooks;
use BitApps\WPKit\Shortcode\Shortcode;

Hooks::addAction('init', [$plugin, 'boot']);
Shortcode::addShortcode('myplugin_widget', [$plugin, 'renderWidget']);
```

`Installer` handles activation/deactivation/uninstall and requirement checks;
`Migration` + `MigrationHelper` run schema migrations; `StaticRouter` maps custom
front-end page URLs (via rewrite rules) to routes. Static routes enforce their
declared HTTP methods. Their actions must return string-compatible page content;
`null` renders no additional content.

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
  unsafe URLs require an explicit, administrator-configured exact-host allowlist:

  ```php
  $client->allowUnsafeUrls(true, ['10.0.0.20', 'internal-api.example']);
  ```

  Do not allowlist hosts supplied by arbitrary requests. Unsafe requests do not
  follow redirects.

## Upgrade notes

- `HttpClient::allowUnsafeUrls()` remains a valid call, but calling it without an
  explicit host allowlist now intentionally fails closed. Unsafe requests return
  a `WP_Error` with the `unsafe_url_not_allowed` code instead of reaching the
  transport. Configure exact, trusted hosts with
  `allowUnsafeUrls(true, ['internal-api.example'])`.
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
- Static routes now generate one complete, anchored rewrite rule for every
  declared path, including literal and optional-parameter paths. Undeclared
  intermediate prefixes are no longer registered as routes.
- Static page dispatch now enforces the route's declared HTTP methods and accepts
  only string-compatible output. Direct `RouteRegister::handleRequest()` and
  custom router types continue returning raw values for backward compatibility.
- `Response::success()` and `Response::error()` now start with fresh metadata;
  messages, codes, and headers no longer leak from an earlier factory response.
  Bulk header changes are validated completely before replacing existing headers.
- `Request::input()` and the other frozen request/IP extension points intentionally
  remain untyped so downstream subclasses with legacy signatures remain compatible.
- IP/device detection classes moved to `Http\Detection\` (`ClientIpResolver`,
  `UserAgent`). The `Http\IpTool` trait and `Request::ip()`/`device()` facade
  are unchanged. Modern `Edg/` user agents are recognized as Edge, and OS matching
  no longer suppresses malformed regular-expression warnings.

## Container

Lightweight IoC container for managing service bindings and providers. Supports
constructor autowiring: when resolving a type, dependencies are automatically
injected if their classes are type-hinted in the constructor.

```php
use BitApps\WPKit\Container\Application;
use BitApps\WPKit\Container\ServiceProvider;

// Build a container with service providers.
$app = new Application();

// Register a simple binding.
$app->bind(Logger::class, FileLogger::class);

// Register a shared singleton instance (same instance every time).
$app->singleton(Database::class, function ($container) {
    return new Database($container->make(Connection::class));
});

// Resolve and autowire dependencies.
$logger = $app->make(Logger::class);      // resolves as FileLogger
$db = $app->make(Database::class);        // autowires Connection

// Register a service provider.
class MailProvider extends ServiceProvider {
    public function register(): void {
        $this->app->singleton(Mailer::class);
    }
    
    public function boot(): void {
        // Bootstrap logic after all providers are registered.
    }
}

$app->register(MailProvider::class);

// Boot all registered providers.
$app->boot();
```

## Settings

Typed get/set/save access to wp_options rows. Define a schema of typed fields,
then use a `SettingsRepository` to load, modify, and persist them with automatic
casting and sanitization.

```php
use BitApps\WPKit\Settings\SettingField;
use BitApps\WPKit\Settings\SettingsSchema;
use BitApps\WPKit\Settings\SettingsRepository;

// Define a schema with typed fields.
$schema = (new SettingsSchema())
    ->add(
        SettingField::bool('enabled', false, 'general'),
        SettingField::int('max_retries', 3, 'general'),
        SettingField::enum('log_level', ['debug', 'info', 'error'], 'info', 'logging'),
        SettingField::string('api_key', '', 'api')
    );

// Create a repository for the wp_options row.
$repo = new SettingsRepository('myplugin_settings', $schema);

// Get typed values (with automatic casting from stored values).
$enabled = $repo->get('enabled');       // bool
$level = $repo->get('log_level');       // string (validated against choices)

// Set values (cast to field type).
$repo->set('enabled', '1')->set('max_retries', '5');

// Bulk update and save.
$repo->fill(['enabled' => true, 'api_key' => 'secret']);
$repo->save();
```

## Cron

Registers custom cron schedules and recurring/one-off jobs. Wire callbacks onto
WordPress cron hooks with fluent scheduling.

```php
use BitApps\WPKit\Cron\Scheduler;

$scheduler = new Scheduler();

// Define a custom cron interval.
$scheduler->addSchedule('every_minute', 60, 'Every Minute');

// Register a recurring job.
$scheduler
    ->job('myplugin_hourly_sync', 'hourly', function () {
        // Sync data every hour.
    })
    ->job('myplugin_sync', 'every_minute', function () {
        // Custom schedule set via addSchedule.
    });

// Register a one-off job.
$scheduler->once('myplugin_one_time', time() + 3600, function () {
    // Fire once, in 1 hour.
});

// Wire hooks and schedule pending events.
$scheduler->boot();

// On plugin deactivation, clear all scheduled events.
$scheduler->clearAll();
```

## Cache

Manages named cache stores (array, transient, WP object cache, or file). Access
stores via the manager, or use the static `Cache` facade.

```php
use BitApps\WPKit\Cache\CacheManager;
use BitApps\WPKit\Cache\Cache;

// Configure a manager with multiple stores.
$manager = new CacheManager([
    'default' => 'transient',
    'prefix'  => 'myplugin_',
    'stores'  => [
        'file' => ['path' => '/var/cache/myplugin'],
        'object' => ['group' => 'myplugin_group'],
    ],
]);

// Access a named store (defaults to 'transient').
$cache = $manager->store('file');

// Cache operations.
$cache->put('user_123', $userData, 3600);
$user = $cache->get('user_123');

// Callback-based caching (compute and store on miss).
$data = $cache->remember('expensive_key', 7200, function () {
    return compute_expensive_data();
});

$cache->forget('user_123');
$cache->flush();

// Use the static facade (requires setManager first).
Cache::setManager($manager);
$data = Cache::remember('cached_posts', 3600, function () {
    return get_posts(['numberposts' => 10]);
});

// Available stores:
// - 'array': in-memory only, lost on shutdown.
// - 'transient': WordPress transients (data persists across requests).
// - 'object': WordPress object cache (non-persistent unless a drop-in is installed).
// - 'file': filesystem, requires configured path.

// Note: TransientStore::flush() is a documented no-op (WordPress limitation).
```

## Tests

```bash
composer test
composer coverage
```

PHPUnit tests are grouped by public feature under `tests/` to protect
compatibility while internals are refactored. The PHPDBG coverage command
enforces 100% executable-line coverage for selected HTTP hardening methods.
