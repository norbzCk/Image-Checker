"""Runtime configuration, read once from environment variables."""
import os


def _float(name: str, default: str) -> float:
    return float(os.getenv(name, default))


def _int(name: str, default: str) -> int:
    return int(os.getenv(name, default))


# "hf" = real Hugging Face model, "stub" = fixed probability (tests / wiring checks)
MODEL_BACKEND = os.getenv("MODEL_BACKEND", "hf")
MODEL_ID = os.getenv("MODEL_ID", "umm-maybe/AI-image-detector")
MODEL_REVISION = os.getenv("MODEL_REVISION") or None
STUB_PROBABILITY = _float("STUB_PROBABILITY", "0.5")

# Labels (lower-case) of the model that mean "AI-generated".
AI_LABELS = {
    label.strip().lower()
    for label in os.getenv("AI_LABELS", "artificial,ai,fake,generated,ai-generated").split(",")
    if label.strip()
}

INFERENCE_TOKEN = os.getenv("INFERENCE_TOKEN", "")

MAX_BYTES = _int("MAX_UPLOAD_BYTES", str(10 * 1024 * 1024))
MAX_PIXELS = _int("MAX_PIXELS", "40000000")

THRESHOLD_LIKELY_AI = _float("THRESHOLD_LIKELY_AI", "0.80")
THRESHOLD_LIKELY_REAL = _float("THRESHOLD_LIKELY_REAL", "0.20")
