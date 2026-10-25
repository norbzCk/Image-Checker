"""Free provenance signals: C2PA Content Credentials and basic EXIF hints.

A *positive* C2PA "AI-generated" declaration is strong evidence. A missing manifest
proves nothing, because most platforms strip metadata and most generators don't sign.
"""
from __future__ import annotations

import io
import json
import logging
from typing import Any

from PIL import Image

log = logging.getLogger("imagecheck.origin")

# IPTC digitalSourceType values that mean "made (partly) by a generative/algorithmic system".
_AI_SOURCE_TYPES = (
    "trainedalgorithmicmedia",
    "compositewithtrainedalgorithmicmedia",
    "algorithmicmedia",
)

_EXIF_MAKE, _EXIF_MODEL = 271, 272


def _collect(node: Any, key: str, out: list[str]) -> None:
    if isinstance(node, dict):
        for k, v in node.items():
            if k == key and isinstance(v, str):
                out.append(v)
            else:
                _collect(v, key, out)
    elif isinstance(node, list):
        for item in node:
            _collect(item, key, out)


def parse_manifest_store(store: dict[str, Any]) -> dict[str, Any]:
    """Reduce a C2PA manifest-store JSON document to the few fields we show."""
    active_label = store.get("active_manifest")
    manifests = store.get("manifests") or {}
    active = manifests.get(active_label) if active_label else None
    if active is None and manifests:
        active = next(iter(manifests.values()))
    if active is None:
        return {"present": False}

    source_types: list[str] = []
    _collect(active.get("assertions", []), "digitalSourceType", source_types)
    ai_declared = any(
        any(marker in st.lower() for marker in _AI_SOURCE_TYPES) for st in source_types
    )

    signature = active.get("signature_info") or {}
    generator = active.get("claim_generator")
    if not generator:
        info = active.get("claim_generator_info") or []
        if info and isinstance(info, list) and isinstance(info[0], dict):
            generator = info[0].get("name")

    return {
        "present": True,
        "ai_declared": ai_declared,
        "digital_source_types": sorted(set(source_types)),
        "issuer": signature.get("issuer"),
        "claim_generator": generator,
        "validation_state": store.get("validation_state"),
    }


def read_c2pa(data: bytes, mime: str) -> dict[str, Any]:
    """Look for a C2PA manifest in the image bytes (must be the ORIGINAL bytes)."""
    try:
        import c2pa
    except ImportError:  # library missing: degrade, don't fail the request
        return {"present": None, "error": "c2pa library not installed"}

    try:
        with c2pa.Reader(mime, io.BytesIO(data)) as reader:
            store = json.loads(reader.json())
        return parse_manifest_store(store)
    except c2pa.C2paError as exc:
        if isinstance(exc, c2pa.C2paError.ManifestNotFound):
            return {"present": False}
        log.warning("C2PA read failed: %s", type(exc).__name__)
        return {"present": None, "error": type(exc).__name__}
    except Exception as exc:  # never let a metadata parser take the request down
        log.warning("C2PA unexpected error: %s", type(exc).__name__)
        return {"present": None, "error": type(exc).__name__}


def exif_hints(image: Image.Image) -> dict[str, bool]:
    """Booleans only (no raw values, for privacy). Weak hints, never used for the verdict."""
    try:
        exif = image.getexif()
    except Exception:
        return {"has_exif": False, "camera_tags": False}
    return {
        "has_exif": len(exif) > 0,
        "camera_tags": bool(exif.get(_EXIF_MAKE) or exif.get(_EXIF_MODEL)),
    }
