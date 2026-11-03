# Changelog

## Unreleased

- Group channels: a shared place such as a team Slack channel or a friends' Discord server, with a message of the owner's own. It is sent only when the switch fires, once, through the same once-only steps as the contacts' messages (across nodes too), and never gets reminders, questions or vault packages. If the owner checks in after it went out, it gets a correction message, on by default and editable. The text may only use the owner's own placeholders ({owner_name}, {days}, {last_checkin_date}), never the contacts' details. It is filled in once, at the first attempt, and frozen, so every run and every node sends the same parts; one node at a time holds a delivery, and another takes over only when it stalls. A text longer than the platform takes (Discord 2,000 and Telegram 4,096, both in UTF-16 units; Slack 40,000, others 4,000 characters) goes as numbered parts, split between whole characters, each delivered once; a retry or another node starts at the first part still missing, for 72 hours. A "Send a test message" button checks the address, at most once a minute. Up to 50 group channels; secondaries copy the primary's, skipping any that is not valid.

- "Don't tell them in advance": a contact who is not the next of kin, not a trusted person and holds no vault packages can be left out of the invitation and the yearly reconfirmation. They count as ready without confirming, and their first message is the one sent when the switch fires.

- Trusted people: an ordered list replaces the single next of kin. The next of kin is still asked first; if nobody says all is fine, the next person is asked after an interval (automatic, spread over 12 hours, or a fixed number of minutes), or everyone at once after the first. Any "all fine" ends the cycle. The switch never fires before the last person has been asked and a reply window (6 to 72 hours, 24 by default) has passed. Contacts, Settings and the dashboard show the list and the times. Existing installs keep working: the next of kin is trusted person 1.

- New look for the owner's pages: a top bar with the state of the switch, a strip of tabs, and full-width content cut by hairlines instead of a sidebar and cards. On phones, a bottom bar and a "More" sheet. Archivo and IBM Plex Mono replace IBM Plex Sans. The palette now comes from the public page's own backgrounds, dark by default with a light theme; the choice (dark, light, system) is in Settings and in the "More" sheet. The sign-in page and the installer sit over the same picture as the public page.

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
