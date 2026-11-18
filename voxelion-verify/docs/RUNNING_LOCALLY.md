# Running Locally

Step-by-step guide to get Voxelion Verify running on your machine.
Commands are written for **Windows PowerShell** — each runs on its own line
(`&&` chaining is not supported in Windows PowerShell 5.x; on bash/PowerShell 7+
you may chain them).

## 1. Prerequisites

| Tool | Version | Check |
|---|---|---|
| PHP | 8.2+ with `mbstring`, `openssl`, `pdo_sqlite`, `gd`, `curl` | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 20+ (with npm) | `node -v` |

No database server, Redis, or other services are needed — the app uses SQLite
and local drivers out of the box.

## 2. Install dependencies

From the project root:

```powershell
cd voxelion-verify
composer install
npm install
```

## 3. Configure the environment

```powershell
cp .env.example .env
php artisan key:generate
```

The defaults in `.env.example` work locally as-is (SQLite, `sync` queue, file
cache). Optional, for the reviewer console:

```
ADMIN_USERNAME=reviewer
ADMIN_PASSWORD=choose-a-password
```

## 4. Create the database and dev keys

```powershell
php artisan migrate
php artisan voxelion:keys
```

- `migrate` creates the SQLite database at `database/database.sqlite`
  (create the file first if it doesn't exist: `New-Item database/database.sqlite`).
- `voxelion:keys` generates a dev RS256 keypair in `storage/app/keys/` used to
  verify hand-off tokens locally.

## 5. Build frontend assets

```powershell
npm run build
```

For active frontend development use `npm run dev` instead (Vite HMR).

## 6. Start the app

```powershell
php artisan serve
```

The app is now at `http://127.0.0.1:8000`.

> **One-command alternative:** `composer dev` runs the server, queue listener,
> log tail, and Vite together via `concurrently`.

## 7. Walk the journey (local demo)

1. Open **`http://127.0.0.1:8000/dev/handoff`** — a local-only OpinionPadi
   simulator that signs a one-time JWT and redirects into `/start`.
   Optional params: `?volunteer=vol-42&task=OP-2026-1234`.
   (This route only exists when `APP_ENV=local`.)
2. Complete the journey: **consent → intake → photo → live check → result**.
3. The completion code is shown once on `/result`.
4. Validate it the way OpinionPadi's server would. Calls are HMAC-signed —
   `X-OpinionPadi-Signature` is `HMAC_SHA256("<ts>.<body>",
   OPINIONPADI_API_SECRET)`:

   ```powershell
   $ts = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
   $hmac = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes("op-dev-secret"))
   $sig = -join ($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes("$ts.")) | ForEach-Object { $_.ToString("x2") })
   curl -H "X-OpinionPadi-Timestamp: $ts" -H "X-OpinionPadi-Signature: $sig" `
     http://127.0.0.1:8000/api/v1/completion-codes/VX-XXXX-XXXX-XXXX
   ```

5. Reviewer console at `http://127.0.0.1:8000/admin/review` — requires the
   `ADMIN_USERNAME`/`ADMIN_PASSWORD` set in step 3 (access fails closed
   without them).

## 8. Run the checks

```powershell
php artisan test          # PHP feature tests (SQLite in-memory)
npm test                  # frontend unit tests (Vitest)
./vendor/bin/pint --test  # formatting check
./vendor/bin/phpstan analyse  # static analysis
```

## Troubleshooting

| Symptom | Fix |
|---|---|
| `The token '&&' is not a valid statement separator` | Windows PowerShell 5.x — run commands on separate lines (or use `;`) |
| `Base table or view not found` | Run `php artisan migrate` |
| `Dev key missing` on `/dev/handoff` | Run `php artisan voxelion:keys` |
| Blank page / missing assets | Run `npm run build` (or `npm run dev`) |
| `/admin` asks for credentials you never set | Set `ADMIN_USERNAME`/`ADMIN_PASSWORD` in `.env`, then restart `php artisan serve` |
| Photo upload fails on camera step | Serve over `localhost`/`127.0.0.1` (browser camera requires a secure context) or grant camera permission |


Terminal command
 
php artisan migrate --force     # verify DB is up to date → "Nothing to migrate"
php artisan voxelion:keys       # verify dev keypair → "Keypair already exists"
php artisan serve --port=8000   # start the server (running in background)
