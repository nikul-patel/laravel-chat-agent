# 📦 Repo Sprints & Checklists (laravel-chat-agent)

**Goal:** convert the high‑level roadmap into actionable, reviewable, and testable work items you can drop into issues/PRs. Copy sections as separate GitHub issues, or keep this as `docs/ROADMAP.md` and link to it from your README.

---

## Sprint 1 — Widget Core (Embeddable + Streaming)

### Issue Set (Ready to Paste into GitHub)

Each item below is formatted so you can copy/paste directly into a new GitHub issue.

---

#### **[Sprint 1.1] Web Component Shell**

**Summary:** Build `<soha-chat>` as a Web Component with core attributes and methods.

**Why:** Enable drop‑in use across Blade, React, and static HTML.

**Scope:**

* Create `resources/js/widget/SohaChat.ts`.
* Build to `public/vendor/soha/soha.js`.
* Attributes: `data-endpoint`, `data-history`, `data-config`, `transport`, `lang`, `theme`, `position`, `history-limit`.
* Methods: `.open()`, `.close()`, `.reset()`, `.focus()`.
* Persist UI state in `localStorage`.

**Acceptance Criteria:**

* Works in Blade + React + static HTML.
* Widget renders and can send/receive messages.

**Test Plan:**

* Manual test on `/welcome` route.
* Playwright: mount demo page, send message, expect response.

---

#### **[Sprint 1.2] Theming & CSS Variables**

**Summary:** Add CSS variables and dark/light theming.

**Why:** Enable host apps to brand and theme widget easily.

**Scope:**

* Variables: `--soha-bg`, `--soha-fg`, `--soha-accent`, `--soha-radius`, `--soha-shadow`.
* Dark mode via `prefers-color-scheme`.
* Document in `docs/widget-theming.md`.

**Acceptance Criteria:**

* Host CSS overrides variables.
* Dark/light mode toggle works.

**Test Plan:**

* Visual: theme swap.
* Playwright screenshot diff.

---

#### **[Sprint 1.3] i18n Support**

**Summary:** Add language bundles and switch by `lang` attribute.

**Why:** Support global usage.

**Scope:**

* JSON bundles in `public/vendor/soha/i18n/{en,hi,gu}.json`.
* Loader with fallback to `en`.
* Translate core strings.

**Acceptance Criteria:**

* Switching `lang` updates widget text.

**Test Plan:**

* Manual: toggle `lang`.
* Unit: fallback check.

---

#### **[Sprint 1.4] Transport: REST + SSE**

**Summary:** Implement SSE streaming endpoint + client support.

**Why:** Token‑by‑token responses improve UX.

**Scope:**

* Add `GET /chat-agent/stream` SSE.
* Client renders tokens with cursor.
* Cancel support + retries.

**Acceptance Criteria:**

* Streaming responses visible.

**Test Plan:**

* Playwright: send + cancel mid‑stream.
* Verify partial message preserved.

---

#### **[Sprint 1.5] Result Renderers**

**Summary:** Add UI renderers for JSON, tables, and code blocks.

**Why:** Tool responses need rich display.

**Scope:**

* Table renderer: virtualized rows + CSV export.
* JSON viewer: collapsible + copy.
* Code: syntax highlight + copy.

**Acceptance Criteria:**

* Payloads render in correct format.

**Test Plan:**

* Unit: renderer selection.
* Manual: demo sample payloads.

---

#### **[Sprint 1.6] Slash Commands & Shortcuts**

**Summary:** Add `/reset`, `/schema`, `/help` commands + keyboard shortcuts.

**Why:** Power users expect commands and quick open.

**Scope:**

* Commands UI with `/` autocomplete.
* `Ctrl/Cmd+K` opens command palette.

**Acceptance Criteria:**

* `/help` lists commands.
* `Esc` closes.

**Test Plan:**

* Playwright: type `/` and verify suggestions.

---

## ✅ Next Step

* Copy each **Sprint 1.x** issue into GitHub and tag with labels: `sprint:1`, `type:feature`.
* Once Sprint 1 backlog is created, repeat process for Sprint 2 and 3.
