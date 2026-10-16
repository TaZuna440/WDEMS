# Restructure Phase 1 — Navigation

**Status:** Ready for implementation
**Parent plan:** `docs/ui-restructure-plan.md`
**Reversible:** Yes — no database changes
**Blocks:** Nothing

---

## 1. What this phase does

1. Sidebar: seven items become five. Remove Services,
   Fellowship, Discipleship. Add Reports.
2. Move three modules under a shared prefix:

   /services     →  /events/church/services
   /fellowship   →  /events/church/fellowship
   /discipleship →  /events/church/discipleship

Route **names** stay unchanged. `services.index`,
`fellowship.attendance.show`, `discipleship.programs.show`, and
every other name keeps working. Only URL paths move.

---

## 2. What does NOT change

- Controllers, services, models, migrations
- All existing data
- The three modules' page components — only their URL
- Route names
- The Events page's running-event list
- The wizard
- Auth, middleware, roles

---

## 3. Sidebar change

`resources/js/components/app-sidebar.tsx` — `mainNavItems` array:

Remove three entries (Services, Fellowship, Discipleship).
Add one (Reports at `/reports`).
New order: Dashboard, Events, Registration, Attendance, Reports.

Icons: remove `Church`, `HeartHandshake`, `GraduationCap` from the
import. Add `FileBarChart` (or `BarChart3`).

---

## 4. Route changes

### URL prefix

Wrap the three existing module blocks in
`Route::prefix('events/church')->group(...)`. Every `->name(...)`
stays as-is. Only the URL prefix is added.

### Redirects (per recommendation B — explicit per-pattern)

Placed before the prefix group so they take precedence:

    Route::permanentRedirect('/services', '/events/church/services');
    Route::permanentRedirect('/services/{type}', '/events/church/services/{type}')
        ->whereIn('type', ['morning', 'midweek', 'youth', 'other']);

    Route::permanentRedirect('/fellowship', '/events/church/fellowship');
    Route::permanentRedirect('/fellowship/{type}', '/events/church/fellowship/{type}')
        ->whereIn('type', ['family', 'church', 'pastors', 'leaders', 'womens', 'mens']);

    Route::permanentRedirect('/discipleship', '/events/church/discipleship');

Deeper URLs (e.g. `/services/morning/42`) 404 unless a specific
per-pattern rule is added. Add per-pattern rules only if a real
bookmark is broken; skip until then.

### New route

    Route::get('reports', ReportIndexController::class)
        ->name('reports.index');

Inside the authenticated middleware group, near `attendance.index`.

---

## 5. Breadcrumb updates

Update the `href` on every breadcrumb and back-link referencing
the old paths. Files (from earlier grep):

    services/index.tsx            line 79
    services/attendance.tsx       line 624
    services/show.tsx             lines 142, 551
    fellowship/index.tsx          line 95
    fellowship/attendance.tsx     line 845
    fellowship/show.tsx           lines 87, 457
    discipleship/index.tsx        line 93
    discipleship/batch-show.tsx   line 619
    discipleship/show.tsx         lines 322, 1494

Mechanical: `/services` → `/events/church/services`, etc.

---

## 6. Reports landing page

**New files:**

- `app/Http/Controllers/ReportIndexController.php` — invokable.
  Queries completed events across both kinds (running uses
  `status = completed`, church uses `church_status = completed`).
  Renders `reports/index`.
- `resources/js/pages/reports/index.tsx` — list view styled like
  `events/index.tsx`. Each row links to `/events/{id}/report`.

Empty state: glass-panel card, "No completed events yet."

---

## 7. Verification

    php artisan test              # expect 791 passed, 3 skipped
    npx tsc --noEmit              # expect silent
    ./scripts/audit-design.sh     # expect clean

Redirects (must be authenticated to see them — the 302 to /login
from an unauthenticated curl is not the redirect we want):

    # After logging in via browser, or via a session cookie:
    curl -sI -b <cookie> http://127.0.0.1:8000/services | head -3
    # expect: HTTP/1.1 301 Moved Permanently
    #         Location: http://127.0.0.1:8000/events/church/services

Browser checks:

1. Sidebar has exactly five items in the right order
2. /events/church/services renders Services landing
3. /events/church/fellowship renders Fellowship landing
4. /events/church/discipleship renders Discipleship landing
5. /services → 301 → /events/church/services
6. /reports renders the new Reports page
7. Breadcrumbs read Events → Church → [Module]

---

## 8. Rollback

`git revert <commit>`. No database changes to unwind. Route
prefix is one block. Redirects are additive.

---

## 9. Open items

- Deep-URL redirects: explicit per-pattern only, add on demand.
- Reports page contents may expand in Phase 5.
