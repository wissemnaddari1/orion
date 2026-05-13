CREATE TABLE IF NOT EXISTS ml_offer_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NULL,
    service_request_id INT NULL,
    worker_id INT NULL,
    
    -- Features
    price_ratio DECIMAL(10, 4) COMMENT 'offer.price / budget_max',
    budget_position DECIMAL(10, 4) COMMENT '(price - budget_min) / (budget_max - budget_min)',
    message_length INT,
    deliverables_length INT,
    has_deliverables TINYINT(1),
    timeline_ratio DECIMAL(10, 4) COMMENT 'estimated_days / request_duration',
    included_revisions INT,
    worker_rating_avg DECIMAL(3, 2),
    total_reviews INT,
    
    -- Contextual Features
    category_id INT NULL,
    is_urgent TINYINT(1) DEFAULT 0,
    priority_level INT DEFAULT 2,
    
    -- Target
    is_accepted TINYINT(1),
    
    -- Metadata
    source_type ENUM('synthetic', 'real') DEFAULT 'synthetic',
    model_version VARCHAR(20) NULL,
    predicted_probability DECIMAL(5, 4) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_offer_id (offer_id),
    INDEX idx_source_type (source_type),
    INDEX idx_created_at (created_at)
);
