# WDEMS — Live Email Demo

**Audience:** Whoever runs the demo. Written for a terminal-driven
workflow with no assumptions about Laravel familiarity.

**Goal:** Show the full authentication gate end to end using real email
delivery through Resend — verification link, then 2FA code, then
dashboard.

**Time to run:** 3–5 minutes if Resend and Gmail are already set up.

---

## 1. The demo account

| Field | Value |
|---|---|
| Email | `pomasinejboy@gmail.com` |
| Password | `TaZuna@440` |
| Role | `staff` |
| Verified | **no** (on purpose) |
| 2FA | **on** (on purpose) |

This account is created by `database/seeders/DemoUsersSeeder.php`.
It must be unverified for the demo to show both gates. If it has
already been verified in a previous demo, reset it — see §6.

**The email address must be the same one registered at resend.com.**
Resend's free tier only delivers to the account owner while the app is
in sandbox mode. Sending to a different address will fail silently.

---

## 2. Prerequisites

- WDEMS running locally (`composer run dev`)
- A Resend account with the API key
- `.env` configured for Resend — see §3
- Browser with Gmail logged in as `pomasinejboy@gmail.com`

---

## 3. Configure `.env` for live email

Three values must be set. Run these to check the current state — no
values are printed, only keys:

    cd ~/WDEMS
    grep -E "^(MAIL_MAILER|MAIL_FROM_ADDRESS)=" .env
    grep -c "^RESEND_API_KEY=" .env

Expected output:

    MAIL_MAILER=resend
    MAIL_FROM_ADDRESS="onboarding@resend.dev"
    1

If `MAIL_MAILER` is `log`, change it. The line number varies by `.env`
file — find it first, then edit line-anchored:

    grep -n "^MAIL_MAILER=" .env
    # Suppose it prints "50:MAIL_MAILER=log"
    sed -i '50s/^MAIL_MAILER=.*$/MAIL_MAILER=resend/' .env

Then clear config cache. **Never run `config:cache` in local dev** —
see `docs/AI-CONTEXT.md` §13 for why.

    php artisan config:clear

Confirm:

    php artisan tinker --execute="echo config('mail.default');"

Expected output: `resend`. If it prints `log`, the change did not take.
Check `grep -n "^MAIL_MAILER=" .env` again.

**One-time transport check.** Verify the Resend package is installed:

    grep resend composer.json

Expected: a line containing `"resend/resend-laravel"`. If missing, the
mailer will error at runtime — run `composer require resend/resend-laravel`.

---

## 4. Prepare the demo account

The seeder is idempotent — it creates the account if missing, skips it
if present. It never overwrites an existing password or verification
state.

    php artisan db:seed

Expected output:

    · admin@wdems.test — already exists, skipped.
    · pomasinejboy@gmail.com — created.
    Demo users seeded (or already present).

If the account already existed and was verified in a previous demo,
reset it first — see §6.

---

## 5. The demo flow

Open the app at the local URL (`http://127.0.0.1:8000` by default).

### Step 1 — Log in

URL: `/login`

- Email: `pomasinejboy@gmail.com`
- Password: `TaZuna@440`
- **Leave "Remember me" unticked.** It is a login-session flag, not a
  device-trust flag. Ticking it does not affect the 2FA flow.

Click **Log In**.

**Expected:** redirect to `/email/verify`. The staff user is unverified
so the `verified.or.admin` middleware holds them at the email gate.
No 2FA challenge yet.

### Step 2 — Send the verification email

On `/email/verify`, click **Resend verification email**.

**Expected:**
- Success banner: "A new verification link has been sent to the email
  address you provided during registration."
- New row appears in the Resend dashboard within seconds
- New email arrives in Gmail

**Deliverability note:** the verification email lands in **Spam** the
first time it is sent to a fresh address. Gmail has learned that
`onboarding@resend.dev` sends link-emails that look like bulk mail. See
§7 for how to redirect it to Inbox.

### Step 3 — Click the verification link

Open Gmail. Find the email from `onboarding@resend.dev`, subject
**"Verify your email address"**. Check the Spam folder if it is not in
Inbox. Click the button or link inside.

**Expected:** redirect to `/verify-device`. The email gate now passes,
and the device gate activates because the browser has no trusted-device
cookie yet.

### Step 4 — Send the 2FA code

On `/verify-device`, the page auto-sends the OTP code on first load.
No click needed.

**Expected:**
- "Sending…" appears briefly
- New email arrives within seconds
- The email subject is **"WDEMS — Login Verification Code"**
- This email lands in **Inbox** (unlike the verification link)

### Step 5 — Enter the code

Copy the 6-digit code from the email. Enter it on `/verify-device`.

**Expected:** redirect to `/dashboard`. Full session active.

### Step 6 — Confirm device trust

Reload `/dashboard` once (F5).

**Expected:** still on `/dashboard`. No prompt. No new email.

Then log out, log in again with the same credentials.

**Expected:** straight to `/dashboard`. No verification prompt, no 2FA
challenge. The trusted-device cookie from step 5 is still valid.

---

## 6. Reset for a repeat demo

Once the demo has been run, the account is verified and the browser is
trusted. To demo the full flow again, reset both:

    cd ~/WDEMS
    php artisan tinker --execute="
    \$u = \App\Models\User::where('email', 'pomasinejboy@gmail.com')->first();
    \$u->forceFill([
        'email_verified_at' => null,
        'trusted_devices' => null,
    ])->save();
    echo 'reset' . PHP_EOL;
    "

Then **clear the browser cookie** `wdems_trusted_device` for
`127.0.0.1`. In Brave/Chrome: DevTools → Application → Cookies →
`http://127.0.0.1:8000` → delete `wdems_trusted_device`.

If you skip the cookie step, step 3 will skip the 2FA challenge because
the browser is still trusted.

---

## 7. Deliverability — the Spam folder problem

The two emails behave differently in Gmail:

| Email | Sender | Typical landing |
|---|---|---|
| Verify your email address (contains a link/button) | `onboarding@resend.dev` | **Spam** |
| WDEMS — Login Verification Code (plain code) | `onboarding@resend.dev` | **Inbox** |

Gmail classifies link-emails from shared sandbox senders more
aggressively. This is a heuristic on Gmail's side, not a bug in WDEMS.

**One-time fix per recipient:**

1. Open the verification email in Gmail
2. Click **Report not spam** at the top of the message
3. Future sends to the same Gmail address will land in Inbox

**Permanent fix (out of scope here):** verify a custom domain at
resend.com and set `MAIL_FROM_ADDRESS` to something like
`noreply@yourdomain.com`. This requires DNS access and a domain.

For a one-off demo, the "Report not spam" click is enough. Tell the
audience the first email may land in Spam so they know to look.

---

## 8. Fallback — demo without Resend

If Resend is unavailable, rate-limited, or the network is down, switch
to the log mailer. Emails are not delivered — they are written to
`storage/logs/laravel.log`.

    cd ~/WDEMS
    grep -n "^MAIL_MAILER=" .env
    # Suppose it prints "50:MAIL_MAILER=resend"
    sed -i '50s/^MAIL_MAILER=.*$/MAIL_MAILER=log/' .env
    php artisan config:clear

Then in a second terminal:

    cd ~/WDEMS
    tail -f storage/logs/laravel.log

Run the demo. Instead of checking Gmail, read the emails directly from
the log stream. **The verification URL and the 6-digit code are both in
plain text** in the log entries. Copy the URL into the browser to verify
email. Copy the code into the 2FA form.

**Do not commit the log-mailed codes anywhere.** They are test-only.

To restore Resend afterward:

    sed -i '50s/^MAIL_MAILER=.*$/MAIL_MAILER=resend/' .env
    php artisan config:clear

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Login: "These credentials do not match our records" | Account missing or password changed | `php artisan db:seed` recreates if missing; the seeder does **not** reset the password of an existing account. Delete and re-seed: `User::where('email', 'pomasinejboy@gmail.com')->delete()` then `db:seed` |
| Login succeeds but lands on `/dashboard` (skipping email verify) | The user is verified, or is an admin | Check `email_verified_at` in Tinker. If set, run §6's reset command |
| Login lands on `/email/verify` but "Resend" button does nothing | Rate limited (6 sends/hour default), or the mailer errored | Check `storage/logs/laravel.log` and the Resend dashboard |
| Verification link in email gives "Invalid signature" | The link was generated for an older user row (from before a database reset) | Send a fresh link — old links do not survive a `migrate:fresh` |
| 2FA code email never arrives | Resend accepted it but Gmail filtered it | Check Gmail Spam, then the Resend dashboard → Logs |
| Resend dashboard shows "Delivered" but nothing in Gmail | Gmail classified it as Spam / Promotions / Updates | Use Gmail search: `from:onboarding@resend.dev in:anywhere` |
| `config('mail.default')` still says `log` after `.env` change | Config cache is stale | `php artisan config:clear` — see `docs/AI-CONTEXT.md` §13 |
| `migrate:fresh` takes more than a minute | Another artisan process is holding MySQL | `ps aux | grep artisan` — kill all, then re-run |

---

## 10. What this demo proves

At the end of a successful run, you have demonstrated all four gates:

1. **Fortify password auth** — the login itself
2. **Email verification gate** — `verified.or.admin` middleware
3. **Device trust gate** — `EnsureDeviceIsTrusted` middleware
4. **Email 2FA** — `EmailTwoFactorService` + Resend delivery

Plus: **session persistence across logout** — the trusted-device cookie
survives logout, so a returning user does not re-verify.

That is the complete authentication subsystem, verified live.

---

## 11. Cleanup after the demo

None required. The account persists intentionally — it is the standard
demo account. Reset it with §6 before the next demo.

To delete the account entirely:

    php artisan tinker --execute="
    \App\Models\User::where('email', 'pomasinejboy@gmail.com')->delete();
    echo 'deleted' . PHP_EOL;
    "

It will be recreated by `php artisan db:seed` when needed.
