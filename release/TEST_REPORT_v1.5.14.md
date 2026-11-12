# Bridgistic v1.5.14 test report

## Checks

- PHP syntax lint: pass for every PHP file in the plugin tree.
- UTF-8 BOM guard: pass; no PHP file begins with a BOM.
- Full PHP suite: pass.
- Release package build: pass.

## Regression coverage

- Plugin bootstrap loads with `declare(strict_types=1)` as the first PHP statement.
- Premium Features membership link targets `https://dashboard.bridgistic.app/sign-in`.
- License API authority remains `https://admin.bridgistic.app/api/v1/licenses`.
