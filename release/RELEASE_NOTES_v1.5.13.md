# Bridgistic v1.5.13

Bridgistic v1.5.13 corrects the customer-facing membership destination in the WordPress admin.

## Included

- Premium Features now links customers to `https://dashboard.bridgistic.app/sign-in`.
- The owner-only `https://admin.bridgistic.app` host remains the license and account-service authority; it is not exposed as the customer dashboard destination.
- WordPress plugin release metadata is synchronized at version 1.5.13.

## Verification

- PHP test suite passed from `wordpress-plugin/bridgistic/tests/run-all.php`.
- The production plugin ZIP was built with `node scripts/build-release.js` from a clean checkout.

## Compatibility

This is a backward-compatible WordPress plugin patch release for WordPress 6.4+ and PHP 8.0+.
