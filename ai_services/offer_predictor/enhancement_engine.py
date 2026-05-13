class OfferEnhancementEngine:
    """
    Converts ML reasoning and feature analysis into actionable 
    improvement suggestions for workers.
    """
    
    @staticmethod
    def generate_suggestions(features, reasoning_output):
        """
        Maps reasoning signals and raw features to constructive advice.
        """
        suggestions = []
        
        # 1. Map from Reasoning Engine Output
        for reason in reasoning_output:
            text = reason.get('text', '')
            r_type = reason.get('type', '')
            
            if r_type in ['warning', 'info']:
                # Price related
                if "over budget" in text.lower() or "Premium price" in text:
                    suggestions.append("Consider adjusting your price closer to the client's budget range to increase competitiveness.")
                
                # Timeline related
                if "Extended delivery" in text:
                    suggestions.append("The proposed timeline is longer than requested. Try to optimize your workflow to match the deadline.")
                
                # Message/Proposal related
                if "Lack of detail" in text.lower() or "Brief proposal" in text:
                    suggestions.append("Add more detail about your approach, methodology, and why you are the best fit for this project.")
                
                # Worker track record (though worker can't change this instantly, we give general advice)
                if "Below average rating" in text or "New worker" in text:
                    suggestions.append("As you have fewer reviews, consider adding extra detail or a sample of previous work to build trust.")

        # 2. Direct Feature-Based Suggestions (Rules not explicitly in reasoning but important)
        if features.get('has_deliverables') == 0:
            suggestions.append("Include a clear, bulleted list of deliverables to help the client understand exactly what they are paying for.")
            
        if features.get('included_revisions', 0) < 2:
            suggestions.append("Offering at least 2-3 revisions can significantly increase client confidence in your commitment to quality.")

        # Deduplicate just in case
        return list(dict.fromkeys(suggestions))

    @staticmethod
    def determine_risk_level(probability):
        """
        Translates acceptance probability into a human-readable risk level.
        """
        if probability >= 0.75:
            return "LOW"
        elif probability >= 0.40:
            return "MEDIUM"
        else:
            return "HIGH"
