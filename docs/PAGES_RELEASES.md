# Release-backed Bridgistic site

The public site at https://bridgistic.app/ should represent a published
release, not an arbitrary commit on main.

The pages-release.yml workflow checks out the exact GitHub release tag,
injects that tag's version into the landing page, stages only the public
landing page and its branding assets, and deploys the result to the
github-pages environment. The download links continue to use GitHub's
stable releases/latest/download/... URLs, so they move to the newest
published release automatically.

## One-time repository setting

In the repository settings, open Pages → Build and deployment and change
Source from branch deployment to **GitHub Actions**. This is a repository
setting and cannot be safely inferred or changed by a source-code commit.
After that one-time change, publishing a release triggers the workflow.

Use the manual workflow dispatch only to repair or redeploy a known release
tag. Verify both the workflow environment URL and the public
https://bridgistic.app/ page after deployment.
