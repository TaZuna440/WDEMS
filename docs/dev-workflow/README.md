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
