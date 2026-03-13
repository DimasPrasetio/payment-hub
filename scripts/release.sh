#!/usr/bin/env bash
set -euo pipefail

version="${1:-}"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ -z "$version" ]]; then
  echo "Usage: ./scripts/release.sh <version>"
  exit 1
fi

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
  echo "Version must follow Semantic Versioning, for example 1.0.1 or 1.1.0-rc.1"
  exit 1
fi

echo "Running production frontend build..."
(cd "$root" && npm run build)

printf '%s' "$version" > "$root/VERSION"

echo "Updated VERSION to $version"
echo "Next steps:"
echo "1. Update CHANGELOG.md"
echo "2. Commit the release changes, including public/build if it changed"
echo "3. Create the tag: git tag -a v$version -m \"Release v$version\""
echo "4. Push branch and tag: git push origin main --follow-tags"
