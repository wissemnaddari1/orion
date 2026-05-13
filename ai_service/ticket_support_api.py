"""
Ticket Support AI API
FastAPI service exposing the TicketSupportAI for suggesting solutions to support tickets.
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
import os
import time
from datetime import datetime

from ticket_support_ai import TicketSupportAI

app = FastAPI(
    title="Ticket Support AI API",
    description="AI-powered support ticket solution suggestion service",
    version="1.0.0"
)

# allow_credentials=False so allow_origins=["*"] is valid for browsers calling this API directly.
# Symfony/Java server-to-server calls do not need CORS.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)

KNOWLEDGE_BASE_PATH = os.path.join(os.path.dirname(__file__), "data", "ticket_knowledge_base.json")
ticket_ai = TicketSupportAI(knowledge_base_path=KNOWLEDGE_BASE_PATH)

# Monotonic uptime for ops probes (Symfony, Spring, or dashboards can share one Python process).
_OPS_STARTED_MONO = time.monotonic()


def _listen_port() -> int:
    return int(os.environ.get("ORION_PORT_TICKET_AI", os.environ.get("PORT", "8015")))


class TicketRequest(BaseModel):
    subject: str
    message: str
    category: Optional[str] = None


class TicketSolutionResponse(BaseModel):
    suggested_solution: Optional[str]
    confidence_score: float
    escalate_to_admin: bool
    similar_ticket: Optional[dict] = None
    processed_at: str


class UpdateKnowledgeBaseRequest(BaseModel):
    subject: str
    message: str
    resolution: str
    category: Optional[str] = None


@app.get("/")
async def root():
    return {
        "service": "Ticket Support AI",
        "status": "running",
        "version": "1.0.0",
        "routes": {
            "solve": "POST /ai/solve",
            "knowledge_update": "POST /ai/knowledge-base/update",
            "knowledge_retrain": "POST /ai/knowledge-base/retrain",
            "knowledge_stats": "GET /ai/knowledge-base/stats",
            "ops_metrics": "GET /ai/ops/metrics",
            "ops_health": "GET /ai/ops/health",
            "health": "GET /health",
        },
        "note": "Java TICKET_AI_API_URL and Symfony TICKET_SUPPORT_AI_URL use the same base (e.g. http://127.0.0.1:8015).",
    }


@app.get("/health")
async def health():
    """Lightweight probe for load balancers and Symfony/Java health checks."""
    return {"status": "ok", "service": "ticket-support-ai"}


@app.get("/ai/ops/metrics")
async def ops_metrics():
    """
    Ops / monitoring endpoint. Spring or other gateways often probe this path.
    This service is a normal HTTP API: run Python once; point both Symfony and Java
    at the same base URL (e.g. http://127.0.0.1:8015) — no duplicate AI stack required.
    """
    return {
        "service": "ticket-support-ai",
        "status": "UP",
        "uptime_seconds": round(time.monotonic() - _OPS_STARTED_MONO, 3),
        "listen_port": _listen_port(),
        "knowledge_base_tickets": len(ticket_ai.tickets),
    }


@app.get("/ai/ops/health")
async def ops_health():
    """Alias for gateways expecting /ai/ops/* health paths."""
    return {"status": "UP", "service": "ticket-support-ai"}


@app.post("/ai/solve", response_model=TicketSolutionResponse)
async def solve_ticket(request: TicketRequest):
    try:
        result = ticket_ai.suggest_solution(
            subject=request.subject,
            message=request.message,
            confidence_threshold=0.75
        )
        result["processed_at"] = datetime.now().isoformat()
        return result
    except Exception as e:
        print(f"Error solving ticket: {str(e)}")
        return {
            "suggested_solution": None,
            "confidence_score": 0.0,
            "escalate_to_admin": True,
            "similar_ticket": None,
            "processed_at": datetime.now().isoformat()
        }


@app.post("/ai/knowledge-base/update")
async def update_knowledge_base(request: UpdateKnowledgeBaseRequest):
    try:
        ticket_ai.add_ticket(
            subject=request.subject,
            message=request.message,
            resolution=request.resolution,
            category=request.category
        )
        ticket_ai.save_knowledge_base(KNOWLEDGE_BASE_PATH)
        return {
            "status": "success",
            "message": "Knowledge base updated successfully",
            "updated_at": datetime.now().isoformat()
        }
    except Exception as e:
        print(f"Error updating knowledge base: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/ai/knowledge-base/retrain")
async def retrain_model():
    try:
        ticket_ai.update_model()
        return {
            "status": "success",
            "message": "Model retrained successfully",
            "retrained_at": datetime.now().isoformat(),
            "total_tickets": len(ticket_ai.tickets)
        }
    except Exception as e:
        print(f"Error retraining model: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/ai/knowledge-base/stats")
async def get_knowledge_base_stats():
    return {
        "total_tickets": len(ticket_ai.tickets),
        "categories": _get_category_stats(),
        "last_updated": datetime.now().isoformat()
    }


def _get_category_stats():
    categories = {}
    for ticket in ticket_ai.tickets:
        category = ticket.get("category", "Uncategorized")
        categories[category] = categories.get(category, 0) + 1
    return categories


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="127.0.0.1", port=_listen_port())
