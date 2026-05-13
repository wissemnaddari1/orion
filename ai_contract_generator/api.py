from fastapi import FastAPI, HTTPException

from .models import ContractGenerationRequest, ContractGenerationResponse
from .risk_service import calculate_contract_risk
from .template_service import generate_contract_text

app = FastAPI(title="AI Contract Generator", version="1.0.0")


@app.post("/generate-contract", response_model=ContractGenerationResponse)
def generate_contract(payload: ContractGenerationRequest):
    try:
        risk = calculate_contract_risk(payload)
    except FileNotFoundError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Risk model evaluation failed: {exc}") from exc

    contract_text = generate_contract_text(
        payload=payload,
        risk_level=risk.risk_level,
        risk_score=risk.risk_score,
    )

    return ContractGenerationResponse(
        generatedContract=contract_text,
        riskScore=risk.risk_score,
        riskLevel=risk.risk_level,
    )


@app.get("/health")
def health():
    return {"status": "ok"}
