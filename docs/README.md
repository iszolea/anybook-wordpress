# Docs index

- `plugin-page-research-2026-11.md`: how the WordPress.org plugin page was researched and rebuilt (readme, banner, screenshots), 2026-11.
- `research/wp-plugin-pitch-research.md`: what competing booking plugins say, and our pitch.
- `research/wp-plugin-ranking-plan.md`: plan for ranking higher in WordPress.org search.
- `research/wp-plugin-free-trial-options.md`: free-trial options and what each changes in billing (no trial for now; revisit about 2026-12-10).

Release: `tools/upload-plugin.ps1` (runs `tools/release.sh --submit`). First-time SVN password: `tools/save-svn-password.py`.

Page-only update (text, tags, banner, screenshots; no new version): merge the change, update the main folder, then run `tools/update-page.ps1`. A code change still needs a version bump and `tools/upload-plugin.ps1`.
