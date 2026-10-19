# Public site SEO

`bridgistic.app` is a static site. Crawl files are not edited by hand. `scripts/build-seo.mjs` reads the public HTML and rewrites them. `scripts/build-site.sh` runs that script on every site build, so a change to a page updates the sitemap and the LLM files in the same build.

Dates come from each file's last git commit, not from the clock. Running the script twice without a content change produces the same files.

## Files

| File | Who it is for |
|---|---|
| [sitemap.xml](https://bridgistic.app/sitemap.xml) | Google, Bing and other search crawlers. Real URLs only, no `#` fragments. |
| [sitemap.html](https://bridgistic.app/sitemap.html) | People. Linked from the homepage footer and from every legal page. |
| [robots.txt](https://bridgistic.app/robots.txt) | Crawl policy. Public pages are allowed. `/api/` is disallowed. |
| [llms.txt](https://bridgistic.app/llms.txt) | Short summary for AI platforms, in the [llms.txt](https://llmstxt.org/) shape. |
| [llms-full.txt](https://bridgistic.app/llms-full.txt) | Headings, the published prices and the FAQ, extracted from the pages. |

The homepage also exposes these in `<head>` (`rel="sitemap"`, `rel="describedby"`) and in JSON-LD (`Organization`, `WebSite`, `SoftwareApplication`, `FAQPage` with a speakable block).

## What the optimization covers

- **Search:** indexable pages, canonical URLs, XML sitemap, large image and snippet hints, one H1, and a publisher block with the legal name and address.
- **AI Overviews and answer engines (AEO / GEO):** the FAQ is real question-and-answer text, marked speakable, and copied into `llms-full.txt` so an overview can quote the page rather than guess.
- **AI platforms (AIO):** `robots.txt` allows Google-Extended, OAI-SearchBot, GPTBot, ClaudeBot, PerplexityBot, Applebot-Extended, Amazonbot and the other named answer-engine crawlers. `llms.txt` is the short entry point.
- **Intent (GIO):** each important question on the homepage has a direct answer. Prices in the LLM files are copied from the published page. If a generated file and the page ever disagree, the page wins.
- **E-E-A-T:** WordPressistic LLC, the Albuquerque address, support@wpistic.com, the GPL license and the public GitHub source are on the homepage and on the sitemap. The page does not claim an independent security audit.

## Commands

```bash
npm run seo
# or, as part of the deploy build:
scripts/build-site.sh dist/pages-site
```

Deploy stays manual. See [PAGES_RELEASES.md](PAGES_RELEASES.md). Nothing here starts a GitHub Actions workflow.
