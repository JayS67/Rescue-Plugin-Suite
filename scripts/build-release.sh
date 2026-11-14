#!/usr/bin/env bash
set -euo pipefail
repo_dir=$(cd "$(dirname "$0")/.." && pwd)
version=${1:-}
version=${version#v}
output_dir=${2:-"$repo_dir/dist"}
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-+][A-Za-z0-9.-]+)?$ ]] || { echo 'Supply a release version, e.g. v15.1.2' >&2; exit 2; }
header_version=$(sed -nE 's/^[[:space:]*#@\/]*Version:[[:space:]]*([^[:space:]*]+).*/\1/p' "$repo_dir/plugin-ui-suite.php" | head -n1)
[[ "$header_version" == "$version" ]] || { echo 'Release version does not match the plugin header' >&2; exit 1; }
mkdir -p "$output_dir"
output_dir=$(cd "$output_dir" && pwd)
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/rescue-plugin-suite" "$output_dir"
cp "$repo_dir"/{plugin-ui-suite.php,readme.txt,changelog.md,uninstall.php} "$stage/rescue-plugin-suite/"
cp -a "$repo_dir/includes" "$repo_dir/assets" "$stage/rescue-plugin-suite/"
if [[ -d "$repo_dir/languages" ]]; then cp -a "$repo_dir/languages" "$stage/rescue-plugin-suite/"; fi
package="$output_dir/rescue-plugin-suite-v$version.zip"
[[ ! -e "$package" ]] || { echo 'Refusing to overwrite an existing release artifact' >&2; exit 1; }
(cd "$stage" && zip -qr "$package" rescue-plugin-suite)
sha256sum "$package"
