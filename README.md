# hazra_ev

Monorepo: PHP website + shared API, Flutter mobile client, design prototypes, product docs.

## Layout

| Folder | What it is |
| --- | --- |
| `website/` | PHP app + `website/api/` — one API serving both the website and the mobile app |
| `mobile_app/` | Flutter client |
| `html/`, `website/assets/` | HTML/CSS/JS prototypes, styles, animation |
| `docs/` | Product docs + `AGENT-BOARD.md`, the shared state |

## Branches

| Branch | Contents | Purpose |
| --- | --- | --- |
| `main` | Everything, at normal paths | Source of truth. All development lands here. |
| `website` | Contents of `website/`, flattened to repo root | Web deploy target — point the docroot at the repo root. |
| `mobile-app` | Contents of `mobile_app/`, flattened to repo root | App build target. |

`website` and `mobile-app` are orphan branches carved from `main`'s tree. They share no
history with `main` by design — they are deploy artifacts, not feature branches. Never
commit to them directly; commit to `main` and refresh:

```bash
git branch -f website    $(git commit-tree $(git rev-parse main:website)    -m "website: sync from main")
git branch -f mobile-app $(git commit-tree $(git rev-parse main:mobile_app) -m "mobile-app: sync from main")
git push --force-with-lease origin website mobile-app
```

## Not in git

`website/.env`, `website/vendor/`, `website/database/database.sqlite`, `website/storage/uploads/`,
`mobile_app/build/`, and the regenerable Flutter desktop targets. Copy `website/.env.example`
to `website/.env` and fill it in; run `composer install` in `website/` and `flutter pub get`
in `mobile_app/`.
