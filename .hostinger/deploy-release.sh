#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly TARGET_SHA="${1:-}"
if [[ ! "$TARGET_SHA" =~ ^[0-9a-f]{40}$ ]]; then
  echo "A full lowercase Git commit SHA is required." >&2
  exit 64
fi

readonly BASE=/home/u164418876/apps/wordle-hit-today
readonly CURRENT="$BASE/current"
readonly RELEASE_ID="git-${TARGET_SHA:0:12}"
readonly RELEASE="$BASE/releases/$RELEASE_ID"
readonly STAGE="$BASE/releases/.stage-$RELEASE_ID"
readonly CLONE="$BASE/incoming/$RELEASE_ID"
readonly LOCK="$BASE/.deploy-$RELEASE_ID.lock"
readonly PHP=/opt/alt/php84/usr/bin/php
readonly REPOSITORY=https://github.com/ArsaalanBaloch/wordle-hit-today.git
readonly PUBLIC_ROOT=/home/u164418876/domains/wordlehintoday.com/public_html

if [[ "$(readlink -f "$CURRENT" 2>/dev/null || true)" == "$RELEASE" ]]; then
  echo "ALREADY_DEPLOYED:$TARGET_SHA"
  exit 0
fi

if ! mkdir "$LOCK" 2>/dev/null; then
  echo "DEPLOY_BUSY:$RELEASE_ID"
  exit 0
fi
trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT

if [[ -e "$RELEASE" ]]; then
  echo "Release exists but is not current: $RELEASE" >&2
  exit 65
fi

rm -rf -- "$STAGE" "$CLONE"
mkdir -p "$CLONE" "$STAGE/assets"

git -C "$CLONE" init --quiet
git -C "$CLONE" remote add origin "$REPOSITORY"
git -C "$CLONE" fetch --quiet --depth 1 origin "$TARGET_SHA"
git -C "$CLONE" checkout --quiet --detach FETCH_HEAD

readonly ACTUAL_SHA="$(git -C "$CLONE" rev-parse HEAD)"
if [[ "$ACTUAL_SHA" != "$TARGET_SHA" ]]; then
  echo "Commit verification failed: $ACTUAL_SHA" >&2
  exit 66
fi

cp -a \
  "$CLONE/bin" \
  "$CLONE/config" \
  "$CLONE/resources" \
  "$CLONE/schemas" \
  "$CLONE/src" \
  "$CLONE/templates" \
  "$STAGE/"
cp -a \
  "$CLONE/composer.json" \
  "$CLONE/composer.lock" \
  "$CLONE/package-lock.json" \
  "$STAGE/"
cp -a "$CLONE/assets/brand" "$STAGE/assets/"
cp -a "$CURRENT/assets/dist" "$STAGE/assets/"
cp -a "$CURRENT/vendor" "$STAGE/"
cp -a "$CURRENT/config/site.local.json" "$STAGE/config/site.local.json"

# Browser assets are reviewed and committed, while the production vocabulary
# remains private on the host. Replace the portable assets, retain only the
# current production vocabulary, and merge its two manifest fields.
rm -f -- "$STAGE/assets/dist/"*.css "$STAGE/assets/dist/"*.js
rm -rf -- "$STAGE/assets/dist/fonts" "$STAGE/assets/dist/licenses"
cp -a "$CLONE/assets/dist/"*.css "$CLONE/assets/dist/"*.js "$STAGE/assets/dist/"
cp -a "$CLONE/assets/dist/fonts" "$CLONE/assets/dist/licenses" "$STAGE/assets/dist/"
"$PHP" "$CLONE/.hostinger/merge-asset-manifests.php" \
  "$STAGE/vendor/autoload.php" \
  "$CLONE/assets/dist/manifest.json" \
  "$CURRENT/assets/dist/manifest.json" \
  "$STAGE/assets/dist/manifest.json"

"$PHP" "$STAGE/bin/wordle" doctor \
  --config="$STAGE/config/site.local.json" \
  --mode=production
"$PHP" "$CURRENT/bin/wordle" backup \
  --config="$CURRENT/config/site.local.json" \
  --mode=production
"$PHP" "$STAGE/bin/wordle" build \
  --config="$STAGE/config/site.local.json" \
  --mode=production

grep -Fq 'Spoiler-Free Clues &amp; Answer' "$PUBLIC_ROOT/index.html"
test -s "$PUBLIC_ROOT/favicon.ico"
test -s "$PUBLIC_ROOT/favicon-48x48.png"
test -s "$PUBLIC_ROOT/apple-touch-icon.png"

"$PHP" "$STAGE/bin/wordle" reconcile \
  --config="$STAGE/config/site.local.json" \
  --mode=production

printf '%s\n' "$TARGET_SHA" >"$STAGE/.release-sha"
mv "$STAGE" "$RELEASE"

readonly NEXT_LINK="$BASE/.current-$RELEASE_ID"
rm -f -- "$NEXT_LINK"
ln -s "$RELEASE" "$NEXT_LINK"
mv -Tf "$NEXT_LINK" "$CURRENT"

rm -rf -- "$CLONE"
echo "DEPLOYED:$TARGET_SHA"
