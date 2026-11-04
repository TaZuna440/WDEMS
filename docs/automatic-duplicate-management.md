# Automatic duplicate management

Implemented policy: `automatic-v1`.

## Participant experience

Registration performs identity checks automatically. Organizers do not identify candidates, approve matches, or release duplicate holds. The former review page now shows paginated system decision history.

- One exact normalized full name plus matching identity email or normalized phone reuses the participant, provided known birthdays do not conflict.
- A repeat registration for the same event recovers its existing attendance code **after** verification of the chosen identity contact. It preserves the original answers and participant details. Retrying completion does not create another registration or send another confirmation.
- Public optional, unverified contact snapshots cannot authorize recovery.
- Different names, omitted middle names, similar spellings, conflicting birthdays, and multiple exact candidates stay separate and active. Shared family contacts alone never cause a merge or a hold.
- Missing birthdays retain compatibility with legacy identities. Names plus a shared verified contact cannot prove identity perfectly; uncertain matches are intentionally not merged. This policy does not claim to eliminate every real-world duplicate.
- Running and Church participant pools remain separate. Cancelled, removed, and duplicate registrations cannot be recovered as active registrations.
- Staff and paper intake reject an exact event repeat with a validation message and continue to accept uncertain identities separately. Check-in uses the existing registration.

## Implementation

`Participant::resolveFrom()` uses a database transaction and a stable lock row per participant pool. This serializes lookup/create even when there are no matching participants yet. Matching reads use row locks to see the latest committed identity under MySQL repeatable-read isolation. Public finalization also locks the event, challenge, and participant pool. Event/participant and event/attendance-code uniqueness constraints remain in place.

All event intake paths create confirmed registrations without a manual hold decision. Registration model events record admission and participant reassignment in `registration_identity_decisions`; recovery and legacy transitions record explicit decisions. Each entry includes the rule version, reason, participant/pool, candidate evidence, timestamp, and resulting status. Legacy transitions additionally retain before/after snapshots. Historical manual-review records remain untouched.

There is no destructive participant merge. Similarity scores are advisory evidence only and never decide identity. The retired manual-resolution endpoint returns HTTP 410.

## Deployment and existing holds

1. Back up the database and deploy during a maintenance window so new code never runs against the old schema.
2. Run `php artisan migrate --force`.
3. Run `php artisan registrations:resolve-held --dry-run` to count pending legacy holds.
4. Run `php artisan registrations:resolve-held`. Exact duplicates remain as terminal duplicate records. Uncertain holds become active separate records; existing attendance is retained. Repeating the command is safe. Released records with no attendance code receive one.
5. Build assets with `npm run build` and resume service. Ensure the normal Laravel scheduler runs; the automatic resolver is also scheduled every minute without overlap.

The resolver may send the normal confirmation to newly released registrations using the configured mail transport. Local testing uses log mail. A malformed cross-pool legacy record causes an explicit error and transaction rollback rather than an unsafe link.

The automated suite uses SQLite, so it verifies behavior, rollback and retry outcomes but does not simulate MySQL row-lock contention. The implementation uses database locks and existing unique constraints; concurrent production-load testing remains a separate deployment check.
