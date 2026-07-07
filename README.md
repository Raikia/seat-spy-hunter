# SeAT Spy Hunter

Spy Hunter is a read-only SeAT plugin for finding accounts that deserve a closer look.

It is not meant to convict anyone by itself. The goal is to pull together a lot of small signals that directors usually check by hand, score them consistently, and show the supporting evidence in one place.

## What It Looks At

Spy Hunter scores SeAT user accounts, not individual characters. If a SeAT account has several linked characters, the plugin looks across all of those characters and produces one account-level report.

Current signals include:

- Contacts, EVE mail, and wallet activity involving characters, corporations, or alliances marked hostile in Spy Hunter settings.
- Hostile contact direction, so outbound touches can be treated differently from passive appearances.
- Shared SeAT login IPs between different user accounts.
- Shared browser or device fingerprints from the SeAT security log.
- VPN, proxy, Tor, hosting, or Apple Private Relay style IP intelligence.
- Low total skillpoints across monitored characters.
- New characters, low SP for character age, and thin skill history.
- Few linked characters on the SeAT account.
- Missing, deleted, stale, or incomplete SeAT token footprint.
- Missing connector footprint from plugins such as seat-connector.
- Little or no PvE, industry, market, or wallet history.
- Limited recent wallet activity.
- Wallet balance that barely moves.
- Low or missing assets.
- Assets found around hostile or risky locations.
- Recent hostile or neutral corporation history.
- Unusual corporation churn, based on unique corporations and character age.
- Employment-history overlap with hostile-group members, including whether the characters were in the same corporation at the same time.
- Review status, notes, and false-positive suppressions so teams can work the queue without fighting the same known exceptions forever.

## Employment Overlap

Spy Hunter uses EveWho only to discover who is currently in configured hostile corporations or alliances.

After it finds those hostile members, it queues SeAT's normal ESI character jobs. Corporation-history comparison is then done from SeAT's own `character_corporation_histories` data.

That means the useful chain is:

1. Configure hostile corporations or alliances in Spy Hunter settings.
2. Let the EveWho queue discover current hostile members.
3. Let SeAT's normal ESI jobs pull corporation history for those members.
4. Refresh Spy Hunter reports.
5. Review any employment overlap evidence.

Same-time overlap is scored more heavily than historical-only overlap. In the account modal, the evidence table shows the monitored character, hostile character, shared corporation, both date ranges, and whether the dates overlap.

## VPNAPI.io

Spy Hunter can enrich login IPs with [VPNAPI.io](https://vpnapi.io/).

Set your VPNAPI.io key in Spy Hunter settings. The plugin queues public IPs from SeAT login history and looks up missing cache entries. Results are cached indefinitely, because the answer for an IP is usually good enough to keep and there is no reason to spend API calls on the same address over and over.

The free VPNAPI.io plan allows 1,000 IP lookups per day. If the API limit is hit, Spy Hunter stops processing VPN lookups until the next UTC day and continues using whatever is already cached.

Manual IP intelligence entries can also be added from the settings page.

## Pages

The plugin adds these main areas:

- Dashboard: account-level risk list for monitored corps and alliances.
- Account review modal: evidence, linked characters, connectors, login IPs, review status, suppressions, and detailed match context.
- Settings: monitored groups, hostile entities, score weights, ignored characters, VPNAPI.io settings, and queue status.
- Caches: VPNAPI.io/manual IP cache and EveWho hostile member cache, with delete buttons for forcing future re-cache.

## Installing In SeAT

Install the package from your SeAT root:

```bash
composer require raikia/seat-spy-hunter
php artisan migrate
php artisan db:seed --class="Raikia\\SeatSpyHunter\\Database\\Seeders\\ScheduleSeeder"
php artisan optimize:clear
```

Then restart the SeAT services you normally run for web, workers, and scheduler.

For Docker-based SeAT setups, that usually looks like:

```bash
docker compose restart front
docker compose restart worker
docker compose restart scheduler
```

After install, assign the Spy Hunter permissions to the right SeAT role:

- `seat-spy-hunter.view` lets a user view the dashboard and reports.
- `seat-spy-hunter.settings` lets a user change settings, entities, queues, and caches.

## Installing In This Local Dev Environment

This repository uses `packages/override.json` for local package loading. Add the plugin namespace and provider:

```json
{
    "autoload": {
        "Raikia\\SeatSpyHunter\\": "packages/seat-spy-hunter/src/"
    },
    "providers": [
        "Raikia\\SeatSpyHunter\\SeatSpyHunterServiceProvider"
    ]
}
```

Then restart the SeAT containers:

```bash
docker compose restart front
docker compose restart worker
docker compose restart scheduler
```

## Scheduled Jobs

The plugin ships a schedule seeder for three commands:

- `seat-spy-hunter:refresh`
- `seat-spy-hunter:vpn-lookup --limit=1000`
- `seat-spy-hunter:evewho-sync --limit=10`

The default intent is:

- Refresh reports every two hours.
- Process VPNAPI.io lookups once per day, shortly after the UTC reset.
- Process EveWho hostile member pages every five minutes.

You can also run the commands manually:

```bash
php artisan seat-spy-hunter:refresh
php artisan seat-spy-hunter:vpn-lookup --limit=1000
php artisan seat-spy-hunter:evewho-sync --limit=10
```

In Docker:

```bash
docker compose exec front php artisan seat-spy-hunter:refresh
docker compose exec front php artisan seat-spy-hunter:vpn-lookup --limit=1000
docker compose exec front php artisan seat-spy-hunter:evewho-sync --limit=10
```

## First Setup

After installing:

1. Open Spy Hunter settings.
2. Add the corporations or alliances you want monitored.
3. Add hostile corporations, alliances, or characters.
4. Add a VPNAPI.io key if you want automatic VPN/proxy checks.
5. Review the score weights and tune them for your group.
6. Let the scheduled queues run for a while.
7. Refresh reports and start reviewing the dashboard.

The first few runs may look quiet while SeAT gathers ESI history for hostile members discovered through EveWho. That is normal.

## Notes

Spy Hunter is intentionally read-only. It does not remove roles, kick users, send notifications, or make access decisions.

Use it as a triage tool. A high score means "look here first," not "this person is guilty."
