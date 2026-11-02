# Changelog

## Unreleased

- Trusted people: an ordered list replaces the single next of kin. The next of kin is still asked first; if nobody says all is fine, the next person is asked after an interval (automatic, spread over 12 hours, or a fixed number of minutes), or everyone at once after the first. Any "all fine" ends the cycle. The switch never fires before the last person has been asked and a reply window (6 to 72 hours, 24 by default) has passed. Contacts, Settings and the dashboard show the list and the times. Existing installs keep working: the next of kin is trusted person 1.

## 1.0.0, October 2026

First public release.

## 0.9.0, October 2026

First version, for testing ahead of public release.

- Installer: server checks, owner details, SMTP proved by an emailed code, crontab line with the detected PHP path and user, confirmation of the first run.
- Weekly check-in by email. A button press is the check-in; opening the link is not.
- Escalation: reminders at 24 and 48 hours, next of kin asked at 72 hours and reminded at 120, other contacts told at 144.
- Next of kin answers "All fine" or "Something's wrong".
- False-alarm notices to anyone already contacted when the owner checks in.
- Contacts confirm by email, reconfirm yearly, and carry editable role labels and messages.
- Pause for up to 28 days, ending automatically.
- Yearly prompt to review what each contact holds.
- Flat-file storage with locking, atomic writes and a backup copy of each file.
- Daily test that the data folder cannot be read from the web.
- Secret trigger address for hosting without cron, and a health address for uptime monitors.
