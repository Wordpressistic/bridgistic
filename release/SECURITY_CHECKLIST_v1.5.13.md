# Bridgistic v1.5.13 security checklist

- [x] Customer-facing URL uses HTTPS and the canonical dashboard host.
- [x] The owner-only admin host remains restricted to licensing/account authority and is not used as the customer membership destination.
- [x] External link retains `target="_blank"` with `rel="noopener noreferrer"`.
- [x] Existing license API endpoint was not changed.
- [x] No credentials, tokens or secrets were added.
- [x] Existing PHP test suite and release build are required before tagging.
