# WDEMS — Development Workflow

**Audience:** Whoever picks up WDEMS development — human or AI — and
needs to know how work actually gets done here. Not how it *should* be
done in theory. How it *is* done, based on what worked and what broke.

**Purpose:** Reduce the cost of every future session by writing down the
rules once. When a new person (or AI) joins, they read this and skip the
learning curve.

**Do not treat this as policy.** Treat it as practice. If a rule gets in
the way, change it here first.

---

## 1. The core shape

Two participants:

- **The developer** — has the code, runs the terminal, decides what to
  work on
- **The AI** — sees only what the developer pastes, proposes changes,
  never runs anything

That asymmetry defines everything else. The AI does not have a repo, a
file system, or a way to observe the state of the project except through
what the developer shows it.

---

## 2. How communication works

| Developer sends | AI responds with |
|---|---|
| `cat file.php` output | Reads, confirms what it sees, asks clarifying questions if the file's intent is ambiguous |
| Multiple files in one message | Full assessment of the subsystem |
| Answers to clarifying questions | A proposal: rationale, blast radius, exact heredoc to run |
| Verification output (`wc`, `grep`, `diff`) | Confirms or corrects |
| Test failure, log tail, `ps` output | Diagnosis and one specific next command |
| A pushback ("was that really needed?") | Retraction or refinement |

The loop is short. The AI never writes and runs code. The developer never
asks the AI to "look at the repo." Every message either provides input
or proposes output.

---

## 3. Protocols

Numbered by order they were established in practice, not by importance.
Every one of these was learned by breaking it first.

### P1 — Read before proposing

Never propose a change to a file unless its current contents were seen in
this session. If uncertain, ask for a `cat`. File drift is real — a
version pasted twenty turns ago may not be what is on disk now.

**Failure mode this prevents:** Writing a rewrite against a remembered
version and silently reverting something that was added in between.

### P2 — Heredoc only

Every code change is delivered as one of:

    cat > path/to/file <<'UNIQUE_DELIMITER'
    ...contents...
    UNIQUE_DELIMITER

or, for appends:

    cat >> path/to/file <<'UNIQUE_DELIMITER'
    ...contents...
    UNIQUE_DELIMITER

No `sed`, no `nano`, no `vim`, no `python`, no one-shot scripts. Exception:
single-line substitutions on files the developer is confident about may
use `sed -i` when the pattern is unambiguous.

**Why:** heredocs make the entire change visible before it runs. Editors
hide it. Scripts hide it behind logic.

### P3 — Unique delimiter per heredoc

Each heredoc uses a distinct label: `WDEMS_ASP_EOF`, `WDEMS_WIZARD_EOF`,
`WDEMS_DEMO_EOF`, and so on. Never reuse a delimiter across files in the
same session.

**Why:** makes leaked delimiters greppable. If `grep -n "WDEMS_ASP_EOF"
file.php` prints a line, something went wrong and the file is polluted.
Unique names make the diagnosis instant.

### P4 — One block per message

When a change spans multiple files, each file gets its own block, its own
verification, and its own response cycle. No batching.

**Why:** if Block 3 fails, Blocks 4 and 5 have not been polluted yet. The
state is always known.

### P5 — Verification is part of the deliverable

Every heredoc comes with verification commands:

    wc -l path/to/file
    grep -n "UNIQUE_DELIMITER" path/to/file    # should print nothing
    grep -n "expected-change" path/to/file     # should print a hit
    diff path/to/file.bak path/to/file         # when applicable

**Why:** the developer never has to trust that the write worked. The
verification is cheap and immediate.

### P6 — No backups unless requested

Do not `cp file file.bak` by default. If the project is under git, git
is the backup. If it is not, one explicit backup at the moment of
highest risk is enough. Reflexive backups clutter the tree.

### P7 — Append-only corrections

When a doc's earlier claim is later proven wrong, do not rewrite the
earlier text. Append a `## Corrections` section that explicitly revokes
the old text and states what is now true. The original line stays.

**Why:** preserves the record of what was believed at the time and what
was learned afterward. Readers see both the evolution and the current
state.

### P8 — Name failures immediately

When something the AI proposed turns out to be wrong, say so in the same
response. Do not silently work around it. If a later reader sees the
correction without seeing the original mistake, they lose the lesson.

---

## 4. How the developer uses the AI

Not as autocomplete. Not as an agent that reads the repository. Not as a
copilot that guesses.

**As a reviewer with session memory.**

Concretely:

| Role | Example |
|---|---|
| **Diagnostician** | Paste a test failure → AI reads the SQLSTATE, points at `config:cache` as root cause |
| **Writer with style memory** | Every doc matches the ones before it without restating the house style |
| **Second reader** | Catches mischaracterizations and simplifications the developer missed |
| **Enumeration engine** | "List the phases of the login system" → table. "Define the test accounts" → table |
| **Verifier** | Reads a diff, confirms the change is what was intended |
| **Retractor** | Corrects its own wrong proposals when the developer pushes back |

The AI is closer to a colleague who has read every file you pasted but
has never seen the actual repository.

---

## 5. Known failure modes

These are the mistakes the AI has made in this project. They are listed
so they can be caught early next time.

### Broken markdown fences

When a response contains a heredoc, and the heredoc itself contains
triple-backtick code blocks, the outer fence must be four backticks.
Otherwise the message renders in pieces and cannot be copied as a
single block.

**Detection:** ask "can I copy this as one block?" If the answer is not
obviously yes, it is broken.

### Wrong about middleware behavior

Laravel's `verified` middleware only enforces when the user model
implements `Illuminate\Contracts\Auth\MustVerifyEmail`. If it does not,
the middleware is a no-op and passes through silently.

**Detection:** any claim about middleware behavior should be verified
against the actual model class and the framework source.

### `config:cache` in local development

Running `php artisan config:cache` in local dev writes
`bootstrap/cache/config.php`. From that point on:

- `.env` changes are ignored until `config:clear`
- `phpunit.xml` env overrides are ignored
- Feature tests run against the `.env` database instead of SQLite
  `:memory:`
- `RefreshDatabase` drops every table in that database

**This exact cascade wiped the local MySQL database once.** See
`docs/AI-CONTEXT.md` §13 for the full warning and recovery steps.

### Over-engineering small changes

For five-line edits, a Python script is overkill. The script has to be
audited, run, and verified — the same effort as just running five
heredocs.

**Detection:** if the automation costs more to understand than the change
it performs, drop the automation.

### Recommending editors out of caution

Telling the developer to use `nano` for a change that a one-line `sed`
handles cleanly. Editors are not universally safer than `sed`.

**Detection:** match the tool to the change, not to a reflex fear.

### Snippet correctness

The README's original Tinker snippet for creating an admin used
`User::create([...])`. But `email_verified_at` is not in `$fillable`, so
the field was silently dropped and every created admin was unverified.
The snippet was wrong; nobody noticed until the demo account kept
landing on `/email/verify`.

**Detection:** any snippet involving a model with `#[Fillable]` should
use `forceCreate()` if it needs to write non-fillable fields.

---

## 6. The workflow in one line

> The developer pastes. The AI reads. The AI proposes a heredoc. The
> developer runs it. The developer pastes verification. The AI confirms.
> Corrections go in the docs, not in the file. Every step is greppable.
> Nothing is silent.

---

## 7. Files that define this workflow

| File | Purpose |
|---|---|
| `docs/dev-workflow/README.md` | This document — the rules |
| `docs/AI-CONTEXT.md` | The paste-me-at-start-of-chat file that teaches a new AI session the project and the traps |
| `docs/known-issues.md` | Backlog of problems found but not yet fixed |
| `docs/architecture.md` | Whole-system overview |

---

## 8. When to update this document

Update when:

- A new protocol is established by breaking an old one
- A failure mode recurs that is not already listed
- A rule stops being useful

Do not update for one-off incidents that are already documented elsewhere.

---

## Changelog

- **2026-09-25** — File created. Captures the workflow, protocols, and
  failure modes from the initial documentation and bug-fix arc.

---

## 9. Additional protocols (2026-09-25)

Section §3 is preserved as written. Two new protocols were established
in the same session that produced the fixes above. They are appended
here rather than edited into §3 — the file is append-only.

### P9 — Commit messages come from diffs, not docs

**Rule.** Before writing a commit message, either:

1. The change was personally observed landing in this session, **or**
2. `git diff --cached` (or `git --no-pager show`) has been read for
   every file the message describes.

Never compose a commit message by reading `docs/fixes.md` (or any other
document) and listing the FIX-NNN references it claims. Docs describe
intent. Diffs describe reality. When they agree, the message is
redundant. When they disagree, the message is a lie.

**Why.** On 2026-09-25, commits `a82b5d3` and `6f11f40` were composed
from FIX-NNN references in `docs/fixes.md` without reading the actual
diffs. Two of the fourteen files were spot-checked afterward and
matched. The remaining twelve carried unverified claims in their
commit messages.

The repo already contained a drift that would have caught this if it
had been checked first: `README.md` still listed "Map picker with
reverse geocoding" as ✅, even though FIX-016 had removed the map
picker. Docs and code had diverged. The commit message assumed they
had not.

**Detection.** Any commit message referencing a FIX-NNN, a trap number,
or a docs section — without the corresponding diff having been read in
the same session — is suspect.

**Recovery.** If a commit message is found to misdescribe its content,
do not amend. Add a `## Corrections` block to `docs/progress.md` under
the same date, stating the actual change and correcting the message.

### P10 — Do not commit unless every staged file has been read this session

A stricter reading of P1 (Read before proposing). The original rule
said "propose." This extends it to "commit."

**Rule.** Before `git commit`, run:

    git diff --cached --name-only

For each staged file, either:

1. It was read in this session (before the change, or the diff was read
   after), **or**
2. It is explicitly listed in the commit message as *"not reviewed this
   session — carried from prior work."*

Files in the second category must not carry FIX-NNN claims. The commit
message must be honest about which files were verified and which were
carried forward.

**Why.** Commit `84c33a0` staged 20 files in the interest of capturing
disk state into git. Twelve of those files (all of `docs/*.md`, plus
the four `.bak` removals) had never been read this session. The commit
message said "files that were on disk but not tracked" — accurately —
but the surrounding context implied a review that did not happen.

**Application.** For a *state-capture* commit (bringing disk and git
into agreement), the message should say so explicitly. For a
*feature-fix* commit, every file must have been read.

---

## Changelog addendum

- **2026-09-25** — P9 (commit messages from diffs) and P10 (do not
  commit unread files) added. Both arose from the same session's
  practice of composing commit messages from docs rather than diffs.

---

## P9 verification addendum (2026-09-25)

The two commits named in P9 have now been verified line-by-line against
their diffs. All fourteen files match their commit-message claims:

- `a82b5d3` — FIX-001 through FIX-007 — 8/8 correct
- `6f11f40` — FIX-008 through FIX-016 — 6/6 correct

P9 remains in effect as a *process* rule. The commit messages were
composed from docs rather than diffs, which is exactly what P9 forbids.
That they turned out to be accurate is a lucky consequence, not a
vindication. The rule stands: verification before message, not after.

Recorded as a note because a rule that says "we got lucky" without
recording that the gamble happened to pay off is a rule that will be
ignored the next time someone is in a hurry.

## Changelog addendum

- **2026-09-25** — P9 verification note appended. Both named commits
  confirmed accurate against their diffs.

---

## P11 — Migration files are live in tests the moment they exist

**Rule.** A migration file under `database/migrations/` is not inert.
It runs against the test suite's in-memory database on every test run
via `RefreshDatabase`. Any migration that would break the codebase —
drops a table, changes a column, adds a NOT NULL without default —
must not exist on disk until the code that depends on it is also on
disk and ready.

**Why.** This session (2026-09-25) wrote a drop migration in the same
block as a create migration, intending the drop to run after the
dependents were updated. The dependent files were still pointing at
the dropped tables. The next test run applied the drop and three tests
failed with `SQLSTATE[HY000]: General error: 1 no such table: event_options`.

The error message was specific enough to diagnose in one pass, but the
underlying assumption — "migration files are inactive until
`php artisan migrate` is called" — was wrong. `RefreshDatabase`
changes that assumption in every Feature test.

**Detection.** Any migration that drops a table, drops a column, or
alters a column's nullability should be checked against the current
code that reads it:

    grep -rn "<table_name>\|<column_name>" app/ tests/

If any of those files are on the current commit and unchanged, the
migration must not be on disk until they are updated in the same
change set.

**Recovery.** Move the migration file out of `database/migrations/`
(temporary location is fine — `/tmp` works). The next test run will
not see it. When the dependents are updated, move it back as part of
the same change set and re-run tests.

**Application.** The Phase 2 plan for the registration feature splits
migration drops into a separate block (Block 6) that also touches the
five dependent files. This is the pattern to follow going forward.

## Changelog addendum

- **2026-09-25** — P11 added. Migration files are live in tests via
  `RefreshDatabase`. Drop migrations must not exist on disk until
  dependents are updated.

---

## 10. AI response rules (2026-09-26)

Sections 1–9 describe what work gets done and how. This section
describes how the AI should respond while doing it — the shape of
messages, the language, and the discipline. Recorded at the developer's
request so future sessions start with the standard in hand.

### 10.1 — Response shape by task type

| The task is... | The response is... |
|---|---|
| A decision needed | Trade-offs, one recommendation, ask for yes/no |
| A cat request | File list, one-line reason per file, expected observations |
| A code block | One file per message, heredoc with a unique delimiter, verify commands, expected values |
| A commit | Read the diff first, message sourced from what the diff shows (P9) |
| A doc append | Single heredoc, verify with wc -l + delimiter grep + tail |
| A browser test | Numbered steps, expected observation per step, stop condition |
| A failure diagnostic | Ask for the missing output. Do not infer from a partial paste |
| A claim about the code | Verify with a fresh cat or grep before stating it as fact |

### 10.2 — What to do consistently

- Read the file before proposing a change to it. P1.
- Heredoc for every write. Unique delimiter per heredoc. Verify
  after every write. P2, P3, P5.
- One file per block. One block per message. P4.
- Corrections append, never rewrite. P7.
- Every commit message comes from a diff read in the same session. P9.
- Every staged file is a file read this session. P10.
- Before proposing a migration that drops or alters, grep for every
  reader of the affected table or column. P11.
- When the state is unclear, ask for a fresh diagnostic instead of
  inferring from a paste.
- When a claim is not verified — a line count, a behavior, a
  convention — say so. "I do not have access to X" beats a
  fabricated answer.
- Size the response to the ask. A one-line change does not need a
  thousand-word explanation.

### 10.3 — What I have done poorly this session

Recorded honestly. Every entry below happened during the 2026-09-25
to 2026-09-26 sessions. They are kept as concrete reminders.

1. **Fabricated a "search."** Asked to research common community-run
   registration questions, I opened with "I'll search for..." and
   then produced nothing. I do not have web access in this session.
   The developer caught it before I recovered. The correct opening
   would have been: "I cannot search. What I have is training-data
   recall, offered as unverified."

2. **Repeatedly over-projected line counts.** Estimated 200 for a
   302-line file, 155 for 143, 290 for 340, 266 for 242, 28 for 24,
   and others. Every prediction was wrong in the same direction — I
   assume brevity and get length. Stop predicting. Wait for `wc -l`.

3. **Misread "nothing to commit" as failure — twice.** Both times the
   commit had already landed in a prior run and the paste was a
   duplicate of the same block. Neither was a real failure. The fix:
   when a commit returns "nothing to commit", the first response is
   `git log --oneline -3`. If the commit is there, it landed. If not,
   retry. Do not treat the message as authoritative on its own.

4. **Over-documented in migration files.** The
   `registration_form_saved_at` migration grew a 20-line "KNOWN RISK"
   block. The actual schema change is one line. The risk belongs in
   the plan and the progress log — it does not need to be restated at
   the migration level. Two locations are enough; four is noise.

5. **Inferred state from incomplete pastes.** Truncated output arrived
   several times this session and I proceeded as if it were complete,
   then discovered the missing data was material. The fix: when a
   paste is missing an expected line, ask for it before proceeding.

### 10.4 — What has worked

Also recorded honestly. The patterns worth repeating.

1. **Heredoc-only writes throughout.** Every code and doc write used a
   heredoc with a unique delimiter. Zero accidental file corruption
   from editor state.

2. **Diff-before-commit discipline.** Every commit message was sourced
   from `git diff --cached --stat` output read the same session, not
   from memory. P9 held across every commit.

3. **Reconstruction when git could not help.** When
   `event-validation.ts` was corrupted and `git checkout` had nothing
   to restore from (file was untracked), the file was rebuilt from a
   snapshot the developer had pasted earlier, then verified with
   `wc -l` and `tsc`.

4. **Turning failures into rules.** The `event-validation.ts`
   corruption became the single-quoted-grep rule. The untracked-file
   recovery failure became "check `git ls-files` before proposing
   split-edit patterns". The migration that ran in tests the moment
   it hit disk became P11. Each was recorded the same session.

5. **Asking for cats before proposing.** Every phase this session
   started with a cat request, and every code block was written
   against bytes seen in the same session.

### 10.5 — When to stop and ask

- When the request has two readings and the responses would differ.
- When the file to change has not been read in this session.
- When a paste is incomplete, truncated, or shows output that does
  not match expectation.
- When a rule in this document is about to be violated.
- When a claim is about to be stated that has not been verified.
- When a decision is genuinely the developer's — a product choice, a
  threshold, a naming convention — and not a technical default.

## Changelog addendum

- **2026-09-26** — §10 added. AI response rules — response shape by
  task type, consistent practices, observed failures, working
  patterns, and stop-and-ask conditions. Recorded at the developer's
  request.

---

## Correction to §9 (2026-10-13)

The P9 section above records:

> README.md still listed "Map picker with reverse geocoding" as ✅,
> even though FIX-016 had removed the map picker.

That claim is wrong. FIX-016b tightened the URL regex. It did not
remove the map picker. The map picker files remain in the tree
(`resources/js/components/map-picker.tsx`,
`resources/js/components/map-picker-leaflet.tsx`), are still
referenced by `VenueStep.tsx`, and still work.

The README row is accurate. §9's claim about it is not.

Recorded per P7 — the original text stays, this block revokes it.

---

## 10. `composer run dev` multiplex behavior (2026-10-15)

`composer run dev` starts four processes under a single
`@laravel/multiplex` parent:

    server  → php artisan serve
    queue   → php artisan queue:listen
    logs    → php artisan pail
    vite    → npm run dev

Multiplex manages them as a unit. Killing any one child (e.g.
`pkill -f "artisan pail"`) kills the parent, which kills the other
three. The server and Vite die with it, and requests to
`http://127.0.0.1:8000/` start returning connection-refused.

### Detection

`curl -s` swallows the error, but the timing exposes it:

    time curl -s -o /dev/null -w "%{time_total}s\n" http://127.0.0.1:8000/

A real Laravel request on this laptop takes 200ms–1.5s. A
connection-refused response returns in **under 1ms** (0.0002s is
typical). If the measured time is in the microseconds, the server
is not running — the measurement is useless.

Confirm with:

    ps aux | grep -E "artisan serve|vite" | grep -v grep

No output means the whole dev stack is down.

### Recovery

Ctrl-C the terminal that ran `composer run dev`, then start it
again in a dedicated terminal. Do not try to restart individual
children — multiplex will not re-adopt them.

### If you need only the server

Run `php artisan serve` directly in a terminal, in parallel with
or instead of `composer run dev`. It does not need multiplex. Do
this when the queue and log-tail processes are not in use — they
consume MySQL connections and CPU that the single-threaded
`artisan serve` contends for.

### Note on `config:cache` and `route:cache` for performance

`docs/AI-CONTEXT.md` §13 forbids caching config or routes in local
development. That rule stands — the failure mode (tests connecting
to MySQL, `RefreshDatabase` dropping every table) is real and
documented.

If you want the perf win anyway, the discipline is:

1. `php artisan config:cache && php artisan route:cache`
2. Use the app
3. **Before any test run**: `php artisan config:clear && php artisan route:clear`

A shell wrapper makes step 3 forget-proof:

    wtest() {
        php artisan config:clear
        php artisan route:clear
        php artisan test "$@"
    }

Add it to `~/.bashrc` and always run tests via `wtest`.

### What is not a fix

- **Splitting the 487 KB `app-*.js`** — bundle size is not the
  bottleneck on localhost. The server response dominates.
- **Switching to Redis** — the file driver captures 90% of the
  session/cache win at zero setup. Redis is a production concern.
- **`pnpm build` for dev** — kills HMR. Wrong trade for the dev
  loop.
- **Docker / Sail on WSL** — adds filesystem overhead that makes
  local dev slower, not faster.

---

## 11. Process rules from Phases 4 and 5 (2026-10-16)

Five rules emerged during the Fellowship (Phase 4) and
Discipleship (Phase 5) build sessions. Each was learned by
breaking it first. They are additive to the protocols in §3 and
the additions in §9 and §10.

### 11.1 — `mkdir -p` first when the target directory may not exist

Every heredoc that writes into a directory the block itself may
need to create is preceded by:

    mkdir -p <dirname>
    ls -ld <dirname>

at the top of the block. The `ls -ld` is a sanity check — it
should succeed after the mkdir.

**What this prevents.** A block that writes three files into
`app/Http/Requests/Fellowship/` for the first time. Without the
`mkdir -p`, the first two writes fail with
`bash: <path>: No such file or directory` and the block reports
those failures in-line. The controller write succeeds because
`app/Http/Controllers/` already exists. The operator has to
notice the two failed writes and reissue the block.

**What it cost to learn.** Phase 4 Block 4a wrote three files
into `app/Http/Requests/Fellowship/` before the directory
existed. The two FormRequest writes failed; the controller
write landed. Recovery: one extra block to create the dir and
re-write the two files. Phase 5A-4 repeated the same mistake
with `tests/Feature/Discipleship/`.

**Where it applies.** Any new `<Module>`-shaped path under
`app/Http/Requests/`, `app/Http/Controllers/`, `app/Services/`,
`resources/js/pages/`, `tests/Feature/`, and equivalents.

### 11.2 — Empty-anchor guards on every head/tail split

Every `head -n $((VAR - 1))` or `tail -n +$VAR` computation in
a head/tail split block is preceded by:

    if [ -z "$VAR" ]; then
        echo "STOP. Anchor empty."
        <handling>
    fi

on the same line or immediately above.

**What this prevents.** `head -n -1` is a valid GNU `head`
invocation with "all but last line" semantics. If a `grep` that
was supposed to resolve an anchor returns nothing, the shell
variable is empty, and `$((BATCH - 1))` evaluates to `-1`.
The block proceeds with surprising semantics — writing the file
back to itself minus the last line, then duplicating content.

**What it cost to learn.** Phase 5's use-statement reorder block
in `routes/web.php` skipped the guard. The anchor grep returned
empty (root cause not investigated), `head -n -1` fired, and
`routes/web.php` ended up with duplicate use statements and a
PHP parse error at line 367. Recovery: `git checkout
routes/web.php`, re-apply the 5E-2 insertions with the guard in
place.

**The guard is not optional.** Even for one-off correction
blocks. Especially for one-off correction blocks.

### 11.3 — No `exit` in blocks delivered for interactive execution

Bash's `exit` in an interactive shell terminates the shell, not
just the current block. A block that reaches an `exit 1` mid-run
closes the developer's terminal, losing context and possibly
in-flight state.

**What this prevents.** Losing session context on an error the
operator would have preferred to inspect.

**What it cost to learn.** A Phase 5 recovery block included
`exit 1` inside an anchor check. The anchor failed. The
terminal closed. The developer lost the paste history for that
turn.

**The correct pattern.** Print the failure and continue:

    if [ -z "$VAR" ]; then
        echo "STOP. Anchor empty."
    fi

The block still short-circuits — subsequent commands fail or
produce nothing — but the shell stays open. The operator
decides whether to proceed, retry, or rollback.

### 11.4 — No placeholder tokens in heredoc bodies

When a heredoc body needs to contain a value that varies per
invocation (a commit hash, a line count, a file count), do not
leave an angle-bracket token like `<hash>` or `<N>`. Either:

- **Compute it in the shell before the heredoc**, using command
  substitution:

        HASH=$(git rev-parse --short HEAD)
        cat >> docs/progress.md <<EOF
        Commit: ${HASH}
        EOF

  Note: unquoted heredoc delimiter (`<<EOF` not `<<'EOF'`) so
  variables expand.

- **Or leave an explicit `<!-- FILL IN -->` comment** so the gap
  is visible to the operator.

Angle-bracket tokens read like real content to future readers
and can survive to a commit unnoticed.

**What it cost to learn.** Two progress entries shipped with
`<hash>` and `<N>` placeholders intact — Phase 5F's Commits
section and Phase 5G's Commits section. Both went to
`origin/main`. Both were corrected in the Phase 5H closure
entry per P7, but the originals remain in `docs/progress.md`
as-is.

### 11.5 — Section-append blocks are idempotent

A block that appends a numbered section (`## N. Title`) starts
with a guard:

    if grep -q "^## N\." <file>; then
        echo "STOP. Section already exists."
    else
        cat >> <file> <<'EOF'
        ...
        EOF
    fi

**What this prevents.** A double-pasted block silently
duplicating the section. The duplication is invisible during
the paste — the operator sees the content scroll by once per
execution, which reads as "worked." Only a subsequent
`grep -c` reveals the doubling.

**What it cost to learn.** The §11 block above was pasted twice
in one turn. The result on disk was two identical §11 sections.
The commit that captured it held 302 insertions instead of 151.
Recovery required `git checkout` of the file back to a state
before §11 existed, then a re-append — losing the intended
single-§11 commit entirely. The recovered history is
`66a5d6d → 9a4e321 (doubled) → 9c550ff (empty) → <this commit>`
— three commits to land a section that should have been one.

**The guard makes append blocks re-runnable.** A double-paste
becomes a no-op on the second run. The operator sees
`STOP. Section already exists.` and moves on.

### Why these are one section and not five

All five rules share a shape: a block that is safe in isolation
but fails at the boundary between blocks. 11.1 fails at the
filesystem boundary; 11.2 and 11.3 fail at the shell-state
boundary; 11.4 and 11.5 fail at the doc-and-paste boundary.

The common practice: any block that writes files, splits files,
runs recovery logic, or produces a doc artifact should be
audited against all five before delivery. A single checklist.

### Checklist for future blocks

Before delivering any bash block that writes files:

    [ ] mkdir -p for any new target directory
    [ ] empty-anchor guard on every head/tail anchor
    [ ] no `exit` — print and continue on failure
    [ ] no `<placeholder>` tokens in heredoc bodies
    [ ] section-append blocks start with an idempotency guard

If any box is unchecked, the block is not ready.

## Changelog addendum

- **2026-10-16** — §11 added. Five process rules from Phases 4
  and 5, each learned by breaking it. Checklist appended.
