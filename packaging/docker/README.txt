Death Plan Switch with Docker
=============================

Works with Docker Compose, Synology Container Manager, Portainer, TrueNAS,
Unraid and CasaOS.

1. Copy .env.example to .env and edit it (time zone, port, optional
   HEARTBEAT_URL).
2. Start it:

       docker compose up -d

3. Open http://<this machine>:8080/install.php and finish the setup.

The compose file pins the image to the exact version of this release. To
update, download the newer release and replace docker-compose.yml. Your data
stays in the ./data folder next to it; keep that folder private and back it up.

Put an HTTPS proxy in front before exposing the switch to the internet. See
docs/pl/instalacja.md in the repository.
