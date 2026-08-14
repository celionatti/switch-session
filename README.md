# Switch Session (`switch/session`)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/celionatti/switch-session)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net)

**Switch Session** is an ultra-fast, standalone session, cookie, flash message, and CSRF protection package for the **Switch Framework** and any modern PHP application.

---

## ⚡ Key Features

- 🍪 **Fluent Cookie API (`Cookie`, `CookieJar`)**: Immutable cookie builder with RFC 6265 compliance (`SameSite`, `HttpOnly`, `Secure`, `Partitioned / CHIPS`).
- 🗄️ **Multi-Driver Storage**:
  - `file` (Default): Atomic file-based session store with automatic garbage collection.
  - `database`: High-concurrency session persistence using `switch/database` / PDO.
  - `cookie`: Encrypted client-side session payload.
  - `array`: Lightning-fast in-memory session driver for unit tests.
- ⚡ **Flash Messages Engine**: Automatic single-request flash state with `flash()`, `now()`, `keep()`, and `reflash()`.
- 🛡️ **CSRF Protection**: Time-constant secure token verification (`VerifyCsrfToken` PSR-15 middleware) and helper `{!! csrf_field() !!}`.
- 🚀 **PSR-15 Middleware**: Seamless integration with PSR-7 (`ServerRequestInterface`, `ResponseInterface`).

---

## 📦 Installation

```bash
composer require switch/session
```

---

## 🚀 Quick Usage

### 1. Basic Session Interaction

```php
use Switch\Session\Session;

// Put data (supports nested dot-notation)
Session::put('user.id', 42);
Session::put('theme', 'dark');

// Retrieve data
$userId = Session::get('user.id');
$theme = Session::get('theme', 'light');

// Check existence
if (Session::has('user.id')) {
    // ...
}

// Flash data for next request
Session::flash('status', 'Profile successfully updated!');

// Pull (get and delete)
$token = Session::pull('temp_token');

// Regenerate Session ID (on login/logout)
Session::regenerate();
```

---

### 2. Global Helpers

```php
// Get / Put via helper
session(['cart.total' => 199.99]);
$total = session('cart.total', 0.0);

// CSRF Helpers in views
echo csrf_field(); // <input type="hidden" name="_token" value="...">
$token = csrf_token();

// Cookie helper
cookie('theme', 'dark', 60); // Queued for 60 minutes
```

---

### 3. PSR-15 Middleware Pipeline

Add `StartSession` and `VerifyCsrfToken` to your application's middleware stack:

```php
use Switch\Session\Middleware\StartSession;
use Switch\Session\Middleware\VerifyCsrfToken;

$app->withMiddleware(function ($middleware) {
    $middleware->web([
        StartSession::class,
        VerifyCsrfToken::class,
    ]);
});
```

---

### 4. Flash Messages & UI Toasts

#### Setting Flash Messages (In Controllers or Code)
```php
// Fluent API
flash()->success('Profile updated successfully!', 'Success');
flash()->error('Payment verification failed.', 'Error');
flash()->warning('Your plan will expire in 3 days.');
flash()->info('Maintenance scheduled tonight.');

// Or quick helper
flash('success', 'Changes saved!');

// In Controllers
$this->flash('success', 'Profile updated!');
$this->flash()->error('Invalid credentials');
```

#### Displaying Flash Messages in Views
```html
<!-- Responsive floating glassmorphic toast deck -->
<flash />
<!-- Or with custom position -->
<flash mode="toast" position="top-right" />

<!-- Or inline alert banner cards -->
<flash mode="alert" />

<!-- Or blade directive -->
@flash
```

#### Automatic Switch Live SPA Reactivity
When using `switch/live`, calling `flash('success', '...')` during an SPA request automatically triggers client-side toast notifications without requiring a full page refresh!

---

### 5. Customizing Excluded CSRF Routes

```php
class CustomVerifyCsrfToken extends \Switch\Session\Middleware\VerifyCsrfToken
{
    protected array $except = [
        'stripe/webhook',
        'api/*',
    ];
}
```

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Session package is open-source software licensed under the [MIT license](LICENSE).
