import os
import re
import mysql.connector
from mysql.connector import Error

def parse_db_url(url):
    """
    Parses a DATABASE_URL of the form:
    mysql://user:pass@host:port/dbname?params
    or
    mysql://user:@host:port/dbname...
    """
    pattern = r"mysql://([^:]+):([^@]*)@([^:]+):(\d+)/([^\?]+)"
    match = re.match(pattern, url)
    if match:
        return {
            "user": match.group(1),
            "password": match.group(2) if match.group(2) else "",
            "host": match.group(3),
            "port": int(match.group(4)),
            "database": match.group(5)
        }
    return None

def get_db_config():
    """
    Reads the .env file from the project root and parses the DATABASE_URL.
    Returns a dictionary of database credentials.
    """
    # Go up two levels from ai_services/offer_predictor to project root
    base_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    env_path = os.path.join(base_dir, '.env')
    
    db_url = None
    
    if os.path.exists(env_path):
        with open(env_path, 'r') as f:
            for line in f:
                if line.strip().startswith("DATABASE_URL="):
                    # Remove DATABASE_URL=" and trailing "
                    # Handle quoted or unquoted
                    parts = line.strip().split('=', 1)
                    if len(parts) == 2:
                        val = parts[1].strip()
                        if val.startswith('"') and val.endswith('"'):
                            val = val[1:-1]
                        db_url = val
                        break
    
    if not db_url:
        # Fallback default
        db_url = "mysql://root:@127.0.0.1:3306/orion"
        print(f"Warning: DATABASE_URL not found in {env_path}, using default: {db_url}")

    config = parse_db_url(db_url)
    if not config:
        raise ValueError(f"Could not parse DATABASE_URL: {db_url}")
        
    return config

def get_connection():
    """Returns a mysql.connector connection object."""
    config = get_db_config()
    try:
        connection = mysql.connector.connect(**config)
        return connection
    except Error as e:
        print(f"Error connecting to MySQL: {e}")
        raise

def get_sqlalchemy_engine():
    """Returns a SQLAlchemy engine."""
    from sqlalchemy import create_engine
    config = get_db_config()
    # Construct URL: mysql+mysqlconnector://user:pass@host:port/db
    user = config['user']
    password = config['password']
    host = config['host']
    port = config['port']
    dbname = config['database']
    
    url = f"mysql+mysqlconnector://{user}:{password}@{host}:{port}/{dbname}"
    return create_engine(url)
