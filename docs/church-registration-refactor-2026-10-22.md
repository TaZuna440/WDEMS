# Church registration refactor and specification audit — 2026-10-22

Branch: `feat/church-registration`. Starting commit: `2abe62f`.
Authority: the former `docs2/` documents now reside in `docs/specs/`, as recorded in AI-CONTEXT §24. Church_Events_Update Amendments B–D prevail over older prototype and companion-spec statements. This audit does not certify complete implementation of every feature in the specs.

## Findings and corrections

- The B-5 commit message claimed four registration routes, but they were absent. Added authenticated, event-scoped routes and fixed the event-page registration link. The interrupted additions had repeated links with missing event IDs.
- The registration screen had inert Edit/Delete buttons, no visible validation errors, incomplete identity inputs, and hardcoded dark surfaces. It now supports Add/Edit/Delete, full name parts, email/phone, birthdate or fallback age, address, participant type and guardian details, status/source display, and organizer review using existing UI components.
- Staff registration now records encoder, encoding/attestation time, and an eight-character code grouped 4–4. Held codes are withheld from the registration list. Staff registration does not send OTP or create attendance.
- Church exact checks and likely-duplicate HOLD detection use the existing review service with an explicit event-side pool. Running and church identities never match across pools. Birthday alone remains advisory. Review actions retain transaction and audit behavior and check the event's pool.
- Add and edit serialize on the event row. Exact matches reject with field errors instead of a database error. Likely matches remain held. Correcting a registration cannot rewrite another event's participant record. Editing into a likely match while attendance exists is rejected rather than deleting recorded history.
- Deleting an active registration cascades attendance, retaining the participant. Held/terminal submissions require the audited review path; referenced master records cannot be silently removed.
- Contactless church staff adds remain permitted with a visible warning, per the Master §5.5 / companion Appendix E. They create independent records and are never resolved by name alone.
- As explicitly confirmed by Mark in this session, Services/Fellowship Add actions remain until the registration bridge exists. Restored their interrupted removal. Their separate occurrence/event tables do not yet feed the new generic Event registration list.
- Dashboard confirmed counts/activity now exclude held/terminal records; attendance counts only Present. Church action items and upcoming events use ChurchEventStatus and exclude completed/cancelled church events. Unsupported church attendance totals show unavailable rather than fabricated zeroes.
- Removed obsolete PHPStan baseline entries for resolved church-controller and dashboard errors; added no new suppressions.

## Validation

- Full suite: 952 passed, 3 skipped, 0 failed (955 total; 3,968 assertions).
- TypeScript: pass.
- Frontend check: 198 files formatted; 137 files linted, no warnings/errors.
- PHPStan: pass, zero errors against the existing project baseline.
- Pint repository gate: pass.
- Separate `lint:design`: fails one rule affecting two pre-existing locations: `resources/js/components/encoder-name-search.tsx:68` and `resources/js/pages/registrations/verify.tsx:451` (yellow/amber warning surfaces lack light-mode overrides). Raw-white notices are informational.
- Added 9 church-registration tests and 2 dashboard tests. Updated the review-isolation assertion to verify an empty church result rather than prohibit all church reviews.
- Local migration status: batches 18, 19, 20 and 21 already applied. No migrations edited or applied and no database reset performed during this refactor.
- Browser interaction/visual QA and remote CI were not run in this pass.

## Remaining work — do not label the project 100% aligned

1. B-6 public church registration and the Services/Fellowship occurrence bridge remain unbuilt. The new page is for church records in `events` only. Existing legacy entry paths intentionally remain.
2. B-7 unified monitoring-only church attendance, code lookup, summary and cross-module participant history remain separate build work.
3. Generic Events currently have a church category but no activity discriminator for Youth Camp. Optional guardian fields are exposed for Youth and Kids Ministry; precise Youth Camp-only conditional behavior needs the activity model.
4. Dashboard remembered-side behavior, linked stat sources and event-aware interactive calendar remain unfinished. Church attendance aggregates await the unified workflow; placeholders are now explicit.
5. The two design-audit violations above remain.
6. Existing PHPStan baseline debt outside the refactored code remains; passing against a baseline does not mean the historical type errors were all corrected.

Changes are local and uncommitted at the time of this report. No merge or push was performed in this refactor pass.
