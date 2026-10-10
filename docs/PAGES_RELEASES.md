# GitHub Pages deployment

The public site at https://bridgistic.app/ is built from this repository's
`index.html` and `assets/` directory. This keeps the landing page, download
links, security information, and product documentation aligned with the
canonical Bridgistic repository.

## Deployment behavior

- Changes to the landing page, its brand assets, or the Pages workflow on
  `main` trigger a deployment.
- Each deployment resolves the latest published Bridgistic release and injects
  its version into the page. A source commit marker also ensures page edits are
  deployed even when the release version has not changed.
- The release workflow dispatches a Pages deployment at the immutable release
  tag, so a newly published release can be verified against its exact source.
- A scheduled run reconciles the public page with the current `main` source and
  latest release. Manual dispatch can select a specific release tag.
- The workflow stages only `index.html`, the two public brand assets, and the
  `bridgistic.app` CNAME file.

The page's download buttons use GitHub's stable `releases/latest/download/...`
URLs, so downloads continue to follow the newest published release.

## One-time repository setting

In repository settings, open **Pages → Build and deployment** and set Source to
**GitHub Actions**. The current repository setting is already configured for
GitHub Actions; this note is retained for maintainers setting up a new repo.

After deployment, verify the workflow environment URL and the public
https://bridgistic.app/ page. Confirm that the page shows the expected release
version and source commit.
