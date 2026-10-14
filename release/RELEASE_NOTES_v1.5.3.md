# Bridgistic v1.5.3

Security release. Fixes five findings from the October 2026 audit (see CHANGELOG):
SQL dry runs no longer execute, MySQL executable comments are rejected, sandbox protective
files are locked and sandbox PHP carries a direct-access guard, `php:execute` goes through
dry-run/approval, and unsigned query-string control parameters are rejected. MCP SDK updated
to 1.32.1; `npm audit` is clean.

Upgrade note: clients that put `dry_run`, `force` or `approval_id` in the URL query string, or
send any query parameter on POST/DELETE, now get HTTP 400 `bridgistic_unsigned_param`. The
official MCP server and cloud relay already send them in the signed body.
