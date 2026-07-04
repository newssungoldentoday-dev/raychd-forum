#!/usr/bin/env bash
#
# INSTALL.sh — Ray Chad Forum setup script
# Supports: macOS, Linux, Termux (Android)
#
# Usage:
#   bash INSTALL.sh
#   bash INSTALL.sh --docker      # force Docker install
#   bash INSTALL.sh --manual      # force manual (no Docker) install
#

set -euo pipefail

REPO_URL="https://github.com/newssungoldentoday-dev/raychd-forum.git"
INSTALL_DIR="${RAYCHD_FORUM_DIR:-$HOME/raychd-forum}"
MODE=""

# ---------------------------------------------------------------------------
# Colors (fall back to plain text if terminal doesn't support it)
# ---------------------------------------------------------------------------
if [ -t 1 ]; then
  GREEN="\033[0;32m"
  AMBER="\033[0;33m"
  RED="\033[0;31m"
  DIM="\033[2m"
  RESET="\033[0m"
else
  GREEN=""; AMBER=""; RED=""; DIM=""; RESET=""
fi

log()  { printf "${GREEN}==>${RESET} %s\n" "$1"; }
warn() { printf "${AMBER}!!${RESET} %s\n" "$1"; }
err()  { printf "${RED}xx${RESET} %s\n" "$1" >&2; }
dim()  { printf "${DIM}%s${RESET}\n" "$1"; }

# ---------------------------------------------------------------------------
# 1. Parse flags
# ---------------------------------------------------------------------------
for arg in "$@"; do
  case "$arg" in
    --docker) MODE="docker" ;;
    --manual) MODE="manual" ;;
    -h|--help)
      cat <<EOF
Ray Chad Forum installer

Usage: bash INSTALL.sh [--docker|--manual]

  --docker   Install and run via Docker Compose (default if Docker is found)
  --manual   Install dependencies directly on this machine (used automatically on Termux)
EOF
      exit 0
      ;;
    *)
      warn "Unknown option: $arg (ignored)"
      ;;
  esac
done

# ---------------------------------------------------------------------------
# 2. Detect platform
# ---------------------------------------------------------------------------
OS="unknown"
if [ -n "${TERMUX_VERSION:-}" ] || [ -d "/data/data/com.termux" ]; then
  OS="termux"
elif [[ "$(uname -s)" == "Darwin" ]]; then
  OS="macos"
elif [[ "$(uname -s)" == "Linux" ]]; then
  OS="linux"
fi

log "Detected platform: $OS"

# ---------------------------------------------------------------------------
# 3. Check for git, clone or update the repo
# ---------------------------------------------------------------------------
if ! command -v git >/dev/null 2>&1; then
  err "git is required but not found."
  case "$OS" in
    termux) dim "Install it with: pkg install git" ;;
    macos)  dim "Install it with: brew install git" ;;
    linux)  dim "Install it with your package manager, e.g. sudo apt install git" ;;
  esac
  exit 1
fi

if [ -d "$INSTALL_DIR/.git" ]; then
  log "Existing install found at $INSTALL_DIR — pulling latest changes"
  git -C "$INSTALL_DIR" pull --ff-only
else
  log "Cloning raychd-forum into $INSTALL_DIR"
  git clone "$REPO_URL" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

# ---------------------------------------------------------------------------
# 4. Decide install mode
# ---------------------------------------------------------------------------
if [ -z "$MODE" ]; then
  if [ "$OS" = "termux" ]; then
    MODE="manual"   # Docker isn't available on Termux
  elif command -v docker >/dev/null 2>&1 && command -v docker-compose >/dev/null 2>&1; then
    MODE="docker"
  elif command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    MODE="docker"
  else
    MODE="manual"
  fi
fi

log "Install mode: $MODE"

# ---------------------------------------------------------------------------
# 5a. Docker install
# ---------------------------------------------------------------------------
install_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    err "Docker not found."
    case "$OS" in
      macos) dim "Install Docker Desktop: https://www.docker.com/products/docker-desktop" ;;
      linux) dim "Install with: curl -fsSL https://get.docker.com | sh" ;;
    esac
    exit 1
  fi

  if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    log "Creating .env from .env.example"
    cp .env.example .env
    warn "Edit .env to set your domain, database credentials, and IRC bridge settings before going further."
  fi

  log "Starting containers"
  if command -v docker-compose >/dev/null 2>&1; then
    docker-compose up -d
  else
    docker compose up -d
  fi

  log "Forum starting. Check status with: docker compose ps"
  dim "Once healthy, it will be available at http://localhost:8080 (see .env to change the port)"
}

# ---------------------------------------------------------------------------
# 5b. Manual install (Termux / bare-metal Linux / macOS without Docker)
# ---------------------------------------------------------------------------
install_manual() {
  case "$OS" in
    termux)
      log "Installing dependencies via pkg"
      pkg update -y
      pkg install -y nodejs-lts sqlite
      ;;
    macos)
      if ! command -v node >/dev/null 2>&1; then
        if command -v brew >/dev/null 2>&1; then
          log "Installing Node.js via Homebrew"
          brew install node sqlite
        else
          err "Node.js not found and Homebrew isn't installed."
          dim "Install Homebrew (https://brew.sh) or Node.js manually, then re-run this script."
          exit 1
        fi
      fi
      ;;
    linux)
      if ! command -v node >/dev/null 2>&1; then
        err "Node.js not found."
        dim "Install it via your package manager or https://nodejs.org, then re-run this script."
        exit 1
      fi
      ;;
    *)
      warn "Unrecognized OS — assuming Node.js is already installed."
      ;;
  esac

  if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    log "Creating .env from .env.example"
    cp .env.example .env
    warn "Edit .env before starting the server (database path, port, IRC bridge settings)."
  fi

  log "Installing npm dependencies"
  npm install --omit=dev

  log "Running database migrations"
  npm run migrate --if-present

  log "Manual install complete."
  dim "Start the forum with: npm start"
  dim "On Termux, keep it running in the background with: nohup npm start &"
}

# ---------------------------------------------------------------------------
# 6. Run
# ---------------------------------------------------------------------------
if [ "$MODE" = "docker" ]; then
  install_docker
else
  install_manual
fi

log "Done. See GETTING_STARTED.md for account setup and linking with your raychd IRC server."
