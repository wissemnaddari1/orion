from datetime import date, timedelta

from .models import ContractGenerationRequest


def _payment_terms(payload: ContractGenerationRequest, risk_level: str) -> str:
    base = (
        f"Total contract value is USD {payload.price:,.2f}. "
        "Payment is due according to written milestone acceptance records."
    )

    if risk_level == "LOW":
        return (
            base
            + " The Client shall release payment within 3 business days after each accepted deliverable."
        )

    if risk_level == "MEDIUM":
        return (
            base
            + " The Client shall release payment within 2 business days after each accepted deliverable. "
            "A 30% advance payment is required before work commencement."
        )

    return (
        base
        + " A mandatory milestone escrow structure applies: each milestone must be funded before milestone start. "
        "No work on subsequent milestones is required until the preceding milestone is paid and marked accepted."
    )


def _late_penalty_clause(risk_level: str) -> str:
    if risk_level == "LOW":
        return (
            "If either party causes delay without approved extension, that party shall provide prompt written notice "
            "and a revised timeline."
        )

    if risk_level == "MEDIUM":
        return (
            "If payment is late beyond 5 calendar days from due date, a late fee of 1.5% of the overdue amount per "
            "week applies, capped at 10% of the overdue amount."
        )

    return (
        "If payment is late beyond 3 calendar days from due date, a late fee of 2.0% of the overdue amount per week "
        "applies, capped at 15%. Repeated delay beyond 14 days constitutes material breach and permits immediate "
        "suspension of services."
    )


def _delivery_conditions(payload: ContractGenerationRequest) -> str:
    mode_text = "remote online collaboration" if payload.deliveryMode.value == "ONLINE" else "on-site execution"
    due_date = (date.today() + timedelta(days=payload.deliveryDays)).isoformat()
    return (
        f"Delivery mode is {payload.deliveryMode.value} ({mode_text}). "
        f"Final delivery deadline is {due_date} (within {payload.deliveryDays} calendar days from effective date)."
    )


def _deliverables_section(payload: ContractGenerationRequest, risk_level: str) -> str:
    milestones = payload.numberOfMilestones
    revision_note = "Two revision cycles are included." if risk_level == "LOW" else "One formal revision cycle per milestone is included."
    return (
        f"Primary deliverables shall satisfy: {payload.requirements.strip()}\n"
        f"Work shall be executed across {milestones} milestone(s), each with written acceptance evidence. "
        f"{revision_note}"
    )


def generate_contract_text(payload: ContractGenerationRequest, risk_level: str, risk_score: float) -> str:
    title = payload.serviceTitle.strip()
    description = payload.serviceDescription.strip()

    dispute_resolution = (
        "Parties shall first attempt good-faith negotiation for 10 business days. If unresolved, the dispute shall be "
        "submitted to confidential mediation before filing a formal claim."
    )

    termination_clause = (
        "Either party may terminate for material breach if not cured within 7 calendar days after written notice. "
        "Upon termination, approved completed work remains payable."
    )

    if risk_level == "HIGH":
        dispute_resolution = (
            "Parties shall first attempt good-faith negotiation for 7 business days, then mandatory mediation. "
            "If unresolved, either party may seek formal arbitration under applicable commercial rules."
        )
        termination_clause = (
            "Either party may terminate for material breach if not cured within 5 calendar days after written notice. "
            "In high-risk scenarios, repeated payment delay or refusal to approve objectively completed milestones "
            "constitutes immediate grounds for suspension and termination."
        )

    return f"""FREELANCE SERVICES AGREEMENT

Service Title: {title}
Risk Assessment: {risk_level} (score: {risk_score:.4f})

1. Scope of Work
The Freelancer shall perform the following professional services:
{description}

2. Deliverables
{_deliverables_section(payload, risk_level)}

3. Payment Terms
{_payment_terms(payload, risk_level)}

4. Delivery Conditions
{_delivery_conditions(payload)}

5. Late Penalty Clause
{_late_penalty_clause(risk_level)}

6. Dispute Resolution
{dispute_resolution}

7. Termination Clause
{termination_clause}

This Agreement is generated automatically from structured commercial inputs and risk policy rules.
""".strip()
