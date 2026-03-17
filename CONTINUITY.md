Goal (incl. success criteria):
- Build an autonomous translation system for `gmekka.devenia.com` that creates high-quality English (`en`) WPML versions from Norwegian (`no`) pages, one page at a time.
- Ensure plugin/runtime text on EN pages is genuinely multilingual (no hardcoded Norwegian leftovers).
- Success criteria:
  - `wpml/*` MCP abilities are available on `gmekka`.
  - A repeatable queue-driven workflow exists (pick next page -> create/update English translation -> QA -> mark done).
  - Loan calculator plugin emits EN labels on EN pages without runtime patch plugin.

Constraints/Assumptions:
- Use WordPress MCP proxy abilities for site operations.
- User wants Codex to execute; no manual operating burden on user.
- Keep language and documentation simple.
- `gmekka` uses Elementor and WPML.
- Current date context: 2026-02-12.

Key decisions:
- Pause old `/learn/` link-content lane; translation-autopilot is current priority.
- Keep `mcp-abilities-wpml` as translation-control bridge.
- Replace temporary EN runtime patching with real multilingual behavior in source plugin.
- Keep canonical plugin source in `plugins/wordpress-plugins/`.

State:
- Done: capability audit complete; WPML plugins confirmed active on `gmekka`.
- Done: queue-driven translation process exists under `translation-ops/`.
- Done: `wpml/*` abilities discoverable and callable on `gmekka`.
- Done: `mcp-abilities-wpml` `0.2.3` deployed with tuned untranslated-content detector.
- Done: all currently scanned published NO pages had EN published counterparts (`49/49`) in prior full scan.
- Done: loan calculator plugin refactored for multilingual frontend output and deployed live as `lanekalkulator/garderobemekka-lanekalkulator.php` `1.1.0`.
- Done: temporary plugin `gmekka-loan-i18n-fix` deactivated and deleted.
- Now: continue translation QA cycle; only patch true residual untranslated text.

Done:
- Verified active WPML stack on `gmekka`.
- Implemented and deployed `mcp-abilities-wpml` versions up to `0.2.3`.
- Added detector into `translation-ops/PROCESS.md`.
- Identified `Lånekalkulator` leakage as runtime/plugin-origin text.
- Created canonical plugin source folder:
  - `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/garderobemekka-lanekalkulator`
- Refactored plugin (`1.1.0`) to support language-aware labels (NO/EN) across:
  - main plugin defaults/settings handling
  - Elementor widget rendering
  - shortcode template rendering
  - frontend JS detail rows/fee notes
- Deployed updated plugin to existing live slug `lanekalkulator/...` via `plugins/upload-base64` with overwrite+activate.
- Verified active plugin version is `1.1.0` on `gmekka`.

Now:
- Use `wpml/detect-untranslated-content` as required QA gate before publish.
- Keep translation queue/log files updated in `translation-ops/state/`.
- Monitor EN pages for residual non-content runtime strings and patch source plugins (not runtime overrides).

Next:
- Re-run detector on previously flagged EN page `31080` and correct if still flagged.
- Spot-check additional EN pages using loan calculator widget for UI consistency.
- If needed, add per-language admin label fields for calculator plugin (NO/EN) beyond auto defaults.

Open questions (UNCONFIRMED if needed):
- UNCONFIRMED: Does WPML locale switching always execute in all rendering contexts (frontend, REST previews, cached fragments)?
- UNCONFIRMED: Policy for existing EN drafts with weak quality: overwrite directly or revise in place.
- UNCONFIRMED: Whether to auto-publish future translated pages immediately or keep a draft buffer.

Working set (files/ids/commands):
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/mcp-abilities-wpml/CONTINUITY.md`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/mcp-abilities-wpml/translation-ops/PROCESS.md`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/mcp-abilities-wpml/translation-ops/state/translation_queue.csv`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/mcp-abilities-wpml/translation-ops/state/translation_log.md`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/garderobemekka-lanekalkulator/garderobemekka-lanekalkulator.php`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/garderobemekka-lanekalkulator/includes/class-elementor-widget.php`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/garderobemekka-lanekalkulator/includes/template-calculator.php`
- `/media/bjorn/Stuff/Prosjekter/plugins/wordpress-plugins/garderobemekka-lanekalkulator/assets/js/calculator.js`
- Key page IDs: `37477` (NO source), `37942` (EN target), `31080` (flagged for QA)
