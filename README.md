# MantisBT – Open Ticket Reminder

**English** · [Deutsch](README.de.md)

A MantisBT plugin that reminds users about their **open tickets**.

* **Weekly digest** of every open issue for a user – by default **once a week,
  Mondays at 09:00** (weekday and time freely configurable).
* **Optional per-ticket reminders** at a fixed interval
  (e.g. “remind me about this open ticket every X days”).
* **Configurable per user** – everyone can override the administrator’s global
  defaults or switch the reminders off entirely.
* **Per-project filtering** – exclude whole projects that don’t need reminders,
  globally and additionally per user.
* **Nicely formatted HTML mails** (with a plain-text fallback) or plain-text
  mails if you prefer.
* **Multilingual** – English, German, French, Spanish and Italian, picked
  automatically from each recipient’s MantisBT language (falls back to English).

---

## Features in detail

### 1. Weekly digest
A single e-mail listing all of the user’s open tickets. Configurable:

| Option | Default | Description |
| --- | --- | --- |
| Weekday | Monday | Which day the digest is sent |
| Hour | 09:00 | Hour of the day to send (0–23) |
| Assigned tickets | on | Tickets assigned to me |
| Reported tickets | off | Tickets I reported |
| Monitored tickets | off | Tickets I monitor |
| Skip empty digest | on | Send nothing when there are no open tickets |

### 2. Per-ticket reminders
Reminds about individual open tickets at a fixed interval. Configurable:

| Option | Default | Description |
| --- | --- | --- |
| Enabled | off | Turn single-ticket reminders on |
| Interval | 7 days | Remind about a ticket at most every N days |
| Only if untouched | 0 | Only tickets with no activity for N days (0 = always) |

### 3. Other useful features (already included)

* **Overdue marking** – tickets past their `due_date` are flagged with
  `[OVERDUE]` in the mail.
* **Prioritised sorting** – highest priority first, then the longest-untouched
  tickets on top.
* **“Last updated X days ago”** per ticket so stale items stand out.
* **Anti-spam logic** – a log table records what was already sent, so an hourly
  cron never produces duplicate mails.
* **Per-user overrides** – global admin defaults plus personal settings under
  *My Account → Reminder settings*.
* **Per-recipient language** – the mail uses the recipient’s language.
* **Two cron options** – a CLI script *or* a token-protected web endpoint for
  hosts without shell access.

Possible future additions are noted in [CHANGELOG.md](CHANGELOG.md).

---

## Installation

1. Get the plugin – either download the latest release archive from the
   [Releases page](https://github.com/marcwoge/mantisBT-Reminder/releases) or
   copy the **`Reminder/`** folder from this repository.

2. Place the folder into your MantisBT plugins directory:

   ```
   <mantisbt>/plugins/Reminder/
   ```

   > Important: the plugin folder must be named exactly `Reminder` (not
   > `mantisBT-Reminder`), otherwise MantisBT will not find the plugin class.

3. Sign in to MantisBT as administrator →
   **Manage → Manage Plugins** → install *Open Ticket Reminder*.

4. Under **Manage → Manage Plugins → Open Ticket Reminder** set the global
   defaults (weekday, time, intervals …).

5. Set up the cron dispatch (see below). **Without cron no reminders are
   sent** – MantisBT does not ship its own scheduler.

---

## Setting up cron

The plugin decides on its own *when* each mail is due, so you only need to
trigger the dispatcher **hourly** – ideally on the hour.

### Option A – command line (recommended)

**Linux / crontab** (`crontab -e`):

```cron
0 * * * * php /path/to/mantisbt/plugins/Reminder/cron/reminder_cron.php >/dev/null 2>&1
```

**Windows – Task Scheduler:**

* Program/script: `C:\xampp\php\php.exe` (path to your `php.exe`)
* Arguments: `D:\mantisbt\plugins\Reminder\cron\reminder_cron.php`
* Trigger: daily, repeat every 1 hour

Send the digest immediately for a test (ignores weekday/hour):

```bash
php plugins/Reminder/cron/reminder_cron.php force-digest
```

### Option B – web endpoint (for hosts without a shell)

1. Set a **web cron token** (a secret string) on the plugin configuration page.
2. Have an external scheduler / uptime service call this URL hourly:

   ```
   https://your-mantis/plugin.php?page=Reminder/cron&token=YOUR_TOKEN
   ```

Without a valid token the endpoint replies with `403 Forbidden`.

---

## Per-user settings

Every user finds their personal options under **My Account → Reminder
settings**. Values set there override the global admin defaults; everything
else keeps inheriting the global defaults.

## Project filtering

Some projects simply don’t need reminders. You can exclude them:

* **Globally (admin)** – on the plugin configuration page, select the projects
  to exclude. This is the **default pre-selection** for every user.
* **Per user** – on *My Account → Reminder settings*, each user can freely adjust
  their own selection (add or remove projects), **including re-enabling** a
  project the administrator excluded by default.

If a user has not customised their selection, the global default applies. Once
they save their own selection, that personal choice is used.

---

## E-mail format (HTML)

By default the plugin sends **HTML mails** with a small overview table (overdue
tickets marked red), including a plain-text alternative as a fallback. Switchable
globally and per user via the *E-mail format* option (HTML / plain text).

> Note: HTML mails are sent directly through MantisBT’s SMTP/mail settings
> (PHPMailer). If that fails or PHPMailer is unavailable, the plugin
> automatically falls back to MantisBT’s normal plain-text mail queue.

---

## Languages

The plugin ships with translations for **English, German, French, Spanish and
Italian**. MantisBT automatically uses each recipient’s configured language and
falls back to English for anything missing.

Adding another language is easy: copy `Reminder/lang/strings_english.txt` to
`strings_<language>.txt` (matching the MantisBT language name) and translate the
values. Pull requests with new or improved translations are very welcome.

---

## Tests

The core logic (schedule decision, interval/staleness check, e-mail rendering)
is covered by unit tests that run **without MantisBT, without a database and
without a web server** – the required MantisBT functions are replaced by stubs
([tests/stubs.php](tests/stubs.php)).

**You do not need to install MantisBT for this.** Two ways:

1. **Automatically via GitHub Actions** – the tests run on PHP 7.4–8.3 on every
   push (see [.github/workflows/tests.yml](.github/workflows/tests.yml)), so you
   need nothing locally.

2. **Locally** – only a PHP CLI is required (no MantisBT):

   ```bash
   php tests/run_tests.php
   ```

   Without a local PHP you can use Docker:

   ```bash
   docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tests/run_tests.php
   ```

The runner prints `Passed: N  Failed: 0` and returns exit code 1 on failure
(CI-friendly).

---

## Trying it with Docker (no MantisBT installation needed)

The [`docker/`](docker/) folder contains a fully working test environment
(MantisBT + MariaDB + Mailpit as a mail catcher), with the plugin already
mounted.

```bash
docker compose up -d --build
```

* MantisBT: http://localhost:8989 (`administrator` / `root`)
* Mailpit (shows every e-mail): http://localhost:8025

Trigger a test dispatch:

```bash
docker compose exec mantis php plugins/Reminder/cron/reminder_cron.php force-digest
```

The full step-by-step walkthrough is in [docker/README.md](docker/README.md).

---

## Compatibility

* MantisBT **2.0.0** or newer
* PHP 5.6+

## License

[MIT License](LICENSE) © 2026 Marc-Philipp Woge.

This applies to the plugin’s own code. MantisBT itself is licensed separately
under the GPL; the MIT-licensed plugin is permissive and works fine alongside
it.
