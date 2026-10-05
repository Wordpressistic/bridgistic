# Bridgistic branding

The source-of-truth brand files are the two user-approved PNGs in the repository
root assets directory:

- `assets/bridgistic-icon.png` — square icon for favicons, app icons, MCPB, and
  OpenAI `logo`/`composerIcon` fields.
- `assets/bridgistic-logo.png` — full Bridgistic lockup for the public site,
  documentation, previews, and social metadata.

## Distribution copies

The build keeps the icon in the places required by each distribution:

- `mcpb/icon.png` — Claude Desktop extension icon.
- `openai-plugin/assets/icon.png` — OpenAI Agent Plugin package icon.
- `wordpress-plugin/bridgistic/assets/brand/bridgistic-icon.png` — WordPress
  admin branding shipped inside the free plugin ZIP.

`npm run package` rebuilds the release archives from these tracked files. The
OpenAI package intentionally references the square icon for both `logo` and
`composerIcon`; the directory requires square artwork for those listing fields.
The full lockup is used by the website and documentation instead of being
forced into a square listing slot.

When changing the brand, update the root source assets first, copy the icon to
the distribution paths above, run `npm run package`, and then run
`node scripts/verify-packages.js` before publishing.
