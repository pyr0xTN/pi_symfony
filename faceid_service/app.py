import base64
import json
import math
import os
from dataclasses import dataclass
from typing import List, Optional
from urllib import error as urlerror
from urllib import request as urlrequest

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


class AnalyzeLocationRequest(BaseModel):
    image_data: str


# Location database with example landmarks and their visual characteristics
LOCATION_DATABASE = [
    {
        "name": "Sidi Bou Said",
        "country": "Tunisia 🇹🇳",
        "icon": "🏘️",
        "description": "Blue and white cliffside village near Tunis",
        "keywords": ["blue", "white", "mediterranean", "coastal", "tunisia", "sidi bou said"],
        "best_time": "March to June or September to November",
    },
    {
        "name": "El Jem Amphitheatre",
        "country": "Tunisia 🇹🇳",
        "icon": "🏛️",
        "description": "Roman amphitheatre in El Jem",
        "keywords": ["amphitheatre", "roman", "stone", "historic", "tunisia", "el jem"],
        "best_time": "October to April",
    },
    {
        "name": "Carthage Ruins",
        "country": "Tunisia 🇹🇳",
        "icon": "🏺",
        "description": "Ancient Punic and Roman archaeological site",
        "keywords": ["ruins", "ancient", "rome", "historic", "carthage", "tunisia"],
        "best_time": "March to May or October to November",
    },
    {
        "name": "Djerba Island",
        "country": "Tunisia 🇹🇳",
        "icon": "🏝️",
        "description": "Sunny island with beaches and white architecture",
        "keywords": ["beach", "island", "coast", "white", "djerba", "tunisia"],
        "best_time": "May to October",
    },
    {
        "name": "Tozeur Sahara Oasis",
        "country": "Tunisia 🇹🇳",
        "icon": "🏜️",
        "description": "Desert oasis gateway to the Sahara",
        "keywords": ["desert", "sand", "oasis", "palm", "tozeur", "tunisia"],
        "best_time": "October to March",
    },
    {
        "name": "Eiffel Tower",
        "country": "France 🇫🇷",
        "icon": "🗼",
        "description": "Iconic iron lattice tower in Paris",
        "keywords": ["tower", "metal", "architecture", "paris"],
        "best_time": "April to June or September to October",
    },
    {
        "name": "Statue of Liberty",
        "country": "USA 🇺🇸",
        "icon": "🗽",
        "description": "Colossal copper statue in New York",
        "keywords": ["statue", "monument", "liberty", "new york"],
        "best_time": "April to June and September to November",
    },
    {
        "name": "Big Ben",
        "country": "UK 🇬🇧",
        "icon": "🏛️",
        "description": "Historic clock tower in London",
        "keywords": ["clock", "tower", "london", "historic"],
        "best_time": "May to September",
    },
    {
        "name": "Colosseum",
        "country": "Italy 🇮🇹",
        "icon": "🏛️",
        "description": "Ancient Roman amphitheater",
        "keywords": ["ancient", "rome", "architecture", "historic"],
        "best_time": "April to May or September to October",
    },
    {
        "name": "Great Wall",
        "country": "China 🇨🇳",
        "icon": "🧱",
        "description": "Historic defensive structure",
        "keywords": ["wall", "ancient", "stone", "historic"],
        "best_time": "April to May or September to November",
    },
    {
        "name": "Taj Mahal",
        "country": "India 🇮🇳",
        "icon": "🕌",
        "description": "Marble mausoleum masterpiece",
        "keywords": ["marble", "architecture", "white", "monument"],
        "best_time": "October to March",
    },
    {
        "name": "Christ the Redeemer",
        "country": "Brazil 🇧🇷",
        "icon": "🗿",
        "description": "Colossal statue overlooking Rio",
        "keywords": ["statue", "mountain", "monument", "rio"],
        "best_time": "May to October",
    },
    {
        "name": "Machu Picchu",
        "country": "Peru 🇵🇪",
        "icon": "⛩️",
        "description": "Ancient Incan citadel",
        "keywords": ["ancient", "mountain", "ruins", "historic"],
        "best_time": "May to September",
    },
]


@app.post("/analyze-location")
def analyze_location(req: AnalyzeLocationRequest) -> dict:
    """Analyze image and suggest similar locations"""
    try:
        image = _decode_data_url(req.image_data)
        if image is None:
            raise HTTPException(status_code=400, detail="Invalid image data")

        # Extract image characteristics
        hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

        # Analyze dominant colors
        dominant_color = _get_dominant_color(hsv)

        # Analyze brightness (contrast)
        brightness = float(np.mean(gray))

        # Detect edges (for architecture)
        edges = cv2.Canny(gray, 100, 200)
        edge_ratio = float(np.sum(edges > 0) / edges.size)

        # Generate keywords based on image analysis
        keywords = _analyze_image_features(image, dominant_color, brightness, edge_ratio)

        # Score each location based on keyword matches
        scored_locations = []
        for location in LOCATION_DATABASE:
            location_keywords = set(location["keywords"])
            image_keywords = set(keywords)
            
            # Calculate match score
            if location_keywords:
                match_score = float(len(image_keywords & location_keywords)) / len(
                    location_keywords
                )
                match_score = max(0.0, min(1.0, match_score))
            else:
                match_score = 0.5

            scored_locations.append(
                {
                    "name": location["name"],
                    "country": location["country"],
                    "icon": location["icon"],
                    "description": location["description"],
                    "best_time": location.get("best_time", "Best in dry season"),
                    "match": match_score,
                }
            )

        # Sort by deterministic score and get top suggestions
        scored_locations.sort(key=lambda x: x["match"], reverse=True)
        top_suggestions = scored_locations[:3]

        # Calculate overall confidence
        avg_confidence = float(np.mean([s["match"] for s in top_suggestions]))

        deepseek = _call_deepseek_vision(req.image_data, top_suggestions)

        # Generate description
        description = f"Based on visual analysis: {', '.join(keywords[:3])}"
        photo_summary = ""
        best_time_to_visit = ""
        visit_window = ""
        analysis_source = "opencv"

        if deepseek:
            analysis_source = "deepseek"
            photo_summary = deepseek.get("scene_summary", "")
            best_time_to_visit = deepseek.get("best_time_to_visit", "")
            visit_window = deepseek.get("visit_window", "")
            location_hint = deepseek.get("likely_location", "")
            location_country = deepseek.get("likely_country", "")
            reason = deepseek.get("reason", "")

            # Re-rank local suggestions with DeepSeek country/place hints.
            scored_locations = _rerank_with_deepseek_hints(scored_locations, deepseek)
            top_suggestions = scored_locations[:3]
            if top_suggestions:
                avg_confidence = float(np.mean([s["match"] for s in top_suggestions]))

            # Prefer LLM summary when available.
            if photo_summary:
                description = photo_summary

            # Surface top-level timing guidance.
            if not best_time_to_visit and top_suggestions:
                best_time_to_visit = top_suggestions[0].get("best_time", "")

            if location_hint:
                if location_country:
                    description = f"{description} Likely place: {location_hint}, {location_country}."
                else:
                    description = f"{description} Likely place: {location_hint}."
            if reason:
                description = f"{description} {reason}"
        elif top_suggestions:
            best_time_to_visit = top_suggestions[0].get("best_time", "")
            description = (
                f"{description} DeepSeek hints unavailable; using local vision scoring."
            )

        return {
            "success": True,
            "suggestions": top_suggestions,
            "confidence": avg_confidence,
            "description": description,
            "photo_summary": photo_summary,
            "best_time_to_visit": best_time_to_visit,
            "visit_window": visit_window,
            "analysis_source": analysis_source,
            "analyzed_features": {
                "dominant_color": dominant_color,
                "brightness": float(brightness),
                "edge_density": float(edge_ratio),
                "keywords": keywords,
            },
        }

    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


def _call_deepseek_vision(image_data: str, top_suggestions: List[dict]) -> Optional[dict]:
    api_key = os.getenv("DEEPSEEK_API_KEY", "").strip()
    if not api_key:
        return None

    api_url = os.getenv("DEEPSEEK_API_URL", "https://api.deepseek.com/chat/completions").strip()
    model = os.getenv("DEEPSEEK_VISION_MODEL", "deepseek-chat").strip()

    suggestion_lines = []
    for suggestion in top_suggestions[:3]:
        suggestion_lines.append(
            f"- {suggestion.get('name', 'Unknown')} ({suggestion.get('country', '')}), best time: {suggestion.get('best_time', 'n/a')}"
        )

    prompt = (
        "Analyze this travel photo and return strict JSON only with keys: "
        "scene_summary, likely_location, likely_country, best_time_to_visit, visit_window, reason. "
        "Keep all fields short and practical. Use English. "
        "If the place looks Tunisian (for example Sidi Bou Said, Carthage, El Jem, Djerba, Tozeur), prefer Tunisia. "
        "Candidate places from local detector:\n"
        + "\n".join(suggestion_lines)
    )

    payload = {
        "model": model,
        "temperature": 0.2,
        "max_tokens": 350,
        "messages": [
            {
                "role": "system",
                "content": "You are a travel vision assistant. Return valid JSON only.",
            },
            {
                "role": "user",
                "content": [
                    {"type": "text", "text": prompt},
                    {"type": "image_url", "image_url": {"url": image_data}},
                ],
            },
        ],
    }

    req = urlrequest.Request(
        api_url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        },
        method="POST",
    )

    try:
        with urlrequest.urlopen(req, timeout=15) as response:
            raw = response.read().decode("utf-8")
    except (urlerror.URLError, TimeoutError):
        return None

    try:
        parsed = json.loads(raw)
        content = (
            parsed.get("choices", [{}])[0]
            .get("message", {})
            .get("content", "")
        )
    except Exception:
        return None

    if not isinstance(content, str) or not content:
        return None

    deepseek_json = _extract_json_object(content)
    if not deepseek_json:
        return None

    scene_summary = str(deepseek_json.get("scene_summary", "")).strip()
    likely_location = str(deepseek_json.get("likely_location", "")).strip()
    likely_country = str(deepseek_json.get("likely_country", "")).strip()
    best_time_to_visit = str(deepseek_json.get("best_time_to_visit", "")).strip()
    visit_window = str(deepseek_json.get("visit_window", "")).strip()
    reason = str(deepseek_json.get("reason", "")).strip()

    return {
        "scene_summary": scene_summary,
        "likely_location": likely_location,
        "likely_country": likely_country,
        "best_time_to_visit": best_time_to_visit,
        "visit_window": visit_window,
        "reason": reason,
    }


def _extract_json_object(text: str) -> Optional[dict]:
    start = text.find("{")
    end = text.rfind("}")
    if start == -1 or end == -1 or end <= start:
        return None

    candidate = text[start : end + 1]
    try:
        parsed = json.loads(candidate)
    except Exception:
        return None

    if isinstance(parsed, dict):
        return parsed

    return None


def _rerank_with_deepseek_hints(scored_locations: List[dict], hints: dict) -> List[dict]:
    country_hint = str(hints.get("likely_country", "")).lower()
    location_hint = str(hints.get("likely_location", "")).lower()
    reason_hint = str(hints.get("reason", "")).lower()

    hint_tokens = _hint_tokens(location_hint + " " + reason_hint)

    reranked: List[dict] = []
    for item in scored_locations:
        score = float(item.get("match", 0.0))
        name_l = str(item.get("name", "")).lower()
        country_l = str(item.get("country", "")).lower()
        description_l = str(item.get("description", "")).lower()

        if country_hint and country_hint in country_l:
            score += 0.35

        if location_hint and (
            location_hint in name_l
            or location_hint in description_l
            or any(token in name_l or token in description_l for token in hint_tokens)
        ):
            score += 0.25

        # Strong bias when DeepSeek indicates Tunisia.
        if "tunisia" in country_hint and "tunisia" in country_l:
            score += 0.30

        item_copy = dict(item)
        item_copy["match"] = max(0.0, min(1.0, score))
        reranked.append(item_copy)

    reranked.sort(key=lambda x: x["match"], reverse=True)
    return reranked


def _hint_tokens(text: str) -> List[str]:
    tokens = []
    for raw in text.replace(",", " ").replace(".", " ").split():
        token = raw.strip().lower()
        if len(token) >= 4:
            tokens.append(token)
    return tokens


def _get_dominant_color(hsv_image):
    """Extract dominant color name from HSV image"""
    h = hsv_image[:, :, 0]
    avg_hue = np.mean(h)

    if avg_hue < 15 or avg_hue > 240:
        return "red"
    elif 15 <= avg_hue < 40:
        return "orange"
    elif 40 <= avg_hue < 75:
        return "yellow"
    elif 75 <= avg_hue < 105:
        return "green"
    elif 105 <= avg_hue < 135:
        return "cyan"
    elif 135 <= avg_hue < 170:
        return "blue"
    else:
        return "purple"


def _analyze_image_features(image, dominant_color: str, brightness: float, edge_ratio: float):
    """Analyze image features and return relevant keywords"""
    keywords = []

    # Color-based keywords
    if dominant_color in ["blue", "cyan"]:
        keywords.extend(["water", "sky", "outdoor"])
    elif dominant_color in ["green"]:
        keywords.extend(["nature", "landscape", "outdoor"])
    elif dominant_color in ["red", "orange", "yellow"]:
        keywords.extend(["warm", "sunset", "monument"])
    else:
        keywords.extend(["cool", "shade"])

    # Brightness-based keywords
    if brightness > 180:
        keywords.extend(["bright", "marble", "white"])
    elif brightness < 100:
        keywords.extend(["dark", "historic", "ancient"])
    else:
        keywords.extend(["neutral"])

    # Edge density (architecture detection)
    if edge_ratio > 0.15:
        keywords.extend(["architecture", "structure", "building"])
    elif edge_ratio > 0.08:
        keywords.extend(["detail", "texture"])

    # Add generic keywords
    keywords.extend(["landmark", "tourist", "historic"])

    # Remove duplicates while preserving order
    seen = set()
    unique_keywords = []
    for kw in keywords:
        if kw not in seen:
            unique_keywords.append(kw)
            seen.add(kw)

    return unique_keywords


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("app:app", host="127.0.0.1", port=8001, reload=False)
