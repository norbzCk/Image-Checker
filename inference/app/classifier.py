"""Image classifiers. Loaded once at startup; `predict` returns P(AI-generated)."""
from __future__ import annotations

import logging
from typing import Protocol

from PIL import Image

from . import settings

log = logging.getLogger("imagecheck.classifier")


class Classifier(Protocol):
    name: str
    revision: str | None

    def predict(self, image: Image.Image) -> float: ...


class StubClassifier:
    """Returns a fixed probability. For tests and for checking the wiring."""

    name = "stub"
    revision = None

    def predict(self, image: Image.Image) -> float:
        return settings.STUB_PROBABILITY


class HFClassifier:
    """Hugging Face `image-classification` pipeline running on CPU."""

    def __init__(self, model_id: str, revision: str | None, ai_labels: set[str]):
        from transformers import pipeline  # imported lazily: heavy

        log.info("Loading model %s (revision=%s)", model_id, revision or "default")
        self._pipe = pipeline(
            "image-classification", model=model_id, revision=revision, device=-1
        )
        self.name = model_id
        self.revision = revision
        self._ai_labels = ai_labels

        model_labels = {str(v).lower() for v in self._pipe.model.config.id2label.values()}
        if not model_labels & ai_labels:
            raise RuntimeError(
                f"None of the model's labels {sorted(model_labels)} match AI_LABELS "
                f"{sorted(ai_labels)}. Set AI_LABELS to the label(s) meaning 'AI-generated'."
            )
        log.info("Model ready. labels=%s ai_labels=%s", sorted(model_labels), sorted(ai_labels))

    def predict(self, image: Image.Image) -> float:
        results = self._pipe(image, top_k=None)
        probability = sum(r["score"] for r in results if r["label"].lower() in self._ai_labels)
        return float(min(max(probability, 0.0), 1.0))


def load_classifier() -> Classifier:
    if settings.MODEL_BACKEND == "stub":
        return StubClassifier()
    if settings.MODEL_BACKEND == "hf":
        return HFClassifier(settings.MODEL_ID, settings.MODEL_REVISION, settings.AI_LABELS)
    raise RuntimeError(f"Unknown MODEL_BACKEND={settings.MODEL_BACKEND!r}")
