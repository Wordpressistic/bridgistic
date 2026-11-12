# Bridgistic v1.5.13 test report

## Scope

This patch release changes the Premium Features customer membership URL and synchronizes release metadata. License API routing remains unchanged.

## Checks

| Check | Result |
| --- | --- |
| PHP test suite (`php wordpress-plugin/bridgistic/tests/run-all.php`) | Pass |
| Release package build (`node scripts/build-release.js`) | Pass |
| Customer link review | `dashboard.bridgistic.app/sign-in` |
| License authority review | `admin.bridgistic.app/api/v1/licenses` retained |

## Not run

An authenticated WordPress production activation was not performed in the release workspace. The release is intended to be installed through the normal WordPress plugin update flow.
