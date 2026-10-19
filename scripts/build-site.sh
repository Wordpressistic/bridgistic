#!/usr/bin/env bash
# Build the bridgistic.app landing page into an output directory.
#
#   scripts/build-site.sh [out-dir]        (default: dist/pages-site)
#
# Env:
#   BRIDGISTIC_RELEASE_TAG  optional tag to pin (e.g. v1.5.2); blank = newest
#   SITE_COMMIT             commit to stamp (default: git HEAD)
#   GH_TOKEN                optional, raises the GitHub API rate limit
#   PADDLE_CLIENT_TOKEN     optional Paddle.js client-side token (live_... or test_...).
#                           Public by design, but kept out of git. Blank = checkout
#                           buttons fall back to https://pay.bridgistic.app/.
#   PADDLE_ENV              optional, "sandbox" or "production" (default)
#   OFFER_STATUS_URL        optional, default /api/offer-status (Pages Function)
#
# The plugin has been released from two repositories (this one and
# wordpressistic/bridgistic-claude-marketplace). The page must advertise the
# newest published release that actually ships the plugin zip, wherever it
# lives, so the download links never point at an older build.
set -euo pipefail

OUT="${1:-dist/pages-site}"
REPOS="${BRIDGISTIC_RELEASE_REPOS:-Wordpressistic/bridgistic wordpressistic/bridgistic-claude-marketplace}"
PIN="${BRIDGISTIC_RELEASE_TAG:-}"
SITE_COMMIT="${SITE_COMMIT:-$(git rev-parse HEAD)}"
ASSET="bridgistic-wordpress-plugin.zip"
PADDLE_CLIENT_TOKEN="${PADDLE_CLIENT_TOKEN:-}"
PADDLE_ENV="${PADDLE_ENV:-production}"
OFFER_STATUS_URL="${OFFER_STATUS_URL:-/api/offer-status}"
if [ -n "$PADDLE_CLIENT_TOKEN" ] && ! printf '%s' "$PADDLE_CLIENT_TOKEN" | grep -Eq '^(live|test)_[A-Za-z0-9]+$'; then
  echo "::error::PADDLE_CLIENT_TOKEN does not look like a Paddle client-side token (live_/test_)" >&2
  exit 1
fi
case "$PADDLE_ENV" in production|sandbox) ;; *) echo "::error::PADDLE_ENV must be production or sandbox" >&2; exit 1;; esac

api() {
  local auth=()
  [ -n "${GH_TOKEN:-}" ] && auth=(-H "Authorization: Bearer $GH_TOKEN")
  curl --fail --silent --show-error --location --connect-timeout 10 --max-time 30 \
    -H "Accept: application/vnd.github+json" "${auth[@]}" "https://api.github.com/$1"
}

best_tag="" best_repo=""
for repo in $REPOS; do
  if [ -n "$PIN" ]; then
    rel=$(api "repos/$repo/releases/tags/$PIN" 2>/dev/null || true)
  else
    rel=$(api "repos/$repo/releases/latest" 2>/dev/null || true)
  fi
  [ -n "$rel" ] || continue
  tag=$(printf '%s' "$rel" | jq -r '.tag_name // empty')
  has=$(printf '%s' "$rel" | jq -r --arg a "$ASSET" '[.assets[]?.name] | index($a) != null')
  printf '%s' "$tag" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$' || continue
  [ "$has" = "true" ] || continue
  if [ -z "$best_tag" ] || [ "$(printf '%s\n%s\n' "$best_tag" "$tag" | sort -V | tail -1)" = "$tag" ] && [ "$tag" != "$best_tag" ]; then
    best_tag="$tag" best_repo="$repo"
  fi
done

if [ -z "$best_tag" ]; then
  echo "::error::No published Bridgistic release with $ASSET was found." >&2
  exit 1
fi

VERSION="${best_tag#v}"
DOWNLOAD="https://github.com/$best_repo/releases/download/$best_tag"
PAGE="https://github.com/$best_repo/releases/tag/$best_tag"
echo "Using Bridgistic $best_tag from $best_repo"

rm -rf "$OUT"
mkdir -p "$OUT/assets"
sed -e "s#__BRIDGISTIC_RELEASE_VERSION__#$VERSION#g" \
    -e "s#__BRIDGISTIC_RELEASE_DOWNLOAD__#$DOWNLOAD#g" \
    -e "s#__BRIDGISTIC_RELEASE_PAGE__#$PAGE#g" \
    -e "s#__BRIDGISTIC_RELEASE_REPO__#$best_repo#g" \
    -e "s#__BRIDGISTIC_SITE_COMMIT__#$SITE_COMMIT#g" \
    -e "s#__BRIDGISTIC_PADDLE_CLIENT_TOKEN__#$PADDLE_CLIENT_TOKEN#g" \
    -e "s#__BRIDGISTIC_PADDLE_ENV__#$PADDLE_ENV#g" \
    -e "s#__BRIDGISTIC_OFFER_STATUS_URL__#$OFFER_STATUS_URL#g" \
    index.html > "$OUT/index.html"
if grep -Eq '__BRIDGISTIC_[A-Z_]+__' "$OUT/index.html"; then
  echo "::error::A site build marker was not replaced" >&2
  exit 1
fi
printf 'bridgistic.app\n' > "$OUT/CNAME"
cp assets/bridgistic-icon.png assets/bridgistic-logo.png "$OUT/assets/"
# Legal and ecosystem pages (static; served at /terms, /privacy, /refund-policy, /how-purchases-work).
cp terms.html privacy.html refund-policy.html how-purchases-work.html "$OUT/"
cp assets/legal.css "$OUT/assets/"

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  { echo "tag=$best_tag"; echo "version=$VERSION"; echo "repo=$best_repo"; } >> "$GITHUB_OUTPUT"
fi
