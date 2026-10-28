#!/usr/bin/env bash
# Propose a semantic version, then create and push an annotated release tag.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
fail() { echo "error: $*" >&2; exit 1; }
clean_main() {
  [[ "$(git branch --show-current)" == main ]] || fail 'releases must start from main'
  [[ -z "$(git status --porcelain)" ]] || fail 'commit or stash working-tree changes first'
}
valid_version() { [[ "$1" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; }
dry_run=false
if [[ $# == 1 && "$1" == --dry-run ]]; then dry_run=true; elif [[ $# != 0 ]]; then fail 'usage: release.sh [--dry-run]'; fi
clean_main
command -v gh >/dev/null || fail 'GitHub CLI is required'
origin="$(git config --get remote.origin.url)"
case "$origin" in
  git@github.com:*) repository="${origin#git@github.com:}" ;;
  https://github.com/*) repository="${origin#https://github.com/}" ;;
  ssh://git@github.com/*) repository="${origin#ssh://git@github.com/}" ;;
  *) fail 'origin must be a GitHub repository URL' ;;
esac
repository="${repository%.git}"
[[ "$repository" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'invalid origin repository'
repo="$(gh repo view "$repository" --json nameWithOwner --jq .nameWithOwner)"
head="$(git rev-parse HEAD)"
remote_head="$(gh api "repos/$repo/git/ref/heads/main" --jq .object.sha)"
[[ "$head" == "$remote_head" ]] || fail 'local main must match published origin/main; push or update main first'
remote_tags="$(gh api "repos/$repo/git/matching-refs/tags/v" --paginate --jq '.[].ref')"
versions="$(printf '%s\n' "$remote_tags" | sed 's|^refs/tags/||')"
latest=''
while IFS= read -r tag; do
  if valid_version "$tag"; then
    candidate="${tag#v}"
    if [[ -z "$latest" ]]; then latest="$candidate"; else latest="$(printf '%s\n%s\n' "$latest" "$candidate" | sort -t. -k1,1n -k2,2n -k3,3n | tail -n1)"; fi
  fi
done <<< "$versions"
proposed=v0.1.0
if [[ -n "$latest" ]]; then
  IFS=. read -r major minor patch <<< "$latest"
  proposed="v${major}.${minor}.$((patch + 1))"
fi
version="${VERSION:-}"
if [[ "$version" == dev ]]; then version=''; fi
if [[ -z "$version" ]]; then
  if "$dry_run"; then version="$proposed"; else
    printf 'Version to release [%s]: ' "$proposed"
    read -r version || fail 'no version supplied'
    version="${version:-$proposed}"
  fi
fi
[[ "$version" == v* ]] || version="v$version"
valid_version "$version" || fail 'version must be vMAJOR.MINOR.PATCH without leading zeroes'
if [[ -n "$latest" ]]; then
  newest="$(printf '%s\n%s\n' "$latest" "${version#v}" | sort -t. -k1,1n -k2,2n -k3,3n | tail -n1)"
  [[ "${version#v}" == "$newest" && "${version#v}" != "$latest" ]] || fail "version must be newer than v$latest"
fi
if git show-ref --verify --quiet "refs/tags/$version"; then fail "local tag $version already exists"; fi
printf 'Release %s from %s (%s)\n' "$version" "$repo" "$head"
if "$dry_run"; then echo 'Dry run: no tag or push performed'; exit 0; fi
printf 'Create annotated tag and push it to origin? [y/N] '
read -r confirm || fail 'no confirmation supplied'
case "$confirm" in y|Y|yes|YES) ;; *) echo 'Aborted'; exit 0 ;; esac
clean_main
[[ "$(git rev-parse HEAD)" == "$head" ]] || fail 'HEAD changed during confirmation'
remote_head="$(gh api "repos/$repo/git/ref/heads/main" --jq .object.sha)"
[[ "$head" == "$remote_head" ]] || fail 'published main changed during confirmation'
git tag -a "$version" "$head" -m "Release $version"
if ! git push origin "refs/tags/$version"; then
  fail "push failed; tag $version remains local, retry with: git push origin refs/tags/$version"
fi
printf 'Pushed %s. Follow the Release workflow in %s.\n' "$version" "$repo"
