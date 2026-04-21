# Face ID OpenCV Service

This small Python API extracts a face embedding from webcam snapshots and is consumed by Symfony routes:

- `POST /panel/face-id/capture` (enrollment)
- `POST /login/face-id` (authentication)

## 1) Create Python environment and install packages

```powershell
cd faceid_service
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## 2) Run the service

```powershell
uvicorn app:app --host 127.0.0.1 --port 8001 --reload
```

Health check:

```powershell
curl http://127.0.0.1:8001/health
```

Expected response:

```json
{"ok": true}
```

## 3) Symfony configuration

Set environment variable used by `App\Service\FaceIdService`:

```powershell
$env:FACE_ID_API_URL = "http://127.0.0.1:8001"
```

If not set, Symfony defaults to `http://127.0.0.1:8001`.

## Notes

- The service uses OpenCV Haar face detection + handcrafted texture embeddings.
- Match threshold is currently `0.82` in Symfony and `/compare`.
- Re-enroll users once after this update, since old raw image blobs are ignored.

## DeepSeek image analysis (AI Guide)

To let AI Guide describe the uploaded photo and suggest when to visit, set:

```powershell
$env:DEEPSEEK_API_KEY = "your_deepseek_api_key"
```

Optional settings:

```powershell
$env:DEEPSEEK_API_URL = "https://api.deepseek.com/chat/completions"
$env:DEEPSEEK_VISION_MODEL = "deepseek-chat"
```

If no DeepSeek key is configured, the service falls back to local OpenCV analysis.
