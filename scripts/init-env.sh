#!/usr/bin/env bash
# Generates a .env with random secrets (hex only, so nothing needs escaping in Compose).
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then
    echo ".env already exists, refusing to overwrite it." >&2
    exit 1
fi

gen() { openssl rand -hex 24; }

cat > .env <<ENV
DB_NAME=imagecheck
DB_USER=imagecheck_user
DB_PASSWORD=$(gen)
DB_ROOT_PASSWORD=$(gen)
INFERENCE_TOKEN=$(gen)
APP_SECRET=$(gen)

MODEL_BACKEND=hf
MODEL_ID=umm-maybe/AI-image-detector
AI_LABELS=artificial,ai,fake,generated,ai-generated
ENV
chmod 600 .env
echo "Created .env. Next: docker compose up --build"
