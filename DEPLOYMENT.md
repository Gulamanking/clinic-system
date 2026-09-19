# Deployment

The app is a plain PHP application with a static frontend. Serve the **repository
root** as the document root: `index.html` is the frontend, and `js/api.js`
resolves the API as `backend/index.php` relative to the page, so no build step
and no API base URL configuration are needed.

Requirements:

- PHP 7.4+ with `pdo_mysql`, `openssl`, and `mbstring`
- Apache with `mod_rewrite` (the API's pretty routes come from
  [backend/.htaccess](backend/.htaccess); without it the app still works via
  `?route=` query parameters)
- MySQL 5.7+ / MariaDB

The schema is created automatically on the first request, so there is no
migration step to run — point the app at an empty database and load it once.

## Configuration

Configuration is resolved in [backend/config.php](backend/config.php), lowest
precedence to highest:

1. dev defaults in `config.php`
2. `backend/config.local.php` — committed XAMPP defaults, for local development
3. `backend/config.secret.php` — gitignored, for local API keys
4. **environment variables — these override everything**

Point 4 is what production uses. `config.local.php` ships in every checkout
with `db_host = 127.0.0.1` and `db_user = root`; because the environment wins,
a deployed copy of that file is inert.

### Environment variables

| Variable | Required in production | Notes |
| --- | --- | --- |
| `APP_ENV` | yes | Set to `production`. This turns on the startup check below. |
| `DB_DRIVER` | yes | `mysql` |
| `DB_HOST` | yes | From the hosting provider's database panel |
| `DB_PORT` | no | Defaults to `3306` |
| `DB_NAME` | yes | |
| `DB_USER` | yes | |
| `DB_PASSWORD` | yes | |
| `JWT_SECRET` | yes | Unique random string. Signs login tokens. |
| `ENCRYPTION_KEY` | yes | Unique random string. Encrypts diagnosis fields at rest. |
| `ADMIN_PASSWORD` | yes | Password for the bootstrap admin account |
| `ALLOWED_ORIGINS` | recommended | Comma-separated origins allowed to call the API, or `*`. Set this to your own domain. |
| `GEMINI_API_KEY` | no | AI features degrade gracefully when unset |
| `GEMINI_MODEL` | no | Defaults to `gemini-3.6-flash` |
| `AI_RATE_LIMIT_MAX` | no | Defaults to `10` |
| `AI_RATE_LIMIT_WINDOW_MINUTES` | no | Defaults to `60` |

Generate the secrets with something like:

```
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### The production startup check

With `APP_ENV=production`, the app refuses to serve any request while
`JWT_SECRET`, `ENCRYPTION_KEY`, or `ADMIN_PASSWORD` is unset or still holding
its development default, and returns a 500 naming the offending variables. The
development values are committed to a public repository, so a deployment using
them could be logged into by anyone who can read the repo. If you see that
error, the fix is to set those three variables — not to remove the check.

## First run

1. Create an empty MySQL database and a user with full rights on it.
2. Set the environment variables above.
3. Deploy, then load the site once — the schema is created on that request.
4. `POST /backend/index.php/seed` creates the `admin` account using
   `ADMIN_PASSWORD`. It only does anything while the users table is empty.
5. Log in as `admin`, then create the real staff accounts.

## Local development

Nothing above applies locally. Clone, start Apache and MySQL in XAMPP, and open
the project — `config.local.php` already has working defaults and `APP_ENV`
stays `development`. See [README.md](README.md).

## HostForge

The app runs as a **single application** serving both the frontend and the API
from one origin. The frontend calls the API as `backend/index.php?route=<name>`
(see [js/api.js](js/api.js)), so a single origin means no CORS and no API base
URL to configure.

### Build Configuration

| Field | Value |
| --- | --- |
| Build system | `buildpacks` (runtime auto-detects as `php`) |
| Root directory | `.` — the repository root, **not** `backend` |
| Start command | `php -S 0.0.0.0:8000 -t . router.php` |
| Port | `8000` |
| Health check path | `/` |

### Why router.php is not optional

PHP's built-in server does not read `.htaccess`, so the rules in
`backend/.htaccess` have no effect on HostForge. Without
[router.php](router.php) the built-in server serves the entire repository as
static files — including `backend/clinic_system` (a SQLite database), the SQL
seed scripts, the CSV import templates and the design PDF.

`router.php` is an allow-list: `/`, the two HTML pages, `/js/*.js`,
`/assets/favicon.svg` and `/backend/index.php`. Everything else is a 404. Add
new frontend assets to that list or they will not be served.

### ENCRYPTION_KEY — do not rotate casually

Diagnosis fields are encrypted at rest with `encryption_key` and stored with an
`enc:` prefix. Changing this value on a database that already holds encrypted
rows makes those rows permanently unreadable. Rotating it requires decrypting
with the old key and re-encrypting with the new one first. `JWT_SECRET` is safe
to rotate at any time — it only invalidates existing logins.

### Database

The managed MySQL instance is MariaDB, reachable from applications in the same
workspace at its `*.internal` hostname and port. Take `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER` and `DB_PASSWORD` from the Databases page and set them as
application environment variables, marking the password as **Secret**.

### Order of operations

Set the environment variables **before** redeploying. Deployments are labelled
`production`; if `APP_ENV=production` reaches the container before the secrets
do, the startup check will refuse to serve and return a 500.

## The frontend cache buster

`index.html` and `enrollment.html` load the JavaScript with a `?v=` query
string. The host serves `js/*.js` with `Cache-Control: public,
max-age=2592000, immutable`, and `immutable` means the browser will not
revalidate for thirty days — an ordinary refresh does not help, only a hard
reload. The only thing that makes a browser fetch the new file is the URL
changing.

That value is **not** a number anyone maintains. It is a hash of the contents
of the scripts themselves, stamped by:

```
php tools/stamp-asset-version.php          # rewrite and report
php tools/stamp-asset-version.php --check  # exit 1 if stale, write nothing
```

It changes exactly when the JavaScript changes and never otherwise, so running
it repeatedly is a no-op.

A `pre-commit` hook runs it automatically whenever `js/api.js` or `js/app.js`
is part of a commit. Hooks are not copied by `git clone`, so **enable it once
per clone**:

```
git config core.hooksPath .githooks
```

Without that the hook does not run and the version can go stale again. Use
`--check` in CI if you want a hard guarantee.

This is not hypothetical: the value sat at `38` from the first commit until
September 2026, so every JavaScript change the project shipped reached only
the people who happened to hard-reload.

## Appointment notifications

Spec Module 4 calls for email/SMS notification on booking, confirmation and
cancellation. Mail is sent over SMTP from a socket rather than through PHP's
`mail()`, which needs a local MTA that a container does not have.

| Variable | Notes |
| --- | --- |
| `MAIL_HOST` | SMTP server. **Leaving this unset disables notifications**; the app still works. |
| `MAIL_PORT` | Defaults to `587` |
| `MAIL_USERNAME` | Omit for a server that does not authenticate |
| `MAIL_PASSWORD` | Mark as **Secret** |
| `MAIL_FROM` | Sender address. Required alongside `MAIL_HOST`. |
| `MAIL_FROM_NAME` | Defaults to `School Clinic` |
| `MAIL_ENCRYPTION` | `tls` (STARTTLS, the default), `ssl` (implicit), or `none` |
| `SMS_GATEWAY_DOMAIN` | Optional. See below. |

Notifications never block the clinic: a booking still succeeds when the mail
server is unreachable or unconfigured, and every attempt is written to the
audit trail as `notification.booked`, `notification.confirmed` or
`notification.cancelled` with the per-recipient outcome. If notifications seem
to be missing, that audit entry says exactly why — `not_configured`,
`no_recipient_on_file`, `auth_failed` and so on.

### SMS without a second provider

Setting `SMS_GATEWAY_DOMAIN` to a carrier's email-to-SMS domain makes the
patient's contact number a second recipient, addressed as
`<digits>@<gateway>`. That covers the spec's SMS requirement through the same
SMTP connection instead of a separate gateway account.

### Recipients

Notifications go to the `email` recorded on the student or staff member. A
patient with no email on file produces a `no_recipient_on_file` audit entry
rather than an error, so it is worth checking that field is being filled in.
