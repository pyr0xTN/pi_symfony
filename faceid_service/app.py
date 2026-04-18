import base64
import math
from dataclasses import dataclass
from typing import List, Optional

import cv2
import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel


@dataclass
class FaceFeatures:
    embedding: List[float]
    face_box: List[int]


class ExtractRequest(BaseModel):
    image_data: str


class CompareRequest(BaseModel):
    probe_embedding: List[float]
    stored_embedding: List[float]


app = FastAPI(title="Face ID OpenCV Service", version="1.0.0")

# Uses OpenCV's bundled frontal-face cascade.
FACE_CASCADE = cv2.CascadeClassifier(
    cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
)
PROFILE_CASCADE = cv2.CascadeClassifier(
    cv2.data.haarcascades + "haarcascade_profileface.xml"
)


@app.get("/health")
def health() -> dict:
    return {"ok": True}


@app.post("/extract")
def extract(req: ExtractRequest) -> dict:
    image = _decode_data_url(req.image_data)
    features = _extract_face_features(image)

    if features is None:
        raise HTTPException(status_code=400, detail="No face detected")

    return {
        "embedding": features.embedding,
        "face_box": features.face_box,
    }


@app.post("/compare")
def compare(req: CompareRequest) -> dict:
    score = _cosine_similarity(req.probe_embedding, req.stored_embedding)
    return {"similarity": score, "match": score >= 0.82}


def _decode_data_url(image_data: str) -> np.ndarray:
    if not image_data.startswith("data:image/"):
        raise HTTPException(status_code=400, detail="Invalid data URL")

    try:
        encoded = image_data.split(",", 1)[1]
        raw = base64.b64decode(encoded)
    except Exception as exc:
        raise HTTPException(status_code=400, detail="Invalid image payload") from exc

    array = np.frombuffer(raw, dtype=np.uint8)
    image = cv2.imdecode(array, cv2.IMREAD_COLOR)
    if image is None:
        raise HTTPException(status_code=400, detail="Image decode failed")

    return image


def _extract_face_features(image: np.ndarray) -> Optional[FaceFeatures]:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    face_box = _detect_face_box(gray)
    if face_box is None:
        return None

    x1, y1, x2, y2 = face_box

    face = gray[y1:y2, x1:x2]
    if face.size == 0:
        return None

    # Basic lighting normalization improves consistency across sessions.
    normalized = cv2.equalizeHist(face)

    # Lightweight handcrafted embedding: local texture + edge histogram.
    resized = cv2.resize(normalized, (128, 128), interpolation=cv2.INTER_AREA)

    lbp_embedding = _lbp_histogram_embedding(resized)
    edge_embedding = _edge_histogram_embedding(resized)

    embedding = np.concatenate([lbp_embedding, edge_embedding]).astype(np.float32)
    norm = np.linalg.norm(embedding)
    if norm <= 1e-8:
        return None

    embedding = embedding / norm

    return FaceFeatures(
        embedding=[float(v) for v in embedding.tolist()],
        face_box=[int(x1), int(y1), int(x2 - x1), int(y2 - y1)],
    )


def _detect_face_box(gray: np.ndarray) -> Optional[tuple[int, int, int, int]]:
    height, width = gray.shape[:2]
    min_face_size = max(40, int(min(width, height) * 0.15))

    attempts = [
        (FACE_CASCADE, 1.12, 4),
        (FACE_CASCADE, 1.08, 3),
        (PROFILE_CASCADE, 1.12, 4),
    ]

    best_face: Optional[tuple[int, int, int, int]] = None
    best_area = 0

    for cascade, scale_factor, min_neighbors in attempts:
        faces = cascade.detectMultiScale(
            gray,
            scaleFactor=scale_factor,
            minNeighbors=min_neighbors,
            minSize=(min_face_size, min_face_size),
        )

        if len(faces) == 0:
            continue

        for x, y, w, h in faces:
            area = int(w) * int(h)
            if area > best_area:
                pad = int(0.18 * max(w, h))
                x1 = max(0, int(x) - pad)
                y1 = max(0, int(y) - pad)
                x2 = min(width, int(x + w + pad))
                y2 = min(height, int(y + h + pad))
                best_face = (x1, y1, x2, y2)
                best_area = area

    return best_face

def _lbp_histogram_embedding(image: np.ndarray) -> np.ndarray:
    # Uniform LBP approximation with 8-neighborhood.
    center = image[1:-1, 1:-1]
    lbp = np.zeros_like(center, dtype=np.uint8)

    offsets = [
        (-1, -1),
        (-1, 0),
        (-1, 1),
        (0, 1),
        (1, 1),
        (1, 0),
        (1, -1),
        (0, -1),
    ]

    for bit, (dy, dx) in enumerate(offsets):
        neighbor = image[1 + dy : image.shape[0] - 1 + dy, 1 + dx : image.shape[1] - 1 + dx]
        lbp |= ((neighbor >= center) << bit).astype(np.uint8)

    # Spatial pyramid: 4x4 blocks for robustness to slight motion.
    blocks = []
    h, w = lbp.shape
    block_h = h // 4
    block_w = w // 4

    for row in range(4):
        for col in range(4):
            y1 = row * block_h
            y2 = h if row == 3 else (row + 1) * block_h
            x1 = col * block_w
            x2 = w if col == 3 else (col + 1) * block_w
            block = lbp[y1:y2, x1:x2]
            hist = cv2.calcHist([block], [0], None, [32], [0, 256]).flatten()
            blocks.append(hist)

    embedding = np.concatenate(blocks).astype(np.float32)
    total = float(np.sum(embedding))
    if total > 0:
        embedding /= total

    return embedding


def _edge_histogram_embedding(image: np.ndarray) -> np.ndarray:
    gx = cv2.Sobel(image, cv2.CV_32F, 1, 0, ksize=3)
    gy = cv2.Sobel(image, cv2.CV_32F, 0, 1, ksize=3)

    magnitude = cv2.magnitude(gx, gy)
    angle = cv2.phase(gx, gy, angleInDegrees=False)

    bins = np.zeros(16, dtype=np.float32)

    flat_mag = magnitude.ravel()
    flat_ang = angle.ravel()

    for mag, ang in zip(flat_mag, flat_ang):
        if mag <= 0.0:
            continue
        idx = int(math.floor((ang / (2.0 * math.pi)) * 16.0)) % 16
        bins[idx] += float(mag)

    total = float(np.sum(bins))
    if total > 0:
        bins /= total

    return bins


def _cosine_similarity(first: List[float], second: List[float]) -> float:
    a = np.array(first, dtype=np.float32)
    b = np.array(second, dtype=np.float32)

    if a.size == 0 or b.size == 0 or a.shape != b.shape:
        return 0.0

    denom = float(np.linalg.norm(a) * np.linalg.norm(b))
    if denom <= 1e-8:
        return 0.0

    return float(np.dot(a, b) / denom)


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("app:app", host="127.0.0.1", port=8001, reload=False)
