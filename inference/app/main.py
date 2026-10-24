"""ImageCheck inference service: POST an image to /analyze, get a JSON assessment."""
from __future__ import annotations

import hmac
import io
import logging
import threading
import time
from contextlib import asynccontextmanager
from typing import Any

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from PIL import Image, UnidentifiedImageError

from . import settings
from .classifier import load_classifier
from .provenance import exif_hints, read_c2pa

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger("imagecheck")

_FORMAT_TO_MIME = {
    "JPEG": "image/jpeg",
    "PNG": "image/png",
    "WEBP": "image/webp",
    "GIF": "image/gif",
}

# Pillow raises a warning above MAX_IMAGE_PIXELS and an error above 2x that.
Image.MAX_IMAGE_PIXELS = settings.MAX_PIXELS


@asynccontextmanager
async def lifespan(app: FastAPI):
    if not settings.INFERENCE_TOKEN:
        raise RuntimeError("INFERENCE_TOKEN must be set")
    app.state.classifier = load_classifier()
    app.state.lock = threading.Lock()  # one CPU inference at a time
    yield


app = FastAPI(title="ImageCheck inference", lifespan=lifespan, docs_url=None, redoc_url=None)


def require_token(x_internal_token: str | None = Header(default=None)) -> None:
    supplied = (x_internal_token or "").encode()
    if not hmac.compare_digest(supplied, settings.INFERENCE_TOKEN.encode()):
        raise HTTPException(status_code=401, detail="invalid token")


def decide(probability: float, c2pa: dict[str, Any]) -> str:
    """Combine signals into one verdict. Provenance outranks the classifier."""
    if c2pa.get("ai_declared"):
        return "AI_DECLARED"
    if probability >= settings.THRESHOLD_LIKELY_AI:
        return "LIKELY_AI"
    if probability <= settings.THRESHOLD_LIKELY_REAL:
        return "LIKELY_REAL"
    return "UNCERTAIN"


def open_image(data: bytes) -> tuple[Image.Image, str]:
    """Validate and decode. Returns (RGB image, mime). Raises ValueError with a safe message."""
    try:
        with Image.open(io.BytesIO(data)) as probe:
            probe.verify()
        image = Image.open(io.BytesIO(data))
        mime = _FORMAT_TO_MIME.get(image.format or "")
        if mime is None:
            raise ValueError("Unsupported image format.")
        width, height = image.size
        if width * height > settings.MAX_PIXELS:
            raise ValueError("Image dimensions are too large.")
        image.load()
    except ValueError:
        raise
    except (UnidentifiedImageError, Image.DecompressionBombError, OSError, SyntaxError):
        raise ValueError("The file is not a valid image.") from None
    return image, mime


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": app.state.classifier.name}


@app.post("/analyze", dependencies=[Depends(require_token)])
def analyze(file: UploadFile = File(...)) -> dict[str, Any]:
    data = file.file.read(settings.MAX_BYTES + 1)
    if len(data) > settings.MAX_BYTES:
        raise HTTPException(status_code=413, detail="Image is too large.")

    started = time.perf_counter()
    try:
        image, mime = open_image(data)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from None

    hints = exif_hints(image)  # before converting: conversion drops EXIF
    c2pa = read_c2pa(data, mime)  # original bytes: re-encoding would destroy the manifest
    rgb = image.convert("RGB")

    with app.state.lock:
        probability = app.state.classifier.predict(rgb)

    return {
        "ai_probability": round(probability, 4),
        "verdict": decide(probability, c2pa),
        "model": {"name": app.state.classifier.name, "revision": app.state.classifier.revision},
        "c2pa": c2pa,
        "hints": hints,
        "width": image.width,
        "height": image.height,
        "latency_ms": int((time.perf_counter() - started) * 1000),
    }
