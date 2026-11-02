# User-manual media

Drop captured assets here. Filenames must match the `data-media` attribute
on each `.manual-media` slot in the manual pages. Two pages share this folder:

- `html/employee-manual.html` — the field app (phone-frame slots).
- `html/admin-manual.html` — the web admin panel (desktop/browser-frame slots).

## Folders

- `img/`  — screenshots. PNG, light + dark where noted.
  - Employee slots use the **phone frame** (9:19.5): e.g. `role-select.png`.
  - Admin slots use the **desktop frame** (16:10) and are prefixed `admin-`:
    e.g. `admin-dashboard.png`.
- `video/` — short task walkthroughs (16:9). WebM/MP4 + a matching `-poster.jpg`.
  Admin walkthroughs are prefixed `admin-` (e.g. `admin-live-map.mp4`).

To list every slot across both pages, grep for `data-media=`:

```
grep -o 'data-media="[^"]*"' html/employee-manual.html html/admin-manual.html
```

See the design inbox notes `design-*-employee-manual.md` and
`design-*-admin-manual.md` for the full slot lists.
