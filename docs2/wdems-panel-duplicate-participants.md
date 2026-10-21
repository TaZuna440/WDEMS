# Panel Explanation — How WDEMS Handles Duplicate Participants

## The 60-second script (say this)

> "WDEMS does not identify a participant by name alone or by email alone,
> because in a church and community setting, two people can share the same
> name, and family members often share one email address. Instead, the
> system uses a **composite identity**: the participant's normalized full
> name — first, middle, last name, and suffix — combined with a contact,
> which is the email address or contact number.
>
> A registration is considered a duplicate only when **both** the name and
> the contact match an existing registration **for the same event**. When
> that happens, the system keeps the first registration and rejects the
> second one. The check happens early — before the email verification code
> is even sent — and the message shown does not reveal the existing
> person's information, for privacy.
>
> On the public registration form, the email is verified through a
> one-time code and protected by reCAPTCHA, so the email used in the
> duplicate check is proven to belong to the person registering. Across
> different events, if the same name and contact register again, the
> system reuses the same participant record instead of creating a new
> person. This rule applies to both the running events and the church
> events, because both share one participants table. For church
> services, the 'same event' means the same service occurrence — this
> Sunday and next Sunday are different occurrences, so registering for
> both is a new registration by the same person, not a duplicate."

## The flow, step by step

1. Participant submits the registration form (public link) or staff encode
   the participant (manual add).
2. The system normalizes the name (case and extra spaces removed) and the
   contact number, then checks: *same name + same email/phone already
   registered in this event?*
3. **Exact match** → duplicate. First record is kept; the new submission
   is rejected with a non-revealing message. (On the link, this happens
   before any OTP is sent.)
4. **No match** → registration proceeds. If the same identity exists from
   a *previous* event, that participant record is reused — no new person
   is created.
5. **Possible duplicate** → records that are merely similar may be
   flagged or listed for the organizer. They are not automatically
   blocked or merged, because they may be different people — e.g.,
   father and son.
6. **Likely duplicate** → same email/phone **and** a highly similar
   name, such as one entry omitting the middle name (“EJ Rubi Pomasin”
   vs “EJ Pomasin”), is **held for organizer review**. It is not a
   completed registration and does not enter the confirmed count or
   Attendance until the organizer confirms it is a separate person,
   corrects it, or closes it as a duplicate of the first record.

## Worked example (use if they ask for one)

Sunday Service, one family email `santos.family@gmail.com`:

| Submission | Result | Why |
|---|---|---|
| Maria Santos + family email | Accepted | First registration |
| Maria Santos + family email (again) | **Rejected** | Same name + same contact, same event |
| Jose Santos (son) + family email | Accepted | Same email, different person |
| Maria Santos + a different email | Possible-duplicate flag | Strict rule: different identity; organizer is warned it may be the same person |
| EJ Rubi Pomasin + same email | Accepted | First registration |
| EJ Pomasin + same email | **Held for review** | Same contact and highly similar name; one entry omitted the middle name |

## Church events — the same rule, scoped per event

If the panel asks whether this applies to church events: **yes, the same
rule, word for word.** The duplicate judgment is always scoped to one
specific event:

- **Same church event** (e.g., one Sunday Service) → same name + same
  contact registering twice = duplicate; first record kept, second
  rejected.
- **Cross-channel counts too.** Each church event has ONE registration
  list fed by the organizer's manual adds and the public link. Registering
  manually and again through the link is still a duplicate — the check
  covers the whole list, not each entry door separately.
- **Different church event** → a new registration, which is expected and
  allowed; the existing participant record is reused, so the person is
  never duplicated.
- **Services nuance:** "same event" = the same service *occurrence*. This
  Sunday vs. next Sunday are different occurrences — same person, two
  registrations, no duplicate.

One line to remember: **duplicate is judged per event; identity is
shared across all events.**

## Anticipated panel questions

**Q: Why not use the email address as the unique key, like most systems?**
Because our users are church members and community runners, not account
holders. Parents register children and siblings under one email. An
email-only key would either block those legitimate registrations or —
worse — register the child *as* the parent. The composite key (name +
contact) is the same approach used by event-registration platforms such
as Corsizio, which block an identical attendee name + email combination
for the same event while allowing one email to register several people.

**Q: Why not use the name alone?**
Names are not unique — common Filipino names repeat, and one person can
also be a Jr. and another a Sr. That is why the suffix and middle name
are part of the identity, and why a contact must also match.

**Q: What if the same person registers with two different emails?**
Honest answer: the exact rule alone will not prove it, and we chose
that deliberately. Automatically merging similar records risks
combining two different people, which is more harmful than a duplicate.
WDEMS therefore separates **possible** from **likely** duplicates.
Possible matches are flagged for the organizer. A likely duplicate —
the same email/phone and a highly similar name, such as a version with
the middle name omitted — is held and is not treated as a completed
registration until the organizer decides. This follows the pattern of
CRM systems like Salesforce: exact matches may be blocked, uncertain
matches go to human review, and records are never auto-merged.

**Q: How do you know the email really belongs to the registrant?**
On the public form, email is required and verified with a one-time code;
reCAPTCHA prevents automated abuse. There is no phone verification — we
removed it to keep one simple, reliable verification channel. The phone
number is still collected and used for matching, but it is not verified.

**Q: What about participants encoded manually by staff, with no verification?**
The same duplicate check runs on the details as entered. We acknowledge
its strength depends on the staff's encoding, which is why the check is
the same rule, and why duplicate-looking records are surfaced to the
organizer rather than silently accepted.

**Q: Does this work the same way for church events?**
Yes. A church event's registration list is checked as one list, whether
the entry came from the organizer's manual add or from the event's public
link. The link form requires email and verifies it with a one-time code,
the same as the running events' form. For recurring services, the check
is per occurrence — registering again next Sunday creates a new
registration under the same participant record, not a duplicate person
and not a blocked registration.

**Q: Is this already implemented?**
The composite rule is implemented and tested in the running-events side
(normalized name matching, duplicate check at verification, participant
reuse across events). The church-events update adopts the same rule —
same participants table, same identity — as part of the church module's
registration redesign, which is documented in the update specification.

**Q: Does the rejected user learn whose record they matched?**
No. The rejection message is deliberately non-revealing ("This
registration cannot be started. If you believe this is an error, contact
the event organizer."). It confirms nothing about the existing
participant's details, in line with data-privacy practice.

---

## MVP and scope — one-line and what's-in/what's-out (added 2026-10-21)

Delivered in chat when Mark asked "what is the MVP of our WDEMS?"; filed
here so every panel-ready answer lives in one place.

### The one line (say this)

> "An organizer can create an event, open registration, receive verified
> participants, record attendance, and produce the final summary or
> report — without using paper forms or a separate spreadsheet."

### The full event cycle

Create event → open registration → receive verified participants →
record attendance → produce the final summary/report.

### What's in the MVP

1. **Organizer access** — secure staff/organizer login; create, edit, and
   manage events.
2. **Event creation** — name, category/ministry, date, time, venue,
   description; running and church events kept separate through kind
   isolation.
3. **Registration** — a public, event-specific registration link; manual
   registration by staff for people who cannot register online; required
   participant and emergency-contact information; email OTP verification
   protected by reCAPTCHA; no phone verification.
4. **Participant identity and duplicate protection** — one shared
   participant record across running and church events; composite
   identity (normalized name + email or phone); exact duplicates in the
   same event blocked; likely duplicates held for organizer review;
   family members may share an email when their names are different.
5. **Registration management** — organizers can monitor, add, edit, and
   delete registrations; each completed registration receives a
   registration code.
6. **Attendance** — registrations move automatically into attendance;
   code lookup or name/email/phone search; Present / Absent / Unmarked
   (Unmarked = default). Running events may use code/barcode check-in;
   church attendance is the simplified monitoring list — no walk-in
   encoding modes, no camera scanning.
7. **Summary and report** — Registered, Present, Absent, and Unmarked
   totals; participant list and event report; CSV export where applicable.

### What's NOT in the MVP

- Payments, donations, and church finance
- Facility booking, payroll, and inventory
- SMS or phone verification
- Automatic participant merging
- Advanced analytics and long-term participant-history reporting
- Specialized ministry features (Youth Camp payments, Timothy Class
  progress tracking)
- Participant accounts and per-person revocable links
- A native mobile application

### Simple panel explanation of scope

> "Our MVP focuses on the core event-management workflow: event
> creation, verified registration, participant management, attendance
> monitoring, and reporting. WDEMS supports both Community Run and
> church events using one participant identity, while keeping their
> event workflows separate. Features outside that core cycle — such as
> finance, advanced analytics, and specialized ministry functions —
> are reserved for future development."

## Amendment — participant separation rule (2026-10-21)

Mark decided during the church feature definition (2026-10-21) that **Church
Events participants are separated from Running Events participants**. Identity
matching, duplicate detection, monitoring, and history are scoped **per side**:
the same person may exist once as a running participant and once as a church
participant — that is by design, not a duplicate, and the sides never
resolve-or-create into each other. Within a side, the composite rule still
applies and duplicates are judged per event (church: per occurrence for
Services). Rationale: each side monitors its own people's history — running
reports show how many Community Runs a participant attended; church monitors
how many times a person attended and what types of church events they joined.

This supersedes the following statements in this file:

- "Church events — the same rule, scoped per event": the rule is now scoped
  per side first, then per event.
- "One line to remember: **duplicate is judged per event; identity is shared
  across all events.**" → now: **duplicate is judged per event; identity is
  shared within each side.**
- "the existing participant record is reused, so the person is never
  duplicated" — true within a side; across sides a separate per-side record
  is created by design.
- MVP item 4 ("one shared participant record across running and church
  events") and the scope paragraph ("using one participant identity") — now
  per-side identity; the event workflows were already separate.
- "What's NOT in the MVP: Advanced analytics and long-term
  participant-history reporting" — narrowed: per-side participant history
  (attendance count + event types joined) is now IN scope for the church
  build; advanced analytics beyond that remain out.

Code status (verified at 5755c75): the pushed code still matches globally —
`Participant::findByComposite()` has no side filter and the church resolvers
call it directly — so the church build needs a side-scoping amendment (side
discriminator on participants, side filter in the resolver). The duplicate
advisory layer (`DuplicateReview`) is already per-event and needs no change.
