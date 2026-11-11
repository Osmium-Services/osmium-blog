# Blog Service (design)

Status (updated 2026-10-10): **published**. Repo `Osmium-Services/osmium-blog`, now v0.11.5<!--v:osmium-blog-->, in the Store, service id
`osmium-blog`, category content. The `page.claim` hook is in core and documented on [[Hooks Reference]]. The text below is
the original design and the 2026-10-07 build notes, kept as written. Drafts are noindex, there is no scheduling, SolarGo2
cannot be the test theme (it runs an older Osmium), and uninstalling leaves the data.

## Why

SolarGo2's blog brings most of its traffic, but every post there is hand-built: a module folder with a controller and
view, images under `assets/images/blog/<slug>/`, a Pages row, and two hand-typed card lists (the index and the "Latest
Blog" strip). The base has no blog. Goal: non-developers write and publish posts from admin.

## Split of responsibility

Same split as the Shows service.

- **Service**: posts table, admin (its own top-level menu item below Shop, via `"placement": "top"`), editor, and a small PHP API
  (`latest(n)`, `published()`, `findBySlug()`) returning plain arrays: title, slug, excerpt, hero image, dates, author,
  body HTML, SEO fields.
- **Theme**: all markup. `blog/views/index.phtml` (list), `post.phtml`, and a `blog-latest` block for the home page.
  The service ships no CSS. The body HTML is styled by the theme under a class such as `.blog-post-body`.

## URLs: a Pages row per post

The shop does not bypass the Pages table: each product and category has a Pages row plus a mapping row, and
`OsmiumShop` asks "is this page ID a product or category?". Blog follows that:

- Saving a post creates or updates its Pages row (parent = the `blog` page) and keeps a `post_id` to `page_id` mapping.
  The row supplies slug, title, meta description, robots, dates, sitemap entry and redirects.
- **Core work needed**: a hook so an installed service can claim a page ID and set `pageContent` (today `OsmiumShop` is
  hard-wired through `modules.json.php`), and the admin Pages list must hide or label service-owned rows so nobody edits
  one by hand.
- Changing a post's slug creates a redirect (Redirects system) so inbound links and search traffic survive.

## Editor

Summernote, as in product copy, categories and reviews (`product-edit-editors.js` has the DOMPurify pattern).

- Fuller toolbar than the product editors: headings, links, lists, images, blockquote, table.
- Same sanitiser (DOMPurify, `<b>`/`<i>` to `<strong>`/`<em>`) on leaving code view and on submit.
- No inline `style` in the blog's allowed attributes, so theme CSS is not fought.
- **Link picker**: a custom button opening one searchable list of Pages, categories, products and blog posts, with a type
  label. Inserts a normal `<a href>`; the post stores plain HTML.
- **Images**: upload through the existing admin image handling into the blog's own folder. Writes `<img>` with `alt`,
  `width` and `height`.

## Post fields

Title, slug, hero image, social image (defaults to hero), excerpt, meta description, publish date, draft/published,
author, body. The author is picked from the registered admin users (they have `first_name`, `last_name` and `avatar`),
stored as a user ID and defaulting to the logged-in user. SolarGo2's schema hard-codes one person. A deactivated
author stays listed on their posts (decided 2026-10-07); the editor can change the author later. Users are deactivated
(`is_active`), not removed, so the ID keeps resolving. Not checked: whether a user can be hard-deleted.

## Schema

`BlogPosting` JSON-LD from the post, as SolarGo2's `blog-post-schema.phtml` does, but with author and image from the
post rather than hard-coded. Offer it as a block the theme includes.

## Left out for now

Comments, tags and categories, multiple authors, RSS.

## What was built (2026-10-07) and where it differs from the design

- **Core**: `page.claim` hook in `OsmiumController` (see `ServiceHooks` docblock and `THEME-CONTRACT.md`), reusing one service
  loader. A pages row with no view and no claim now 404s instead of answering a blank 200.
- **Service**: posts table (`page_id`, excerpt, body, hero/social image, author user id, published flag and date), `BlogPost`
  model, `BlogClaim` (claims the `blog` index page and published posts), `BlogSchema` (BlogPosting JSON-LD, `JSON_HEX_TAG`),
  `BlogImages` (checks file type, scales to 1600 wide, saves WebP under `assets/images/blog/`), admin list and editor.
- **Editor**: Summernote with headings, lists, links, table, a **Page** link picker (pages, categories, products, published
  posts), an **Image** button (upload then alt text) and hero/social image slots. Summernote's built-in Picture button is not
  registered because it embeds base64. DOMPurify config has no `style` or `class`.
- **Slug change** redirects the old address (`AdminPages::update`, a 302). **Delete** removes the post and permanently deletes
  its pages row so the address can be reused.
- **Pages list marker** (v0.3.1): core fires `pages.owners` (collect) when the admin Pages list loads; the service returns its
  page ids. Those rows get a "Managed by Blog" column, type "Managed by a service" in the Type filter, an extra **Edit content**
  action linking to the blog editor, and a locked Delete. `PagesController` also refuses delete and permanent delete for them
  server-side. Edit (title, description, robots, address) stays available there, since the post finds its page by id.
- **Index page**: the `blog` pages row is created when the first post is saved, and is the parent of every post.
- **Differences**: no pagination yet (not built). "Latest posts" is `BlogPost::latest(n)` called from the theme's controller, not a theme block. Admin menu entry
  is a top-level item below Shop (`placement: top`), not under Services like the other services.

## Untested or open

- Product links in the picker (the dev DB had no products); EXIF rotation (no exif extension in the container); a real
  file-picker upload (driven from script with generated images); the `blog` index claim on a site that already has its own
  `blog` module folder.
- Orphaned images are never deleted. Schema `@id` uses `$site['fullURL']`, which always ends in `/`, even where
  `urlTrailingSlash` is off.
- No unit tests in the service repo; no git repo, README or Store listing yet.

## MCP tools (v0.7.0)

Blog answers core's `mcp.tools` hook ([[MCP Server]]) with `BlogMcp`: `blog_posts_list` (optionally the bin), and the write tools
`blog_post_delete` and `blog_post_restore`, which only appear when the site sets `mcp.writesEnabled`. Both take the post `id` and its
exact `expected_title` and refuse on a mismatch. They call `BlogBin`, the same class the admin screen uses, so a soft delete sends the
page to the Pages bin and a restore brings it back. Changelog entries read "<app name> via MCP". There is no permanent-delete tool and no
author names in any output. v0.7.0 also fixed `BlogController::pages()`, which still used the old `AdminPages` constructor from before
core `bad12aa` (8 Oct), so Blog save, delete and restore would have thrown on current core.

