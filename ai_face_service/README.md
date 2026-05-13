# Orion Face Recognition Service

Local face recognition microservice using **face-recognition** (dlib). No paid APIs; runs entirely on your machine.

## Endpoints

- **POST /embed** — Compute face embedding from base64 image.
  - Body: `{ "image_base64": "data:image/jpeg;base64,..." }`
  - Returns: `{ "faces": 1, "embedding": [float, ...] }` or 422 with `NO_FACE` / `MULTIPLE_FACES` / `BAD_IMAGE`

- **POST /match** — Match image against candidate embeddings (Euclidean distance).
  - Body: `{ "image_base64": "...", "candidates": [{ "user_id": 123, "embedding": [...] }], "threshold": 0.6 }`
  - Returns: `{ "matched": true|false, "user_id": 123|null, "distance": float|null, "threshold": float }`

- **GET /health** — Health check.

## Installation (Linux / WSL2 / macOS)

```bash
cd ai_face_service
python3 -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8002
```

## Windows (native)

The **face-recognition** library depends on **dlib**, which can be difficult to install on Windows. Options:

### Option 1: Using dlib-bin (Recommended - No CMake needed)

For Python 3.13 on Windows, use the pre-built `dlib-bin` wheel:

```bash
cd ai_face_service
python -m venv .venv
.venv\Scripts\activate
pip install dlib-bin
pip install --no-deps face-recognition
pip install face-recognition-models
pip install fastapi uvicorn[standard] numpy Pillow
```

Or install from requirements.txt (which includes dlib-bin):
```bash
pip install dlib-bin
pip install --no-deps -r requirements.txt
pip install face-recognition-models
```

### Option 2: Other Windows Options

1. **Use WSL2**: Run the service inside WSL2 and call it from Symfony (e.g. `http://127.0.0.1:8002` from Windows).
2. **Docker**: Use a Linux-based image (see below).
3. **Conda**: `conda install -c conda-forge dlib` then `pip install face-recognition fastapi uvicorn`.
4. **Install CMake**: If you prefer building from source, install CMake from [cmake.org](https://cmake.org/download/) and Visual Studio Build Tools, then `pip install face-recognition`.

## Docker (any OS)

```bash
cd ai_face_service
docker build -t orion-face-service .
docker run -p 8002:8002 orion-face-service
```

Create a `Dockerfile` in this folder:

```dockerfile
FROM python:3.11-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential cmake libopenblas-dev liblapack-dev libx11-dev \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY main.py .
EXPOSE 8002
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8002"]
```

## Symfony config

Set in `.env`:

```
FACE_SERVICE_URL=http://127.0.0.1:8002
```

If the service runs in WSL2 or another host, use that URL (e.g. `http://localhost:8002`).
