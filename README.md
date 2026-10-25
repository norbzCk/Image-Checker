# ImageCheck

Upload an image and get an estimate of whether it is **AI-generated** or **camera-captured**.
Everything runs locally in Docker and uses only free components.

```
browser ──► web (PHP 8.3 + Apache) ──HTTP──► inference (Python/FastAPI)
                  │                              ├─ pretrained classifier (Hugging Face, CPU)
                  ▼                              ├─ C2PA Content Credentials check (c2pa-python)
                db (MySQL 8.4)                   └─ EXIF hints
```

## Quick start

```bash
scripts/init-env.sh            # generates .env with random secrets
docker compose up --build
```

Open http://localhost:8081. The **first start downloads the model weights** (a few hundred MB, cached in the
`model_cache` volume); watch it with `docker compose logs -f inference`. The `web` container works
before the model is ready: uploads made during warm-up show "Analysis failed" with a **Try again** button.

If you ran the old single-container version, stop it first (`docker compose down -v` in the old checkout)
and **rotate the old database password**: it was committed to git.

## How a verdict is produced

1. **C2PA Content Credentials** (original file bytes): if the image declares AI generation → `AI_DECLARED`.
2. Otherwise the classifier's probability: `>= 0.80` → `LIKELY_AI`, `<= 0.20` → `LIKELY_REAL`, else `UNCERTAIN`
   (thresholds: `THRESHOLD_LIKELY_AI` / `THRESHOLD_LIKELY_REAL`).
3. EXIF camera tags are shown as a weak hint only and never change the verdict.

A missing C2PA manifest or SynthID watermark proves nothing; most images have neither. SynthID has no free
API, so the results page links to Google's free web detector for manual checks.

## Configuration (`.env`)

| Variable | Purpose |
|---|---|
| `MODEL_ID` | Hugging Face image-classification model. **Check its licence** before commercial use. |
| `AI_LABELS` | Label name(s) of that model meaning "AI-generated". The service refuses to start if none match, and logs the model's real labels. |
| `MODEL_REVISION` | Pin an exact model commit for reproducible results. |
| `MODEL_BACKEND` | `hf` (real model) or `stub` (fixed probability, for wiring tests). |

## Security notes

- Uploads: size, real MIME (`finfo`), `getimagesize`, pixel cap, random filenames, stored **outside** the web root and served
  only to the uploading browser by `image.php`. Original bytes are kept (re-encoding would destroy C2PA).
- No accounts: a random HttpOnly cookie identifies a browser; the DB stores only its hash. CSRF tokens on all POSTs.
- Rate limit: 10 uploads / 10 min per IP (HMAC-hashed). Behind a reverse proxy, make `REMOTE_ADDR` the real client IP.
- The inference service is not published to the host and requires `X-Internal-Token`.
- Add TLS (reverse proxy) before exposing this beyond localhost, and a retention job if you don't want to keep images.

## Tests

```bash
cd inference && pip install -r requirements-dev.txt && pytest
```

