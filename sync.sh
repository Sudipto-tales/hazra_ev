#!/usr/bin/env bash
#
# sync.sh — one command to commit, integrate and publish this monorepo.
#
#   ./sync.sh              stage everything, build a commit message from the
#                          diff, land it on main, then fast-forward every
#                          deploy branch whose folder actually changed
#   ./sync.sh -n           dry run: show what would happen, touch nothing
#   ./sync.sh -m "msg"     use your own commit subject
#   ./sync.sh -t fix       override the inferred conventional-commit type
#   ./sync.sh -h           help
#
# You only ever work on main. Deploy branches are derived, never authored.
#
set -euo pipefail

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_ROOT"

REMOTE=origin
MAIN=main

# folder:branch — the whole routing table. Add a line to add a deploy target.
SPLITS=(
  "website:website"
  "mobile_app:mobile-app"
  "html:html"
)

# Branches that exist but hold nothing and are never rebuilt from a folder.
EMPTY_BRANCHES=(deploy)

scope_alias() {
  case $1 in
    mobile_app) echo mobile ;;
    .claude)    echo agents ;;
    .|"")       echo repo ;;
    *)          echo "$1" ;;
  esac
}

# ── output ───────────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
  C_B=$'\033[1;34m'; C_G=$'\033[1;32m'; C_Y=$'\033[1;33m'
  C_R=$'\033[1;31m'; C_D=$'\033[2m';    C_0=$'\033[0m'
else
  C_B=; C_G=; C_Y=; C_R=; C_D=; C_0=
fi
say()  { printf '%s>%s %s\n' "$C_B" "$C_0" "$*"; }
ok()   { printf '%sok%s %s\n' "$C_G" "$C_0" "$*"; }
warn() { printf '%s!%s %s\n' "$C_Y" "$C_0" "$*"; }
dim()  { printf '%s   %s%s\n' "$C_D" "$*" "$C_0"; }
die()  { printf '%sx%s %s\n' "$C_R" "$C_0" "$*" >&2; exit 1; }

# ── args ─────────────────────────────────────────────────────────────────
DRY=0; USER_MSG=""; USER_TYPE=""
while (( $# )); do
  case $1 in
    -n|--dry-run) DRY=1 ;;
    -m|--message) USER_MSG=${2:?-m needs a message}; shift ;;
    -t|--type)    USER_TYPE=${2:?-t needs a type};   shift ;;
    -h|--help)    sed -n '2,13p' "$0" | sed 's/^#\{1\} \{0,1\}//'; exit 0 ;;
    *)            die "unknown option: $1  (try -h)" ;;
  esac
  shift
done
run() { if (( DRY )); then dim "would: $*"; else "$@"; fi; }

# ── preflight ────────────────────────────────────────────────────────────
git rev-parse --git-dir >/dev/null 2>&1 || die "not a git repository"
git remote get-url "$REMOTE" >/dev/null 2>&1 || die "no remote named '$REMOTE'"

CURRENT=$(git symbolic-ref --quiet --short HEAD || true)
[[ -n $CURRENT ]] || die "detached HEAD — run 'git switch $MAIN' first"
if [[ $CURRENT != "$MAIN" ]]; then
  for pair in "${SPLITS[@]}"; do
    if [[ $CURRENT == "${pair#*:}" ]]; then
      die "you are on deploy branch '$CURRENT'.
  Deploy branches are generated, never authored. Run 'git switch $MAIN',
  redo the edit there, and rerun this script."
    fi
  done
  die "on branch '$CURRENT', expected '$MAIN'"
fi
[[ -e .git/MERGE_HEAD ]] && die "a merge is in progress — resolve it, 'git commit', then rerun"

# ── commit message, built from the diff (no model, no network) ───────────
# In dry-run nothing is staged yet, so read the worktree instead of the index.
collect_changes() {
  if (( DRY )); then
    git -c core.quotepath=false status --porcelain --untracked-files=all \
    | while IFS= read -r line; do
        xy=${line:0:2}; p=${line:3}; p=${p##* -> }
        if [[ $xy == '??' ]]; then printf 'A\t%s\n' "$p"
        else c=${xy//[[:space:]]/}; printf '%s\t%s\n' "${c:0:1}" "$p"; fi
      done
  else
    git -c core.quotepath=false diff --cached --name-status --find-renames
  fi
}

longest_common_dir() {
  local prefix="" first=1 p d
  for p in "$@"; do
    d=${p%/*}; [[ $d == "$p" ]] && d=""
    if (( first )); then prefix=$d; first=0; continue; fi
    while [[ -n $prefix && "$d/" != "$prefix/"* ]]; do
      if [[ $prefix == */* ]]; then prefix=${prefix%/*}; else prefix=""; fi
    done
  done
  printf '%s' "$prefix"
}

build_message() {
  local -a lines=() paths=()
  mapfile -t lines < <(collect_changes)
  (( ${#lines[@]} )) || return 1

  local n_add=0 n_del=0 n_mod=0 l st p
  for l in "${lines[@]}"; do
    st=${l%%$'\t'*}; p=${l#*$'\t'}; p=${p##*$'\t'}
    paths+=("$p")
    case ${st:0:1} in
      A) n_add=$((n_add+1)) ;;
      D) n_del=$((n_del+1)) ;;
      *) n_mod=$((n_mod+1)) ;;
    esac
  done
  local n=${#paths[@]}

  # scope: the single top-level folder everything sits under, else "repo"
  local -A seen=(); local top
  for p in "${paths[@]}"; do
    top=${p%%/*}; [[ $top == "$p" ]] && top="."
    seen["$top"]=1
  done
  local scope onlytop=""
  if (( ${#seen[@]} == 1 )); then
    onlytop="${!seen[*]}"
    scope=$(scope_alias "$onlytop")
    [[ $onlytop == "." ]] && onlytop=""
  else
    scope=repo
  fi

  # type
  local type all_docs=1 all_test=1
  for p in "${paths[@]}"; do
    [[ $p == docs/* || $p == */docs/* || $p == *.md ]] || all_docs=0
    [[ $p == test/* || $p == */test/* || $p == *_test.* ]] || all_test=0
  done
  if   [[ -n $USER_TYPE ]]; then type=$USER_TYPE
  elif (( all_docs ));      then type=docs
  elif (( all_test ));      then type=test
  elif (( n_add > 0 ));     then type=feat
  else                           type=chore
  fi

  # subject
  local subject
  if [[ -n $USER_MSG ]]; then
    subject=$USER_MSG
  else
    local verb=update
    (( n_add > 0 && n_mod == 0 && n_del == 0 )) && verb=add
    (( n_del > 0 && n_mod == 0 && n_add == 0 )) && verb=remove
    local common rel
    common=$(longest_common_dir "${paths[@]}")
    rel=$common
    if [[ -n $onlytop && ( $rel == "$onlytop" || $rel == "$onlytop"/* ) ]]; then
      rel=${rel#"$onlytop"}; rel=${rel#/}
    fi
    if (( n == 1 )); then
      local only=${paths[0]}
      [[ -n $onlytop ]] && only=${only#"$onlytop"/}
      subject="$verb $only"
    elif [[ -n $rel ]]; then
      subject="$verb $n files in $rel"
    else
      subject="$verb $n files"
    fi
  fi

  local head
  if [[ $scope == "$type" ]]; then head="$type: $subject"
  else                             head="$type($scope): $subject"; fi
  (( ${#head} > 72 )) && head="${head:0:69}..."

  printf '%s\n\n' "$head"
  local stat
  if (( DRY )); then stat=$(git diff --shortstat 2>/dev/null || true)
  else               stat=$(git diff --cached --shortstat 2>/dev/null || true); fi
  [[ -n $stat ]] && printf '%s\n\n' "$(printf '%s' "$stat" | sed 's/^ *//')"
  if (( n <= 50 )); then
    printf '%s\n' "${lines[@]}"
  else
    printf '%s\n' "${lines[@]:0:50}"
    printf '... and %d more files\n' "$((n-50))"
  fi
}

show_message() {
  printf '%s+- commit -----------------------------%s\n' "$C_D" "$C_0"
  printf '%s\n' "$1" | sed "s/^/${C_D}|${C_0} /"
  printf '%s+--------------------------------------%s\n' "$C_D" "$C_0"
}

# ── 1. fetch ─────────────────────────────────────────────────────────────
OFFLINE=0
say "fetching $REMOTE"
git fetch --prune --quiet "$REMOTE" 2>/dev/null \
  || { OFFLINE=1; warn "fetch failed — working offline, nothing will be pushed"; }

# ── 2. commit whatever changed ───────────────────────────────────────────
if [[ -n $(git status --porcelain) ]]; then
  say "staging changes"
  (( DRY )) || git add -A
  if (( DRY )) || [[ -n $(git diff --cached --name-only) ]]; then
    MSG=$(build_message) || die "could not read changes"
    show_message "$MSG"
    if (( DRY )); then
      dim "would: git add -A && git commit"
    else
      git commit --quiet -m "$MSG"
      ok "committed $(git rev-parse --short HEAD) on $MAIN"
    fi
  else
    ok "nothing staged — every change is gitignored"
  fi
else
  ok "working tree clean"
fi

# ── 3. integrate the remote: fast-forward, merge only if genuinely needed ─
if (( ! OFFLINE )) && git rev-parse -q --verify "refs/remotes/$REMOTE/$MAIN" >/dev/null; then
  if git merge-base --is-ancestor "$REMOTE/$MAIN" HEAD; then
    ok "$MAIN already contains $REMOTE/$MAIN"
  elif git merge --ff-only --quiet "$REMOTE/$MAIN" 2>/dev/null; then
    ok "fast-forwarded $MAIN from $REMOTE"
  else
    warn "$MAIN and $REMOTE/$MAIN diverged — merging"
    if (( DRY )); then
      dim "would: git merge $REMOTE/$MAIN"
    elif git merge --no-edit --quiet "$REMOTE/$MAIN"; then
      ok "merged $REMOTE/$MAIN"
    else
      git merge --abort 2>/dev/null || true
      die "merge conflicts. Run 'git merge $REMOTE/$MAIN', fix them, 'git commit',
  then rerun this script. Nothing was pushed."
    fi
  fi
fi

# ── 4. rebuild deploy branches whose folder changed ──────────────────────
# Each branch gets ONE new commit whose tree IS the folder and whose parent is
# the current remote tip. Every push is therefore a fast-forward — no force,
# ever — and it costs one object regardless of how long the history is.
PUSH=()
if ! git rev-parse -q --verify "refs/remotes/$REMOTE/$MAIN" >/dev/null; then
  PUSH+=("$MAIN")
elif ! git merge-base --is-ancestor HEAD "refs/remotes/$REMOTE/$MAIN"; then
  PUSH+=("$MAIN")
fi

for pair in "${SPLITS[@]}"; do
  folder=${pair%%:*}; branch=${pair#*:}
  if ! tree=$(git rev-parse -q --verify "$MAIN:$folder" 2>/dev/null); then
    warn "$folder/ holds nothing tracked on $MAIN — skipping branch '$branch'"
    continue
  fi
  on_remote=0
  git rev-parse -q --verify "refs/remotes/$REMOTE/$branch" >/dev/null && on_remote=1
  parent=""
  if (( on_remote )); then
    parent=$(git rev-parse "refs/remotes/$REMOTE/$branch")
  elif git rev-parse -q --verify "refs/heads/$branch" >/dev/null; then
    parent=$(git rev-parse "refs/heads/$branch")
  fi

  # folder unchanged since that tip? then there is nothing to rebuild
  if [[ -n $parent && $(git rev-parse "$parent^{tree}") == "$tree" ]]; then
    run git update-ref "refs/heads/$branch" "$parent"
    if (( on_remote )); then
      ok "$branch up to date"
    else
      ok "$branch up to date, not yet on $REMOTE"
      PUSH+=("$branch")
    fi
    continue
  fi

  msg="$branch: sync $folder/ from $MAIN@$(git rev-parse --short "$MAIN")"
  if (( DRY )); then
    dim "would: rebuild $branch from $folder/"
  else
    if [[ -n $parent ]]; then new=$(git commit-tree "$tree" -p "$parent" -m "$msg")
    else                      new=$(git commit-tree "$tree" -m "$msg"); fi
    git update-ref "refs/heads/$branch" "$new"
    ok "rebuilt $branch $(git rev-parse --short "$new") from $folder/"
  fi
  PUSH+=("$branch")
done

# ── 5. empty branches: create once, then never touch ─────────────────────
for branch in "${EMPTY_BRANCHES[@]}"; do
  git rev-parse -q --verify "refs/remotes/$REMOTE/$branch" >/dev/null && continue
  if ! git rev-parse -q --verify "refs/heads/$branch" >/dev/null; then
    if (( DRY )); then
      dim "would: create empty branch $branch"
    else
      empty=$(git hash-object -t tree /dev/null)
      c=$(git commit-tree "$empty" -m "$branch: empty branch, reserved as a deployment target")
      git update-ref "refs/heads/$branch" "$c"
      ok "created empty branch $branch"
    fi
  fi
  PUSH+=("$branch")
done

# ── 6. one atomic push for everything ────────────────────────────────────
if (( OFFLINE )); then
  warn "offline — rerun with a network to publish"
  exit 0
fi
if (( ${#PUSH[@]} == 0 )); then
  ok "everything already published — nothing to push"
  exit 0
fi
mapfile -t PUSH < <(printf '%s\n' "${PUSH[@]}" | awk '!seen[$0]++')
say "pushing: ${PUSH[*]}"
if (( DRY )); then
  dim "would: git push --atomic -u $REMOTE ${PUSH[*]}"
else
  git push --atomic -u "$REMOTE" "${PUSH[@]}"
  ok "published ${#PUSH[@]} branch(es): ${PUSH[*]}"
fi
