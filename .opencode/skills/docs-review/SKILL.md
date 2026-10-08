---
name: docs-review
description: >-
  Use when the user asks to review, revise or audit the Omega MVC documentation
  in /home/morpheus/docs (revisione della documentazione, rivedi/revisiona i
  docs, check della documentazione Omega). Review every package .md for
  correctness, completeness and alignment with the real starter-app and
  framework; fix the "real ring" gaps.
---

# Docs Review — Omega MVC documentation in /home/morpheus/docs

## Scope and trigger

Activate ONLY when the user asks for a review / audit / revision of the Omega
MVC documentation. The docs to review live in **/home/morpheus/docs** (a
VitePress project: one `*.md` per package + `index.md` + `.vitepress/config.mts`).
The built static site output is **/home/morpheus/site**.

## Ground truth (the ONLY authoritative sources)

Always cross-check the docs against these three, never guess:

1. **Docs source (review target)** — `/home/morpheus/docs`
2. **Starter application (the "real ring")** — `/home/morpheus/omega`
   (`app/`, `bootstrap/app.php`, `config/`, `routes/`, `resources/views/`,
   `public/index.php`, `omega` CLI, `README.md`, `composer.json`)
3. **Framework (API source of truth)** — `vendor/omega-mvc/framework/`
   (namespace `Omega\*`, source under `src/`, tests under `tests/`)

## Non-negotiable conventions to enforce (the "real ring")

Every doc claim about the starter-app must match these verified facts:

- Controllers live in `app/Http/Controllers/`, expose `handle()` (NOT
  `index()`), type-hint dependencies, return a Response.
- Entry points: `public/index.php` (HTTP) and `omega` (console). Both load
  `vendor/autoload.php` + `bootstrap/app.php`, which builds the container and
  registers `App\Kernel\HttpKernel` / `App\Kernel\ConsoleKernel` / exception
  handler via `$app->set(...)`.
- Routing: `routes/web.php` with `Router::get(...)`; API-style via method
  attributes (`#[Get]`, `#[Middleware]`) + `Router::register([...])`;
  `routes/schedule.php` is cron.
- Views: **Templator** engine, files end `.template.php` under
  `resources/views/`, syntax `{% ... %}` (extend/section/yield/vite) — NOT
  Blade; `{{ $var }}` is plain PHP echo.
- `view()` helper: `view(string $path, array $data = [], array $option =
  ['status' => 200, 'header' => []]): Response`.
- `config/` plays a binding role: `app.php` keys (`debug`, `name`, `version`,
  `environment`) become container bindings (`app.debug`, etc.) via
  `loadConfig()`; paths are bindings (`path.app`, `path.config`, ...).
- Framework namespaces are `Omega\*`, application code is `App\*`.
- README quick-start commands must be consistent: `composer create-project
  omega-mvc/omega`, `npm install`, `npm run build`, `php omega serve`.

## Known drift and gaps to hunt (from the 6/10 · 5/10 audit)

Check every page against this watch-list and flag any occurrence:

1. Wrong path `app/Controller/ProfileController.php` — real is
   `app/Http/Controllers/`.
2. Wrong path `route/web.php` — real is `routes/web.php`.
3. Controller method written as `index` — real convention is `handle`.
4. Missing structure guide (bootstrap/app.php wiring, entry points, kernels
   extending framework classes, `handle()` convention, `view()` helper,
   `routes/{web,schedule}.php`, config roles).
5. Missing Getting Started / tutorial on the site (home page is only a slogan;
   onboarding currently lives only in README).
6. No doc↔starter linkage: each Package Manual should show "in the starter-app
   this is used like this".
7. No per-use-case map (route→Router, DB→Database, template→View,
   container→Container/Application).
8. Staleness dangers: code↔doc drift, undocumented `App\` conventions, version
   marker in generated docs, `make:*` generators that must match documented
   conventions.

## Review workflow

1. Identify which doc files are in scope (requested page(s) or the whole tree).
2. Read each `.md` and verify EVERY claim against the real code: PHP snippets,
   class names, method signatures, file paths, binding names, command names.
   Use grep/read on the framework `src/` and the starter-app — never trust the
   doc itself.
3. Check page structure follows the Package Manual template: `# Omega MVC —
   <Package> Package Manual`, overview, `## Usage` sections with PHP examples,
   an exceptions table, an honest `## Notes` section, and a `## Reference`
   (source files, dependencies, tests, license).
4. Cross-check `.vitepress/config.mts` nav + sidebar against the actual set of
   package files + `index.md`.
5. Check internal links: with `cleanUrls`, links point to existing pages/slugs.
6. Optionally validate the build: from `/home/morpheus/docs` run
   `npx vitepress build` (output dir is `../site`) — but only the bare build,
   do not install new tooling without asking.
7. Report findings; do NOT edit a doc unless the user explicitly asked for
   the edits.

## Output format

- One-line verdict per reviewed page.
- Concrete issues list: `file:line` → what is wrong vs ground truth → exact
  suggested correction.
- Gap checklist: which items of the known-drift list above remain unaddressed.

## Notes

- The docs live OUTSIDE the omega repo (`/home/morpheus/docs`); reading/writing
  there may trigger external-directory permission prompts — that is expected.
- Questions about scope or tone: ask instead of guessing.