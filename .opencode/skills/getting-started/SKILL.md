---
name: getting-started
description: >-
  Use when the user wants to create or keep updated the "Getting Started"
  section of the Omega MVC docs in /home/morpheus/docs (getting started, guida
  introduttiva, quick start, tutorial, primi passi). Generate it from scratch
  or refresh it so every path, convention and command matches the current
  starter app.
---

# Getting Started — Omega MVC docs section

## Scope and trigger

Activate when the user asks to create, write, review or update the **Getting
Started** section of the Omega MVC documentation. The docs live in
**/home/morpheus/docs** (VitePress project: one `*.md` per package + `index.md`
+ `.vitepress/config.mts`); the built site output is `/home/morpheus/site`.
The user has announced he will create the section layout — when that happens,
this skill keeps the content accurate and in sync with the real starter app.

## Ground truth (the ONLY authoritative sources)

1. **Starter application** — `/home/morpheus/omega`: `app/`, `bootstrap/app.php`,
   `config/`, `routes/`, `resources/views/`, `public/`, `omega` CLI,
   `README.md`, `composer.json`.
2. **Framework** — `vendor/omega-mvc/framework/` (namespace `Omega\*`,
   source under `src/`).
3. **Docs source** — `/home/morpheus/docs`.

Always cross-check snippets against these; never guess.

## Canonical outline (use for a fresh section; adapt to the user's chosen layout)

1. **Prerequisites** — PHP >= 8.4, Composer, Node.js + npm (Vite build).
2. **Quick Start** — `composer create-project omega-mvc/omega` → `npm install`
   → `npm run build` (emits hashed assets to `public/build/`) →
   `php omega serve`; open the app and check the home page.
3. **Project structure** — `app/` (Http/Controllers with `handle()`, Kernel,
   Middlewares, Models, Providers), `bootstrap/app.php` (Env::load + new
   Application + HttpKernel/ConsoleKernel bindings), `config/` (app, cache,
   database, filesystem, hashing, logging, redis, view), `routes/{web,schedule}.php`,
   `resources/views/`, `public/`, `database/`, `tests/`, `omega` CLI.
4. **Your first feature** — `make:migration`, `db:create`, `Schema::table`,
   `make:model`, `make:controller`, `make:view`, then
   `Router::get('/', [SomeController::class, 'handle'])`. Use ONLY real
   paths/methods (see Non-negotiable rules).
5. **Routing, requests, responses** — `routes/web.php`, the attributes API
   (`#[Get]`, `#[Name]`, `#[Middleware]`) + `Router::register([...])`, and the
   `view()` helper.
6. **CLI quick reference** — `php omega <cmd>`: serve, migrate (alias
   migrate:run), db:create, db:seed, make:* commands, route:/config:/view:cache
   commands, cache:clear.
7. **Go deeper** — link the 33 Package Manuals (Application, Container, Router,
   Database, View, Http, ...) and the per-use-case map: route→Router, DB→Database,
   template→View, container→Container/Application.

## Non-negotiable rules (apply to every page you write or update)

- Controllers: `app/Http/Controllers/`, public method `handle()` — never
  `app/Controller/` and never `index()`.
- Route file: `routes/web.php` — never `route/web.php`.
- Views: Templator `.template.php`, `{% ... %}` tags — never Blade syntax;
  `{{ $var }}` is a plain PHP echo, not a template tag.
- `view($path, $data = [], $option = ['status' => 200, 'header' => []])`.
- `bootstrap/app.php`: `Env::load(...)`, then `new Application(...)`, then
  `$app->set(...)` for HttpKernel/ConsoleKernel/ExceptionHandler.
- Framework = `Omega\*`, application = `App\*`.
- Every CLI command and composer script must match `composer.json` scripts and
  the `omega` command list.

## Workflow — create from scratch

1. Ask the user where the section should live inside the VitePress tree (e.g.
   a `getting-started/` folder vs a single page), and how it should be linked
   in nav/sidebar (`.vitepress/config.mts`). Do not invent placement.
2. Verify every PHP snippet, file path and command against the real starter
   app before writing (grep/read `app/`, `bootstrap/`, `config/`, `routes/`,
   README, vendor framework).
3. Write the files under `/home/morpheus/docs` following the tone of the
   existing package pages (concise prose, concrete PHP examples, honest notes).
4. Wire the section into `.vitepress/config.mts` (nav + sidebar) as agreed.
5. If `npm`/vitepress is already installed in `/home/morpheus/docs`, optionally
   run `npx vitepress build` to confirm it builds (outDir `../site`) — no new
   tooling without asking.

## Workflow — update/refresh an existing section

1. Diff every snippet in the section against the current starter app:
   `app/`, `bootstrap/app.php`, `config/`, `routes/`, README, `composer.json`.
2. Fix any drift in paths, method names, commands, package-manual links.
3. Keep the outline stable; extend it only if the framework added conventions.
4. Re-check internal links (VitePress `cleanUrls`): links must resolve to
   existing pages/slugs.

## Notes

- The docs live OUTSIDE the omega repo (`/home/morpheus/docs`); writing there
  may trigger external-directory permission prompts — expected.
- The user's word is the source of truth on layout and tone: ask when unsure.