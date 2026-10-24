import io

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.main import app, decide
from app.provenance import parse_manifest_store

AUTH = {"X-Internal-Token": "test-token"}


@pytest.fixture(scope="module")
def client():
    with TestClient(app) as c:
        yield c


def make_image(fmt="JPEG", size=(64, 48)) -> bytes:
    buf = io.BytesIO()
    Image.new("RGB", size, (120, 80, 200)).save(buf, fmt)
    return buf.getvalue()


def post(client, data, name="x.jpg", headers=AUTH):
    return client.post("/analyze", files={"file": (name, data, "application/octet-stream")}, headers=headers)


def test_health(client):
    r = client.get("/health")
    assert r.status_code == 200 and r.json()["model"] == "stub"


def test_requires_token(client):
    assert post(client, make_image(), headers={}).status_code == 401
    assert post(client, make_image(), headers={"X-Internal-Token": "nope"}).status_code == 401


@pytest.mark.parametrize("fmt", ["JPEG", "PNG", "WEBP", "GIF"])
def test_analyze_formats(client, fmt):
    r = post(client, make_image(fmt))
    assert r.status_code == 200, r.text
    body = r.json()
    assert body["ai_probability"] == 0.9
    assert body["verdict"] == "LIKELY_AI"
    assert body["c2pa"] == {"present": False}
    assert body["hints"] == {"has_exif": False, "camera_tags": False}
    assert (body["width"], body["height"]) == (64, 48)


def test_rejects_non_image(client):
    assert post(client, b"definitely not an image").status_code == 422


def test_rejects_too_many_pixels(client):
    assert post(client, make_image("PNG", (1500, 1000))).status_code == 422


def test_rejects_oversize_body(client):
    assert post(client, b"0" * 200_001).status_code == 413


def test_decide_thresholds():
    none = {}
    assert decide(0.95, none) == "LIKELY_AI"
    assert decide(0.05, none) == "LIKELY_REAL"
    assert decide(0.5, none) == "UNCERTAIN"
    # provenance outranks the classifier
    assert decide(0.01, {"ai_declared": True}) == "AI_DECLARED"


def _store(source_type: str | None):
    assertions = []
    if source_type:
        assertions.append({"label": "c2pa.actions.v2", "data": {"actions": [
            {"action": "c2pa.created", "digitalSourceType": source_type}]}})
    return {
        "active_manifest": "urn:uuid:1",
        "manifests": {"urn:uuid:1": {
            "claim_generator": "SomeTool/1.0",
            "signature_info": {"issuer": "Example CA"},
            "assertions": assertions,
        }},
        "validation_state": "Valid",
    }


def test_parse_manifest_ai_declared():
    out = parse_manifest_store(_store("http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia"))
    assert out["present"] and out["ai_declared"] and out["issuer"] == "Example CA"


def test_parse_manifest_camera_not_ai():
    out = parse_manifest_store(_store("http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture"))
    assert out["present"] and not out["ai_declared"]


def test_parse_manifest_empty():
    assert parse_manifest_store({}) == {"present": False}
