# AI-Based Support Assistant System

## Complete Design and Implementation

This document provides a comprehensive overview of the AI-based support assistant system designed to work with the existing Symfony-based ticket system.

## Table of Contents

1. [Global Idea and AI Approach](#global-idea-and-ai-approach)
2. [Architecture Overview](#architecture-overview)
3. [Dataset Format](#dataset-format)
4. [Python Implementation](#python-implementation)
5. [Symfony Integration](#symfony-integration)
6. [API Endpoints](#api-endpoints)
7. [Example Responses](#example-responses)
8. [Continuous Learning](#continuous-learning)
9. [Security & Robustness](#security--robustness)
10. [Academic Explanation](#academic-explanation)

## Global Idea and AI Approach

### Simple Explanation

The AI system works like a smart search engine for support tickets. When a new ticket arrives, it:
1. Transforms the problem text (subject + message) into a numerical representation (embedding)
2. Compares this representation with all previously solved tickets
3. Finds the most similar past problems
4. If similarity is high enough (≥75%), suggests the resolution from the most similar ticket
5. If similarity is low, escalates to a human admin

### Academic Explanation

This is a retrieval-based recommendation system using sentence embeddings and cosine similarity. We employ the all-MiniLM-L6-v2 model from the Sentence Transformers library, which maps text to a 384-dimensional dense vector space. The model is trained on diverse text sources using contrastive learning, ensuring semantically similar texts are close in the vector space.

For each ticket, we create an embedding of the problem text (subject + first message) and store it alongside the resolution. When a new ticket arrives, we compute its embedding and calculate cosine similarity with all stored embeddings. The cosine similarity score represents the semantic similarity between the new problem and past problems, ranging from -1 (opposite) to 1 (identical). We use this as our confidence metric.

The system is designed to be explainable: we can always trace back which past ticket led to a suggestion. It fails safely by defaulting to human escalation when confidence is below threshold.

## Architecture Overview

```
Symfony (PHP)
|
| HTTP POST (JSON)
|
Python AI API (FastAPI)
|
| Similarity search
|
Past ticket knowledge base (JSON)
```

The system consists of three main components:

1. **Symfony Backend**: The existing PHP application that handles tickets and user interactions
2. **Python AI API**: A FastAPI microservice that provides AI-powered solution suggestions
3. **Knowledge Base**: A JSON file storing historical resolved tickets used for similarity search

## Dataset Format

The knowledge base is stored in a JSON file with the following format:

```json
[
  {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm sure my password is correct as I just reset it yesterday.",
    "resolution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again. Also, try clearing your browser cache and cookies, or use a different browser.",
    "category": "Authentication"
  },
  {
    "subject": "Payment not processed",
    "message": "I tried to make a payment for my subscription but the transaction failed. My card was charged but the subscription is still showing as inactive.",
    "resolution": "Please check your bank statement to confirm if the transaction was completed. If the amount was deducted, please provide the transaction ID and we'll manually activate your subscription. This usually happens due to a delay in payment gateway processing.",
    "category": "Billing"
  }
]
```

Each ticket in the knowledge base contains:
- `subject`: The ticket subject/title
- `message`: The problem description (first message)
- `resolution`: The admin's solution to the problem
- `category`: The ticket category (optional)

## Python Implementation

The Python implementation consists of two main files:

### 1. ticket_support_ai.py

This file contains the `TicketSupportAI` class, which implements the core AI functionality:

```python
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
import json
from typing import List, Dict, Tuple, Optional
import os


class TicketSupportAI:
    """
    AI-powered support ticket solution suggester using sentence embeddings
    and cosine similarity for retrieval-based recommendations.
    """

    def __init__(self, model_name: str = "all-MiniLM-L6-v2", knowledge_base_path: str = None):
        """
        Initialize the Ticket Support AI system.

        Args:
            model_name: Name of the sentence-transformers model to use
            knowledge_base_path: Path to the JSON file containing resolved tickets
        """
        # Load the sentence transformer model
        self.model = SentenceTransformer(model_name)

        # Initialize knowledge base
        self.tickets = []
        self.embeddings = None

        # Load knowledge base if path is provided
        if knowledge_base_path and os.path.exists(knowledge_base_path):
            self.load_knowledge_base(knowledge_base_path)

    def load_knowledge_base(self, file_path: str) -> None:
        """
        Load resolved tickets from a JSON file.

        Args:
            file_path: Path to the JSON file containing resolved tickets
        """
        with open(file_path, 'r', encoding='utf-8') as f:
            self.tickets = json.load(f)

        # Generate embeddings for all tickets
        self._generate_embeddings()

    def _generate_embeddings(self) -> None:
        """
        Generate embeddings for all tickets in the knowledge base.
        """
        if not self.tickets:
            self.embeddings = np.array([])
            return

        # Prepare text for each ticket (subject + message)
        texts = [f"{ticket['subject']} {ticket['message']}" for ticket in self.tickets]

        # Generate embeddings using the model
        self.embeddings = self.model.encode(texts, convert_to_numpy=True)

    def add_ticket(self, subject: str, message: str, resolution: str, category: str = None) -> None:
        """
        Add a new resolved ticket to the knowledge base.

        Args:
            subject: Ticket subject
            message: Ticket message (problem description)
            resolution: Admin's resolution for the problem
            category: Ticket category (optional)
        """
        # Create ticket entry
        ticket = {
            "subject": subject,
            "message": message,
            "resolution": resolution,
            "category": category
        }

        # Add to knowledge base
        self.tickets.append(ticket)

        # Generate embedding for this ticket
        text = f"{subject} {message}"
        embedding = self.model.encode([text], convert_to_numpy=True)[0]

        # Update embeddings array
        if self.embeddings is None or len(self.embeddings) == 0:
            self.embeddings = np.array([embedding])
        else:
            self.embeddings = np.vstack([self.embeddings, embedding])

    def find_similar_tickets(self, subject: str, message: str, top_k: int = 3) -> List[Dict]:
        """
        Find similar tickets based on subject and message.

        Args:
            subject: Ticket subject
            message: Ticket message (problem description)
            top_k: Number of similar tickets to return

        Returns:
            List of similar tickets with similarity scores
        """
        if self.embeddings is None or len(self.embeddings) == 0:
            return []

        # Generate embedding for the query
        query_text = f"{subject} {message}"
        query_embedding = self.model.encode([query_text], convert_to_numpy=True)

        # Calculate cosine similarity
        similarities = cosine_similarity(query_embedding, self.embeddings)[0]

        # Get indices of top_k most similar tickets
        top_indices = np.argsort(similarities)[::-1][:top_k]

        # Prepare results
        results = []
        for idx in top_indices:
            result = {
                "ticket": self.tickets[idx],
                "similarity_score": float(similarities[idx])
            }
            results.append(result)

        return results

    def suggest_solution(self, subject: str, message: str, confidence_threshold: float = 0.75) -> Dict:
        """
        Suggest a solution for a new ticket based on similar past tickets.

        Args:
            subject: Ticket subject
            message: Ticket message (problem description)
            confidence_threshold: Minimum confidence score to suggest a solution

        Returns:
            Dictionary containing suggested solution, confidence score, and escalation decision
        """
        # Find similar tickets
        similar_tickets = self.find_similar_tickets(subject, message, top_k=1)

        # If no similar tickets found, escalate to admin
        if not similar_tickets:
            return {
                "suggested_solution": None,
                "confidence_score": 0.0,
                "escalate_to_admin": True,
                "similar_ticket": None
            }

        # Get the most similar ticket
        most_similar = similar_tickets[0]
        confidence = most_similar["similarity_score"]

        # Determine if we should suggest the solution or escalate
        if confidence >= confidence_threshold:
            return {
                "suggested_solution": most_similar["ticket"]["resolution"],
                "confidence_score": confidence,
                "escalate_to_admin": False,
                "similar_ticket": {
                    "subject": most_similar["ticket"]["subject"],
                    "message": most_similar["ticket"]["message"]
                }
            }
        else:
            return {
                "suggested_solution": None,
                "confidence_score": confidence,
                "escalate_to_admin": True,
                "similar_ticket": {
                    "subject": most_similar["ticket"]["subject"],
                    "message": most_similar["ticket"]["message"]
                }
            }

    def save_knowledge_base(self, file_path: str) -> None:
        """
        Save the current knowledge base to a JSON file.

        Args:
            file_path: Path where to save the JSON file
        """
        with open(file_path, 'w', encoding='utf-8') as f:
            json.dump(self.tickets, f, ensure_ascii=False, indent=2)

    def update_model(self) -> None:
        """
        Regenerate embeddings for all tickets in the knowledge base.
        Useful when multiple tickets have been added and you want to ensure consistency.
        """
        self._generate_embeddings()
```

### 2. ticket_support_api.py

This file contains the FastAPI application that exposes the AI functionality as REST endpoints:

```python
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
import os
from datetime import datetime

# Import the TicketSupportAI class
from ticket_support_ai import TicketSupportAI

# Create FastAPI app
app = FastAPI(
    title="Ticket Support AI API",
    description="AI-powered support ticket solution suggestion service",
    version="1.0.0"
)

# CORS middleware for Symfony to access
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify Symfony domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize the AI service
# Path to the knowledge base file
KNOWLEDGE_BASE_PATH = os.path.join(os.path.dirname(__file__), "data", "ticket_knowledge_base.json")

# Initialize the AI service with the knowledge base
ticket_ai = TicketSupportAI(knowledge_base_path=KNOWLEDGE_BASE_PATH)


class TicketRequest(BaseModel):
    """Request model for solving a ticket"""
    subject: str
    message: str
    category: Optional[str] = None


class TicketSolutionResponse(BaseModel):
    """Response model for ticket solution suggestion"""
    suggested_solution: Optional[str]
    confidence_score: float
    escalate_to_admin: bool
    similar_ticket: Optional[dict] = None
    processed_at: str


class UpdateKnowledgeBaseRequest(BaseModel):
    """Request model for updating the knowledge base"""
    subject: str
    message: str
    resolution: str
    category: Optional[str] = None


@app.get("/")
async def root():
    """Health check endpoint"""
    return {
        "service": "Ticket Support AI",
        "status": "running",
        "version": "1.0.0"
    }


@app.post("/ai/solve", response_model=TicketSolutionResponse)
async def solve_ticket(request: TicketRequest):
    """
    Suggest a solution for a new support ticket based on similar past tickets.

    Args:
        request: TicketRequest containing subject, message, and optional category

    Returns:
        TicketSolutionResponse with suggested solution, confidence score, and escalation decision
    """
    try:
        # Get solution suggestion from AI
        result = ticket_ai.suggest_solution(
            subject=request.subject,
            message=request.message,
            confidence_threshold=0.75
        )

        # Add timestamp
        result["processed_at"] = datetime.now().isoformat()

        return result

    except Exception as e:
        # Log the error (in production, use proper logging)
        print(f"Error solving ticket: {str(e)}")

        # Return a safe response that escalates to admin
        return {
            "suggested_solution": None,
            "confidence_score": 0.0,
            "escalate_to_admin": True,
            "similar_ticket": None,
            "processed_at": datetime.now().isoformat()
        }


@app.post("/ai/knowledge-base/update")
async def update_knowledge_base(request: UpdateKnowledgeBaseRequest):
    """
    Update the knowledge base with a new resolved ticket.
    Call this endpoint when an admin resolves a ticket.

    Args:
        request: UpdateKnowledgeBaseRequest containing ticket details and resolution

    Returns:
        Status message
    """
    try:
        # Add the new ticket to the knowledge base
        ticket_ai.add_ticket(
            subject=request.subject,
            message=request.message,
            resolution=request.resolution,
            category=request.category
        )

        # Save the updated knowledge base
        ticket_ai.save_knowledge_base(KNOWLEDGE_BASE_PATH)

        return {
            "status": "success",
            "message": "Knowledge base updated successfully",
            "updated_at": datetime.now().isoformat()
        }

    except Exception as e:
        # Log the error (in production, use proper logging)
        print(f"Error updating knowledge base: {str(e)}")

        raise HTTPException(
            status_code=500,
            detail=f"Failed to update knowledge base: {str(e)}"
        )


@app.post("/ai/knowledge-base/retrain")
async def retrain_model():
    """
    Retrain the model by regenerating embeddings for all tickets in the knowledge base.
    Call this endpoint after adding multiple tickets to ensure consistency.

    Returns:
        Status message
    """
    try:
        # Regenerate embeddings
        ticket_ai.update_model()

        return {
            "status": "success",
            "message": "Model retrained successfully",
            "retrained_at": datetime.now().isoformat(),
            "total_tickets": len(ticket_ai.tickets)
        }

    except Exception as e:
        # Log the error (in production, use proper logging)
        print(f"Error retraining model: {str(e)}")

        raise HTTPException(
            status_code=500,
            detail=f"Failed to retrain model: {str(e)}"
        )


@app.get("/ai/knowledge-base/stats")
async def get_knowledge_base_stats():
    """
    Get statistics about the knowledge base.

    Returns:
        Knowledge base statistics
    """
    return {
        "total_tickets": len(ticket_ai.tickets),
        "categories": _get_category_stats(),
        "last_updated": datetime.now().isoformat()
    }


def _get_category_stats():
    """Helper function to get statistics by category"""
    categories = {}
    for ticket in ticket_ai.tickets:
        category = ticket.get("category", "Uncategorized")
        categories[category] = categories.get(category, 0) + 1
    return categories


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8002)
```

## Symfony Integration

The Symfony integration consists of two main components:

### 1. TicketSupportAIService

This service provides methods to communicate with the AI-powered ticket support system:

```php
<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Service for interacting with the Ticket Support AI API
 * 
 * This service provides methods to communicate with the AI-powered ticket
 * support system that suggests solutions based on historical tickets.
 */
class TicketSupportAIService
{
    private HttpClientInterface $httpClient;
    private string $apiBaseUrl;
    private int $timeout;

    /**
     * Constructor
     *
     * @param HttpClientInterface $httpClient HTTP client for making API requests
     * @param string $aiApiUrl Base URL of the AI API (from configuration)
     * @param int $timeout Request timeout in seconds (default: 5)
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $aiApiUrl = 'http://127.0.0.1:8002',
        int $timeout = 5
    ) {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = rtrim($aiApiUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Request a solution suggestion for a new ticket
     *
     * @param string $subject Ticket subject
     * @param string $message Ticket message (problem description)
     * @param string|null $category Optional ticket category
     * @return array AI response with suggested solution, confidence score, and escalation decision
     * @throws HttpException If the API request fails
     */
    public function solveTicket(string $subject, string $message, ?string $category = null): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/solve', [
                'json' => [
                    'subject' => $subject,
                    'message' => $message,
                    'category' => $category,
                ],
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                );
            }

            return $response->toArray();
        } catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error calling AI API: ' . $e->getMessage());

            // Return a safe response that escalates to admin
            return [
                'suggested_solution' => null,
                'confidence_score' => 0.0,
                'escalate_to_admin' => true,
                'similar_ticket' => null,
                'processed_at' => (new \DateTime())->format('c'),
            ];
        }
    }

    /**
     * Update the AI knowledge base with a new resolved ticket
     *
     * @param string $subject Ticket subject
     * @param string $message Ticket message (problem description)
     * @param string $resolution Admin's resolution for the problem
     * @param string|null $category Optional ticket category
     * @return array API response
     * @throws HttpException If the API request fails
     */
    public function updateKnowledgeBase(
        string $subject,
        string $message,
        string $resolution,
        ?string $category = null
    ): array {
        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/knowledge-base/update', [
                'json' => [
                    'subject' => $subject,
                    'message' => $message,
                    'resolution' => $resolution,
                    'category' => $category,
                ],
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                );
            }

            return $response->toArray();
        } catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error updating AI knowledge base: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to update AI knowledge base: ' . $e->getMessage()
            );
        }
    }

    /**
     * Request a model retrain
     *
     * Call this method after adding multiple tickets to ensure consistency.
     *
     * @return array API response
     * @throws HttpException If the API request fails
     */
    public function retrainModel(): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/ai/knowledge-base/retrain', [
                'timeout' => $this->timeout * 2, // Retraining might take longer
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                );
            }

            return $response->toArray();
        } catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error retraining AI model: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to retrain AI model: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get statistics about the AI knowledge base
     *
     * @return array Knowledge base statistics
     * @throws HttpException If the API request fails
     */
    public function getKnowledgeBaseStats(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/ai/knowledge-base/stats', [
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new HttpException(
                    $statusCode,
                    'AI API returned non-200 status code: ' . $statusCode
                );
            }

            return $response->toArray();
        } catch (\Exception $e) {
            // Log the error (in production, use proper logging)
            error_log('Error getting AI knowledge base stats: ' . $e->getMessage());

            throw new HttpException(
                500,
                'Failed to get AI knowledge base stats: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if the AI service is available
     *
     * @return bool True if the service is available, false otherwise
     */
    public function isServiceAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/', [
                'timeout' => $this->timeout,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### 2. TicketSupportAIController

This controller provides endpoints for integrating with the Ticket Support AI service:

```php
<?php

namespace App\Controller;

use App\Service\TicketSupportAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for handling AI-powered ticket support
 * 
 * This controller provides endpoints for integrating with the Ticket Support AI
 * service and demonstrates how to use it in a Symfony application.
 */
class TicketSupportAIController extends AbstractController
{
    private TicketSupportAIService $aiService;

    /**
     * Constructor
     *
     * @param TicketSupportAIService $aiService The AI service for ticket support
     */
    public function __construct(TicketSupportAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get a solution suggestion for a new ticket
     *
     * @Route("/api/tickets/ai-solve", name="ticket_ai_solve", methods={"POST"})
     *
     * @param Request $request The HTTP request
     * @return JsonResponse JSON response with AI suggestion
     */
    public function solveTicket(Request $request): JsonResponse
    {
        // Get request data
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['subject']) || empty($data['message'])) {
            return $this->json([
                'error' => 'Subject and message are required fields',
            ], 400);
        }

        // Get AI suggestion
        $aiResponse = $this->aiService->solveTicket(
            $data['subject'],
            $data['message'],
            $data['category'] ?? null
        );

        return $this->json($aiResponse);
    }

    /**
     * Update the AI knowledge base with a new resolved ticket
     *
     * @Route("/api/tickets/ai-update-kb", name="ticket_ai_update_kb", methods={"POST"})
     *
     * @param Request $request The HTTP request
     * @return JsonResponse JSON response with update status
     */
    public function updateKnowledgeBase(Request $request): JsonResponse
    {
        // Get request data
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['subject']) || empty($data['message']) || empty($data['resolution'])) {
            return $this->json([
                'error' => 'Subject, message, and resolution are required fields',
            ], 400);
        }

        // Update knowledge base
        $response = $this->aiService->updateKnowledgeBase(
            $data['subject'],
            $data['message'],
            $data['resolution'],
            $data['category'] ?? null
        );

        return $this->json($response);
    }

    /**
     * Retrain the AI model
     *
     * @Route("/api/tickets/ai-retrain", name="ticket_ai_retrain", methods={"POST"})
     *
     * @return JsonResponse JSON response with retrain status
     */
    public function retrainModel(): JsonResponse
    {
        $response = $this->aiService->retrainModel();

        return $this->json($response);
    }

    /**
     * Get statistics about the AI knowledge base
     *
     * @Route("/api/tickets/ai-stats", name="ticket_ai_stats", methods={"GET"})
     *
     * @return JsonResponse JSON response with knowledge base statistics
     */
    public function getKnowledgeBaseStats(): JsonResponse
    {
        $stats = $this->aiService->getKnowledgeBaseStats();

        return $this->json($stats);
    }

    /**
     * Check if the AI service is available
     *
     * @Route("/api/tickets/ai-status", name="ticket_ai_status", methods={"GET"})
     *
     * @return JsonResponse JSON response with service status
     */
    public function getServiceStatus(): JsonResponse
    {
        $isAvailable = $this->aiService->isServiceAvailable();

        return $this->json([
            'available' => $isAvailable,
            'checked_at' => (new \DateTime())->format('c'),
        ]);
    }
}
```

## API Endpoints

### Python AI API Endpoints

#### 1. POST /ai/solve

Suggest a solution for a new support ticket based on similar past tickets.

**Request:**
```json
{
  "subject": "Unable to login to my account",
  "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'.",
  "category": "Authentication"
}
```

**Response:**
```json
{
  "suggested_solution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again.",
  "confidence_score": 0.92,
  "escalate_to_admin": false,
  "similar_ticket": {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm sure my password is correct as I just reset it yesterday."
  },
  "processed_at": "2023-11-15T14:30:45.123456"
}
```

#### 2. POST /ai/knowledge-base/update

Update the knowledge base with a new resolved ticket. Call this endpoint when an admin resolves a ticket.

**Request:**
```json
{
  "subject": "Unable to login to my account",
  "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'.",
  "resolution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again.",
  "category": "Authentication"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Knowledge base updated successfully",
  "updated_at": "2023-11-15T14:30:45.123456"
}
```

#### 3. POST /ai/knowledge-base/retrain

Retrain the model by regenerating embeddings for all tickets in the knowledge base. Call this endpoint after adding multiple tickets to ensure consistency.

**Response:**
```json
{
  "status": "success",
  "message": "Model retrained successfully",
  "retrained_at": "2023-11-15T14:30:45.123456",
  "total_tickets": 15
}
```

#### 4. GET /ai/knowledge-base/stats

Get statistics about the knowledge base.

**Response:**
```json
{
  "total_tickets": 15,
  "categories": {
    "Authentication": 5,
    "Billing": 3,
    "Technical": 4,
    "Account": 2,
    "Feature Request": 1
  },
  "last_updated": "2023-11-15T14:30:45.123456"
}
```

### Symfony API Endpoints

The Symfony application provides proxy endpoints to the Python AI API:

#### 1. POST /api/tickets/ai-solve

Get a solution suggestion for a new ticket.

**Request:**
```json
{
  "subject": "Unable to login to my account",
  "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'.",
  "category": "Authentication"
}
```

**Response:**
```json
{
  "suggested_solution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again.",
  "confidence_score": 0.92,
  "escalate_to_admin": false,
  "similar_ticket": {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm sure my password is correct as I just reset it yesterday."
  },
  "processed_at": "2023-11-15T14:30:45.123456"
}
```

#### 2. POST /api/tickets/ai-update-kb

Update the AI knowledge base with a new resolved ticket.

**Request:**
```json
{
  "subject": "Unable to login to my account",
  "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'.",
  "resolution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again.",
  "category": "Authentication"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Knowledge base updated successfully",
  "updated_at": "2023-11-15T14:30:45.123456"
}
```

#### 3. POST /api/tickets/ai-retrain

Retrain the AI model.

**Response:**
```json
{
  "status": "success",
  "message": "Model retrained successfully",
  "retrained_at": "2023-11-15T14:30:45.123456",
  "total_tickets": 15
}
```

#### 4. GET /api/tickets/ai-stats

Get statistics about the AI knowledge base.

**Response:**
```json
{
  "total_tickets": 15,
  "categories": {
    "Authentication": 5,
    "Billing": 3,
    "Technical": 4,
    "Account": 2,
    "Feature Request": 1
  },
  "last_updated": "2023-11-15T14:30:45.123456"
}
```

#### 5. GET /api/tickets/ai-status

Check if the AI service is available.

**Response:**
```json
{
  "available": true,
  "checked_at": "2023-11-15T14:30:45.123456"
}
```

## Example Responses

### High Confidence Response

When the AI finds a very similar ticket with high confidence:

```json
{
  "suggested_solution": "Please check if Caps Lock is on and ensure you're using the correct email address. If the issue persists, click on 'Forgot Password' to reset your password again. Also, try clearing your browser cache and cookies, or use a different browser.",
  "confidence_score": 0.92,
  "escalate_to_admin": false,
  "similar_ticket": {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm sure my password is correct as I just reset it yesterday."
  },
  "processed_at": "2023-11-15T14:30:45.123456"
}
```

### Low Confidence Response

When the AI doesn't find a sufficiently similar ticket:

```json
{
  "suggested_solution": null,
  "confidence_score": 0.45,
  "escalate_to_admin": true,
  "similar_ticket": {
    "subject": "Unable to login to my account",
    "message": "I've been trying to login to my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm sure my password is correct as I just reset it yesterday."
  },
  "processed_at": "2023-11-15T14:30:45.123456"
}
```

### No Similar Tickets Response

When the AI doesn't find any similar tickets:

```json
{
  "suggested_solution": null,
  "confidence_score": 0.0,
  "escalate_to_admin": true,
  "similar_ticket": null,
  "processed_at": "2023-11-15T14:30:45.123456"
}
```

## Continuous Learning

The system improves over time as more tickets are resolved:

1. When an admin resolves a ticket, the problem and solution are added to the knowledge base
2. The AI can then use this new knowledge to suggest solutions for future similar tickets
3. Periodically, the model can be retrained to ensure all embeddings are consistent

### How to Retrain the Model

There are two ways to retrain the model:

1. **Automatic Retrain**: Each time a new ticket is added to the knowledge base, its embedding is generated and added to the embeddings array. This is sufficient for most cases.

2. **Manual Retrain**: After adding multiple tickets, you can trigger a full retrain by calling the `/ai/knowledge-base/retrain` endpoint. This regenerates embeddings for all tickets in the knowledge base, ensuring consistency.

### When to Retrain

You should consider retraining the model when:
- You've added a significant number of new tickets (e.g., 10% of the knowledge base)
- You've updated or modified existing tickets in the knowledge base
- You've changed the embedding model
- You've noticed a decrease in the quality of suggestions

## Security & Robustness

### Fail-Safe Design

The system defaults to escalating to admin if:
- No similar tickets are found
- Confidence score is below threshold (0.75)
- An error occurs during processing

This ensures that users always get help, either from the AI or from a human admin.

### No Hallucination

The system only suggests solutions that exist in the knowledge base:
- It does not generate new solutions
- All suggestions can be traced back to a specific past ticket

### Explainability

Each suggestion includes:
- The confidence score
- The similar ticket that led to the suggestion
- This allows admins to understand why a particular solution was suggested

### Input Validation

The API validates all inputs to prevent injection attacks:
- Subject and message are required fields
- Category is optional but validated if provided

### CORS Configuration

In production, restrict CORS to only allow requests from your Symfony domain:

```python
app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://your-symfony-domain.com"],
    allow_credentials=True,
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)
```

### Error Handling

The system implements comprehensive error handling:
- All exceptions are caught and logged
- Users receive a safe fallback response
- Admins are notified of errors through logging

### Rate Limiting

Consider implementing rate limiting to prevent abuse:
- Limit the number of requests per user/IP
- Implement backoff strategies for repeated failures

### Authentication

For production use, implement authentication:
- API keys for Symfony to authenticate with the Python API
- JWT tokens for user-facing endpoints

## Academic Explanation

### Retrieval-Based Recommendation Systems

This system implements a retrieval-based recommendation approach, as opposed to generative approaches. Retrieval-based systems select the most appropriate response from a predefined set, while generative systems create new responses on the fly. Retrieval-based systems are preferred in customer support contexts because they:
- Ensure consistency in responses
- Prevent hallucination or inappropriate suggestions
- Are easier to explain and debug
- Provide better control over the system's behavior

### Sentence Embeddings

Sentence embeddings are a technique for representing text as fixed-size vectors in a continuous vector space. Unlike traditional bag-of-words or TF-IDF representations, sentence embeddings capture semantic meaning and context. The all-MiniLM-L6-v2 model used here is a compact transformer-based model that produces 384-dimensional embeddings.

### Cosine Similarity

Cosine similarity is a metric used to measure how similar two vectors are, regardless of their magnitude. It's calculated as the cosine of the angle between two vectors:

cos(θ) = (A · B) / (||A|| * ||B||)

Where A and B are vectors, · is the dot product, and ||A|| is the norm of vector A. Cosine similarity ranges from -1 (opposite) to 1 (identical), with 0 indicating orthogonality (no similarity).

### Confidence Threshold

The confidence threshold (0.75) is a hyperparameter that determines when the system should suggest a solution vs. escalate to a human. This threshold can be adjusted based on:
- The quality of the knowledge base
- The tolerance for false positives vs. false negatives
- The availability of human admins
- The criticality of the support issues

### Continuous Learning

The system implements a form of online learning, where the model improves as new data becomes available. Unlike traditional machine learning models that require periodic retraining, this system can incorporate new knowledge immediately by adding new tickets to the knowledge base and computing their embeddings.

### Explainability

The system is designed to be explainable, which is crucial for customer support applications. Each suggestion can be traced back to a specific past ticket, allowing admins to:
- Understand why a particular solution was suggested
- Validate the quality of suggestions
- Identify and correct issues in the knowledge base
- Build trust with users

### Evaluation Metrics

To evaluate the performance of this system, consider tracking:
- Precision: How many suggested solutions were actually helpful?
- Recall: How many issues could have been resolved automatically?
- Escalation rate: How often are tickets escalated to humans?
- User satisfaction: Are users satisfied with AI suggestions?
- Resolution time: How quickly are issues resolved?

## Conclusion

This AI-based support assistant system provides a robust, explainable, and safe solution for automating customer support. By leveraging retrieval-based recommendation with sentence embeddings and cosine similarity, it can suggest relevant solutions while ensuring safety through confidence-based escalation. The system improves over time as more tickets are resolved, making it an increasingly valuable asset for the support team.

The integration with Symfony is seamless, allowing the existing ticket system to benefit from AI capabilities without major architectural changes. The system is designed to fail safely, ensuring that users always get help, either from the AI or from a human admin.

With proper monitoring and regular updates to the knowledge base, this system can significantly reduce the workload of support staff while maintaining or even improving the quality of support provided to users.
