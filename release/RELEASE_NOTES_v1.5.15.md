# Bridgistic v1.5.15

This is a compatibility patch for the hosted dashboard memory features.

## Fixed

- Added the authenticated `POST /bridgistic/v1/memory/scan` route used by the dashboard's **Run memory scan** action.
- Added the authenticated `POST /bridgistic/v1/memory/import-url` route used by public Markdown/text imports.
- Bounded outbound fetches by timeout, redirects and response size, and report optional source failures per source.
- Persisted WordPress site context alongside successfully fetched public context.

## Verification

- Full plugin suite: 734 checks across 13 suites passed.
- PHP syntax lint passed for the changed controller.
- Release metadata is synchronized at version 1.5.15.
