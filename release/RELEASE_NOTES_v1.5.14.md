# Bridgistic v1.5.14

Bridgistic v1.5.14 is a corrective release for plugin activation on PHP 8.x.

## Fixed

- Removed the UTF-8 BOM from the plugin bootstrap. `declare(strict_types=1)` is now the first PHP statement, so the plugin can activate without a critical error.
- Kept the customer-facing membership destination on `dashboard.bridgistic.app`; the owner-only license API remains on `admin.bridgistic.app`.

## Verification

- PHP syntax lint passes for the complete plugin tree.
- The full plugin test suite passes.
- The release builder now rejects PHP files containing a UTF-8 BOM.
