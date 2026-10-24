import os

# Must be set before `app` is imported.
os.environ.update(
    MODEL_BACKEND="stub",
    INFERENCE_TOKEN="test-token",
    MAX_PIXELS="1000000",
    MAX_UPLOAD_BYTES="200000",
    STUB_PROBABILITY="0.9",
)
