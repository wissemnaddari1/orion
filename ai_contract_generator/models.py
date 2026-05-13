from enum import Enum

from pydantic import BaseModel, Field


class DeliveryMode(str, Enum):
    ONLINE = "ONLINE"
    ONSITE = "ONSITE"


class ContractGenerationRequest(BaseModel):
    serviceTitle: str = Field(min_length=2, max_length=255)
    serviceDescription: str = Field(min_length=2)
    requirements: str = Field(min_length=2)
    price: float = Field(gt=0)
    deliveryDays: int = Field(gt=0)
    deliveryMode: DeliveryMode
    clientRating: float = Field(ge=0.0, le=5.0)
    freelancerRating: float = Field(ge=0.0, le=5.0)
    negotiationCount: int = Field(ge=0)
    numberOfMilestones: int = Field(ge=1)


class ContractGenerationResponse(BaseModel):
    generatedContract: str
    riskScore: float
    riskLevel: str
