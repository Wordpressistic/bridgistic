# Bridgistic v1.5.2

Bridgistic 1.5.2 hardens the hosted MCP authorization flow and refreshes the public pricing presentation.

## Highlights

- Dynamic marketplace OAuth registration now tolerates the short Cloudflare KV propagation window before `/authorize`.
- Invalid or expired authorization requests receive a safe, readable 400 response instead of a Cloudflare Worker 1101 page.
- The public landing page now presents only Bridgistic Free and Bridgistic Pro.
- Internal Paddle and product identifiers are no longer exposed in the pricing copy.

The free WordPress plugin remains the supported public distribution. Premium WPistic/SaaS capabilities remain locked unless a valid Pro license is supplied through the separate licensing path.
