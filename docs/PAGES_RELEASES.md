# Website deployment (bridgistic.app)

`https://bridgistic.app` is served by the **Cloudflare Pages** project `bridgistic-site`, which also serves `www.bridgistic.app`; the `www` address redirects to the apex domain. The site is deployed by hand. Merging to `main` deploys nothing.

> The GitHub Pages workflow (`.github/workflows/pages-release.yml`) is legacy and manual-only. It is not how the live site is deployed.

## What gets built

`scripts/build-site.sh [out-dir]` (default `dist/pages-site`) does the following:

- It picks the newest published release that ships `bridgistic-wordpress-plugin.zip`, checking `wordpressistic/bridgistic-claude-marketplace` first and then `Wordpressistic/bridgistic`. When both repos have the same version, the marketplace listing release wins.
- It injects that release's version, download base URL and release page into `index.html`, along with the site commit marker, the Paddle client-side token and environment, and the offer-status URL.
- It copies the brand assets, the legal pages (`terms`, `privacy`, `refund-policy`, `how-purchases-work`) and `assets/legal.css`, and writes `CNAME`.
- It runs `node scripts/build-seo.mjs`, which rewrites `sitemap.xml`, `sitemap.html`, `robots.txt`, `llms.txt` and `llms-full.txt` from those pages and copies them into the deploy folder. See [SEO.md](SEO.md).

Pages Functions in `functions/` (the `www` → apex redirect middleware and `/api/offer-status`) are bundled by wrangler from the repo root at deploy time.

## Deploy

```bash
# From a clean checkout of main. Node.js 22+ is required by wrangler.
PADDLE_CLIENT_TOKEN=live_... PADDLE_ENV=production scripts/build-site.sh dist/pages-site
CLOUDFLARE_API_TOKEN=<pages token> CLOUDFLARE_ACCOUNT_ID=<account id> \
  npx wrangler pages deploy dist/pages-site --project-name bridgistic-site --branch main \
  --commit-hash "$(git rev-parse HEAD)"
```

Before you deploy, note the current production deployment ID (Cloudflare dashboard → Pages → `bridgistic-site` → Deployments) so you can roll back with **Rollback to this deployment**.

## Verify after deploying

- `https://bridgistic.app/` returns 200. The HTML comment `site-commit=…; release=…` shows the expected commit and version.
- The version pill, the "Release notes for vX.Y.Z" link and every download button point at the expected release, and the files download.
- Pricing renders, `https://cdn.paddle.com/paddle/v2/paddle.js` loads, and `/api/offer-status` returns JSON.
- `/terms`, `/privacy`, `/refund-policy` and `/how-purchases-work` return 200.
- `/sitemap.xml`, `/sitemap.html`, `/robots.txt`, `/llms.txt` and `/llms-full.txt` return 200, and the footer links to `/sitemap.html`.

Local preview: `SITE_COMMIT=preview scripts/build-site.sh /tmp/site`, then serve `/tmp/site` with any static server. Pages Functions need `wrangler pages dev`.
