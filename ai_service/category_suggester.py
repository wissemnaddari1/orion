import os
import json
from google import genai
from pydantic import BaseModel
from typing import Dict, Any

class CategorySuggestion(BaseModel):
    name: str
    description: str
    average_hourly_rate: float

class CategorySuggester:
    def __init__(self):
        self.api_key = os.getenv("GEMINI_API_KEY")
        if not self.api_key:
            print("⚠️ GEMINI_API_KEY not found in environment variables.")
            self.client = None
        else:
            self.client = genai.Client(api_key=self.api_key)
        
        self.model_id = os.getenv("GEMINI_MODEL_ID", "gemini-2.0-flash")

    def suggest(self, theme: str, existing_categories: list[str] = None) -> Dict[str, Any]:
        """
        Suggest a category name, description, and hourly rate based on a theme.
        """
        # Try to reload if client is missing
        if not self.client:
            from dotenv import load_dotenv
            load_dotenv(override=True)
            self.api_key = os.getenv("GEMINI_API_KEY")
            if self.api_key:
                self.client = genai.Client(api_key=self.api_key)

        if not self.client:
            return {
                "error": "Gemini API key is missing. Please configure GEMINI_API_KEY in ai_service/.env and restart the service if necessary.",
                "success": False
            }

        categories_context = ""
        if existing_categories:
            categories_context = f"\nExisting categories in our system: {', '.join(existing_categories)}. If the theme matches one of these, reuse its exact name."

        prompt = f"""
        Generate a professional worker category for a service marketplace based on the theme: "{theme}". {categories_context}
        
        Return a JSON object with the following fields:
        - "name": A concise name for the category (e.g., "Plomberie", "Développement Web"). If it matches an existing category provided above, use that name.
        - "description": A detailed, professional description (at least 200 characters) of the services included in this category.
        - "average_hourly_rate": A realistic average hourly rate in Euros (float).

        Rules:
        1. The response MUST be in French.
        2. If the theme is very specific (e.g., "React Developer"), suggest a broader but relevant category name (e.g., "Développement Web") if it exists, otherwise create a professional one.
        3. The description should be compelling for both workers and clients.
        """

        try:
            response = self.client.models.generate_content(
                model=self.model_id,
                contents=prompt,
                config={
                    'response_mime_type': 'application/json',
                    'response_schema': CategorySuggestion,
                }
            )
            
            result = json.loads(response.text)
            result["success"] = True
            return result
            
        except Exception as e:
            print(f"❌ Gemini suggestion failed: {e}")
            return {
                "error": str(e),
                "success": False
            }
