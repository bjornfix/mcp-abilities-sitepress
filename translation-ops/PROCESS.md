# Gmekka Translation Autopilot

Goal: turn each Norwegian page into a good English WPML page, one by one.

## Simple flow (one page cycle)

1. Scan pages:
   - Run `wpml/list-page-translation-status`
   - Use `source_lang: no`, `target_lang: en`, `status: publish`
2. Pick next page:
   - Priority A: `has_translation = false`
   - Priority B: `target_status = draft`
3. Create/link English page if missing:
   - Run `wpml/ensure-page-translation`
4. Translate page content:
   - For Elementor pages: update text widgets with `elementor/update-element`
   - Update page title/slug with `content/update-page`
5. QA check:
   - Run `wpml/detect-untranslated-content` with:
     - `source_id: <NO page id>`
     - `target_lang: en`
     - defaults (`min_shared_terms_for_flag=2`, `min_target_count_for_flag=2`)
   - If known brand words trigger noise, pass `ignore_terms` (example: `["resurs"]`)
   - Continue only when `suspicious = false`, or mark `needs_review`
   - No obvious Norwegian strings in visible text
   - Heading hierarchy still makes sense
   - Numbers, prices, and legal terms still correct
6. Mark queue row:
   - `done` if ready to publish
   - `needs_review` if anything looks risky
7. Move to next page.

## Queue statuses

- `pending`: not started
- `in_progress`: currently being translated
- `needs_review`: translation done, but check needed
- `done`: ready
- `legacy`: skip for separate workflow

## Stop rules

- If one page fails QA twice, set `needs_review` and continue.
- If page is shortcode-heavy or non-Elementor legacy, set `legacy`.

## Quality target

- Natural English.
- No broken layout.
- No important Norwegian text left.
- CTA still clear and action-focused.
