# WPKit compatibility tests

The tests are grouped by public feature so internal architecture can change
without changing the behavior observed by consuming plugins.

PHPUnit discovers every `*Test.php` feature suite through `phpunit.xml`. Shared
WordPress doubles live in `bootstrap.php`, and `TestCase.php` resets their state
before every test.

The contract tests intentionally do not preserve known implementation defects,
including process-global router identity, implicit authorization, broken
automatic multipart boundaries, and static rewrite-rule loss. Those behaviors
must be corrected rather than treated as compatibility requirements.

Run the suite and package coverage report with:

```bash
composer validate --no-check-publish --no-interaction
composer lint
composer compat
composer rector
composer test
composer coverage
```

Development dependencies resolve against the advertised PHP 8.0 floor. CI runs
the same gates on PHP 8.0, 8.4, and 8.5.

The coverage command runs the PHPUnit suite under PHPDBG and retains the focused
100% executable-line gate for selected HTTP hardening methods. It is not
package-wide coverage.
