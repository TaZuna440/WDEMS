# Google Integration — Setup Guide

WDEMS uses Google Forms as the participant-facing registration surface. This document walks through everything needed to get a working Google connection: Cloud project setup, OAuth credentials, environment variables, and the one-time per-event Sheet linkage.

For architecture details see `architecture.md`. For the chronological history of the integration, see `phase-a-b-report.md`.

---

## Prerequisites

- A Google account that will own the Forms and Sheets (personal Gmail works)
- Access to https://console.cloud.google.com
- WDEMS running locally at `http://127.0.0.1:8000`

The account that owns the Google Cloud project can be the same or different from the account that owns the Forms. WDEMS separates them: the Cloud project holds credentials, the connected account holds files.

---

## Step 1 — Create a Google Cloud project

1. Go to https://console.cloud.google.com
2. Click the project dropdown at the top → **New Project**
3. Name it `wdems` (or anything)
4. Note the **Project ID** — something like `wdems-509007`
5. Click **Create** and select the new project

---

## Step 2 — Enable the required APIs

From the sidebar → **APIs & Services** → **Library**. Search for and enable each of these three:

    Google Forms API
    Google Sheets API
    Google Drive API

No billing account is required. All three are free tier and remain free at WDEMS's expected usage (a few API calls per event).

**Optional but recommended:** enable the **Google Apps Script API** too. WDEMS's A2 approach doesn't call it directly, but enabling it avoids a permission prompt if you ever upgrade to programmatic deployment.

---

## Step 3 — Configure the OAuth consent screen

From the sidebar → **Google Auth Platform** (formerly "OAuth consent screen").

### Branding

- **App name:** `WDEMS`
- **User support email:** your email
- **Developer contact:** your email

Save.

### Audience

- **User type:** External
- **Publishing status:** Testing
- **Test users:** add the Google account you'll use to connect WDEMS

**Important:** Google only allows connections from accounts listed as test users while the app is in Testing mode. If you skip this, the OAuth flow fails with "app blocked."

### Data Access (scopes)

Click **Add or remove scopes**. In the "Manually add scopes" box, paste this list:

    openid
    https://www.googleapis.com/auth/userinfo.email
    https://www.googleapis.com/auth/userinfo.profile
    https://www.googleapis.com/auth/forms.body
    https://www.googleapis.com/auth/forms.responses.readonly
    https://www.googleapis.com/auth/spreadsheets
    https://www.googleapis.com/auth/drive.file
    https://www.googleapis.com/auth/script.projects

Click **Add to table** → **Update** → **Save**.

`drive.file` is intentionally narrow: WDEMS can only see files it has created, not the user's whole Drive.

---

## Step 4 — Create OAuth credentials

From the sidebar → **Clients** → **+ Create client**.

- **Application type:** Web application
- **Name:** `WDEMS Local`
- **Authorized redirect URIs:** add both:

      http://127.0.0.1:8000/auth/google/callback
      http://localhost:8000/auth/google/callback

Click **Create**.

A popup appears with the **Client ID** and **Client Secret**. **Copy both immediately** — Google never shows the secret again.

---

## Step 5 — Configure WDEMS

Open `.env` and add:

    GOOGLE_CLIENT_ID=xxxxxxxxxxxx.apps.googleusercontent.com
    GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxx
    GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback

Then:

    php artisan config:clear

Verify:

    php artisan config:show services.google

You should see the three values echoed back. If the Client Secret shows as empty, the `.env` value didn't take — usually a stray quote or trailing space.

---

## Step 6 — Connect your account

1. Start the dev server: `composer run dev`
2. Log in to WDEMS
3. Navigate to `/settings/google`
4. Click **Connect Google Account**
5. Sign in as the test user you added in Step 3
6. Approve the requested scopes

You'll see one warning: **"Google hasn't verified this app"** — that's expected for a Testing-mode project. Click **Advanced** → **Go to WDEMS (unsafe)** → **Allow**. The "unsafe" label just means Google hasn't manually reviewed your app. It's your own client ID and your own account.

After approving, you're redirected back to `/settings/google` and see:

    ✅ Connected as your-email@gmail.com

If this fails, see the Troubleshooting section below.

---

## Step 7 — Create a Form for an event

1. Create or open an event
2. Move it to `configured` status
3. Click **Set Up Registration Form**
4. Click **Create Registration Form**

WDEMS creates a Google Form in the connected account's Drive, seeds it with default questions, publishes it, and stores the Form ID on the event's `registration_setup`.

You can now:

- Add, edit, and remove questions from WDEMS (they sync to Google in one batch per save)
- Click **Open Form in Google** to see what participants see
- Click **Edit Form in Google** to open the editor view

---

## Step 8 — Link the Form to a Sheet

The Google Forms API cannot link a Form to a Sheet. That must be done from the Forms UI or via an Apps Script bound to the Form.

WDEMS uses the **A2 approach**: the organizer pastes a small script once per event.

On the setup page you'll see a **Link Responses to a Sheet** panel. It walks through:

1. Click **Open Edit View** to open the Form in Google Forms editor
2. Click the **⋮ menu** (top-right) → **Apps Script**
3. Copy the snippet shown in the panel and paste it into the Script Editor
4. **Save** (💾) then **Run** (▶)
5. When Google asks for authorization, click **Advanced** → **Go to (unsafe)** → **Allow**
6. Return to WDEMS and click **Verify Sheet Link**

Verification calls Google's Forms API and reads the `linkedSheetId` field. If Google reports a link, WDEMS stores the Sheet ID and URL.

You'll know it worked when:

- The "Link Responses" panel is replaced by a **Linked Sheet** panel
- The Sheet URL appears with **Open Sheet** and **Copy URL** buttons
- A `sheet_linked` entry appears in the History panel

### Why not automate this?

Programmatic Apps Script deployment (via the Script API) is possible but complex: it requires creating the script project via API, deploying it, and installing the trigger — each step can fail for opaque reasons. The manual paste is 30 seconds per event and has been 100% reliable.

If the manual step ever becomes a pain point, WDEMS could be upgraded to programmatic deployment. The rest of the pipeline stays the same.

---

## Step 9 — Verify the pipeline works

Before relying on the Sheet, do one live test:

1. Copy the registration link from WDEMS (**Copy Registration Link** button)
2. Open it in an incognito window
3. Fill in the form and submit
4. Open the linked Sheet

You should see a new row with a timestamp, all your answers, and one column per question.

If yes, the pipeline works end-to-end. Phase D (importing these rows into WDEMS) can now be built on top.

---

## Resend email setup (optional)

WDEMS uses **Resend** for transactional email (OTP codes for staff event deletion). Setup is separate from Google.

1. Sign up at https://resend.com
2. Get an API key from the dashboard
3. Add to `.env`:

       MAIL_MAILER=resend
       MAIL_FROM_ADDRESS=onboarding@resend.dev
       RESEND_API_KEY=re_xxxxxxxxxxxx

4. `php artisan config:clear`

**Sandbox restriction:** the free tier's `onboarding@resend.dev` sender can only deliver **to the account owner's email**. To send to arbitrary recipients, verify a custom domain in Resend and change `MAIL_FROM_ADDRESS` to something like `noreply@yourdomain.com`.

**Local development alternative:** set `MAIL_MAILER=log` and OTP codes will appear in `storage/logs/laravel.log` instead of being sent.

---

## Troubleshooting

### "Access blocked: WDEMS has not completed the Google verification process"

The Google account you're signing in with isn't in the test users list.

**Fix:** Google Cloud Console → Google Auth Platform → Audience → Test users → add the account.

### "Google hasn't verified this app" (with no Go to option)

Same as above, but the Advanced → Go to link is missing. Happens when:
- The account isn't a test user
- Or the account is a Google Workspace account with admin restrictions

For Workspace accounts, ask your admin to allow the app, or use a personal Gmail.

### "Invalid redirect URI"

The redirect URI in Google Cloud Console doesn't match `GOOGLE_REDIRECT_URI` in `.env` byte-for-byte.

**Fix:** Both must be exactly `http://127.0.0.1:8000/auth/google/callback` (or the `localhost` variant — pick one and use it everywhere).

### "Google token refresh failed"

The refresh token was revoked — either because:
- You removed the app from https://myaccount.google.com/permissions
- The refresh token wasn't stored (missing `access_type=offline` — not an issue in current code)

**Fix:** Disconnect and reconnect at `/settings/google`.

### Verify Sheet Link returns "No Sheet is linked"

Either:
- The Apps Script hasn't run yet
- Or it ran but failed silently

**Fix:** Open the Form in Google Forms editor, open Script Editor, check the Execution log. You should see `Sheet linked: https://...`.

If you see `Exception: The form currently has no response destination` — the script hit the `getDestinationId()` exception. Use the current version of the snippet from WDEMS (it wraps that call in try/catch).

### "Class 'X' not found" during sync

Some Google API classes have unusual names or don't exist in your SDK version. The current WDEMS code has been verified against `google/apiclient` v2.19 and `google/apiclient-services` v0.459. If you upgrade either package, re-verify with:

    ls vendor/google/apiclient-services/src/Forms/

### Form ends up in the owner's trash

If you delete an event in WDEMS but the Form survives in Google, the Form may eventually get flagged as trash depending on Google's state. **Fix:** open Google Drive → Trash → Restore the Form. Long-term fix is on the roadmap (auto-delete or auto-recover on event deletion).

---

## Free tier limits

| Service | Free tier | Typical WDEMS usage |
|---|---|---|
| Forms API | 300 req/min per project | ~10 per form setup |
| Sheets API | 300 req/min per project | 0 (WDEMS builds Sheet URL directly) |
| Drive API | 12,000 req/min | ~2 per form |
| Apps Script | 20,000 fetches/day, 90 min runtime/day | ~1 per form |
| OAuth test users | 100 | 1–5 |

WDEMS stays under 1% of the free tier even with heavy use. No billing account needed.

---

## Reference

- Google Cloud Console: https://console.cloud.google.com
- Apps Script dashboard: https://script.google.com
- OAuth permissions view: https://myaccount.google.com/permissions
- Resend dashboard: https://resend.com

