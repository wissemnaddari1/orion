import mysql.connector
from db_config import get_connection
from feature_engineering import OfferFeatureExtractor

def init_db(cursor):
    """Executes schema.sql to create the table."""
    import os
    schema_path = os.path.join(os.path.dirname(__file__), 'schema.sql')
    with open(schema_path, 'r') as f:
        schema = f.read()
    # Split by ; to handle multiple statements if any (though schema.sql is likely one CREATE)
    commands = schema.split(';')
    for cmd in commands:
        if cmd.strip():
            cursor.execute(cmd)
    print(f"Initialized ml_offer_training table using {schema_path}.")

def populate_training_data():
    conn = get_connection()
    if not conn:
        return
    
    cursor = conn.cursor(dictionary=True)
    
    try:
        cursor.execute("DROP TABLE IF EXISTS ml_offer_training")
        print("Dropped ml_offer_training to refresh schema.")
        
        init_db(cursor)
        
        query = """
        SELECT 
            o.id as offer_id, o.price, o.message, o.deliverables, o.estimated_time_days, o.included_revisions, 
            o.status, o.is_urgent, o.priority_level,
            sr.id as service_request_id, sr.budget_min, sr.budget_max, sr.duration, sr.category_id,
            u.id as worker_id, u.rating_avg, u.total_reviews
        FROM offer o
        JOIN service_request sr ON o.service_request_id = sr.id
        JOIN users u ON o.worker_id = u.id
        LEFT JOIN ml_offer_training ml ON o.id = ml.offer_id
        WHERE ml.offer_id IS NULL
        AND o.status IN ('ACCEPTED', 'REJECTED')
        """
        
        cursor.execute(query)
        rows = cursor.fetchall()
        
        if not rows:
            print("No new offers to process.")
            return

        print(f"Processing {len(rows)} offers...")
        
        inserts = []
        for row in rows:
            # Prepare dicts for extractor
            offer_data = {
                'price': row['price'],
                'message': row['message'],
                'deliverables': row['deliverables'],
                'estimated_time_days': row['estimated_time_days'],
                'included_revisions': row['included_revisions'],
                'is_urgent': row['is_urgent'],
                'priority_level': row['priority_level']
            }
            request_data = {
                'budget_min': row['budget_min'],
                'budget_max': row['budget_max'],
                'duration': row['duration'],
                'category_id': row['category_id']
            }
            worker_data = {
                'rating_avg': row['rating_avg'],
                'total_reviews': row['total_reviews']
            }
            
            features = OfferFeatureExtractor.compute_features(offer_data, request_data, worker_data)
            
            if features:
                is_accepted = 1 if row['status'] == 'ACCEPTED' else 0
                
                inserts.append((
                    row['offer_id'], row['service_request_id'], row['worker_id'],
                    features['price_ratio'], features['budget_position'], features['message_length'],
                    features['deliverables_length'], features['has_deliverables'], features['timeline_ratio'],
                    features['included_revisions'], features['worker_rating_avg'], features['total_reviews'],
                    features['category_id'], features['is_urgent'], features['priority_level'],
                    is_accepted, 'synthetic' # Source type
                ))
        
        if inserts:
            insert_sql = """
            INSERT INTO ml_offer_training (
                offer_id, service_request_id, worker_id,
                price_ratio, budget_position, message_length, deliverables_length, has_deliverables,
                timeline_ratio, included_revisions, worker_rating_avg, total_reviews,
                category_id, is_urgent, priority_level,
                is_accepted, source_type
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.executemany(insert_sql, inserts)
            conn.commit()
            print(f"Inserted {len(inserts)} training records.")
            
    except Exception as e:
        print(f"Error populating training data: {e}")
        conn.rollback()
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    populate_training_data()
