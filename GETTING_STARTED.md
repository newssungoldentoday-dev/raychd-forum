# Getting Started with Ray Chad Forum

Ray Chad Forum is the threaded, async companion to [Ray Chad IRC](https://github.com/newssungoldentoday-dev/raychd). This guide walks you through installing it, linking it to your raychd server, and creating your first board and thread.

---

## 1. Prerequisites

- A working [Ray Chad IRC](https://github.com/newssungoldentoday-dev/raychd) install (optional, but required if you want shared accounts and the IRC bridge bot)
- One of:
  - Docker + Docker Compose, **or**
  - Node.js 18+ and SQLite (for a manual install)
- git

Supported platforms: **macOS**, **Linux**, and **Termux** (Android).

---

## 2. Install

Run the installer from the repo root:

```bash
bash INSTALL.sh
```

The script auto-detects your platform:

| Platform | Default mode |
|---|---|
| macOS / Linux (Docker found) | Docker Compose |
| macOS / Linux (no Docker) | Manual (Node.js) |
| Termux | Manual (Docker isn't supported on Android) |

You can also force a mode:

```bash
bash INSTALL.sh --docker
bash INSTALL.sh --manual
```

The script copies `.env.example` to `.env` on first run. **Edit `.env` before starting the server** — at minimum, set:

```env
FORUM_DOMAIN=forum.example.com
FORUM_PORT=8080
DATABASE_PATH=./data/forum.sqlite
```

---

## 3. Start the forum

**Docker:**
```bash
docker compose up -d
docker compose ps        # check health
```

**Manual:**
```bash
npm start
# or, to keep it running in the background on Termux:
nohup npm start &
```

Once running, visit `http://localhost:8080` (or whatever `FORUM_PORT` you set).

---

## 4. Link accounts with Ray Chad IRC

If you're running raychd on the same machine or network, point the forum at it so members can log in with one identity:

```env
# in .env
RAYCHD_IRC_HOST=irc.example.com
RAYCHD_IRC_PORT=6697
RAYCHD_SHARED_SECRET=replace-with-a-long-random-string
```

Restart the forum after saving. When a member logs in, the forum checks their credentials against raychd's account system instead of keeping a separate password.

---

## 5. Enable the IRC bridge bot (optional)

The bridge bot posts new thread titles into your IRC channels, so the forum surfaces itself where people already are.

```bash
npm run bridge:enable --if-present
```

Configure which channels receive announcements in `.env`:

```env
BRIDGE_CHANNELS=#general,#dev
```

---

## 6. Create your first board and thread

1. Log in with your raychd account (or register directly on the forum if you're running it standalone).
2. Go to **Boards → New Board** and give it a name (e.g. `install`, `dev`, `meta`).
3. Open the board and select **New Thread**.
4. Or, from the command line:

```bash
raychd-forum new --board install "Docker Compose for forum + raychd together"
```

---

## 7. Where to go next

- **INSTALL.sh** — re-run any time to update or repair your install
- **docker-compose.yml** — adjust ports, volumes, and resource limits
- **privacy-policy.md** — bilingual policy, compliant with RA 10173 (Philippine Data Privacy Act), matching the raychd policy
- **CONTRIBUTING.md** — if you'd like to submit a patch or theme

If something doesn't start cleanly, check `docker compose logs -f` (Docker) or the console output from `npm start` (manual) — both print the reason on failure rather than failing silently.
