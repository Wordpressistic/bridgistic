# Bridgistic v1.5.14 security checklist

- [x] Corrective release changes are limited to bootstrap encoding, release metadata and the customer dashboard destination.
- [x] Owner-only license API authority remains unchanged.
- [x] Existing nonces, capability checks, signed REST requests and output escaping are unchanged.
- [x] Release builder rejects UTF-8 BOMs in PHP files to prevent this activation regression from returning.
