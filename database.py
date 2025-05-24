# database.py
import sqlite3

DB_NAME_STR = 'activation_server.db'

def init_sqlite_db():
    """Initializes the SQLite database and creates the devices table if it doesn't exist."""
    conn = None
    try:
        conn = sqlite3.connect(DB_NAME_STR)
        cursor = conn.cursor()
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS devices (
                udid TEXT PRIMARY KEY,
                apple_id TEXT,
                password_hash TEXT,
                activation_state TEXT,
                last_activation_record TEXT,
                activation_info_xml TEXT
            )
        ''')
        conn.commit()
        # Use string concatenation for the print statement to avoid f-string issues here
        print("Database '" + DB_NAME_STR + "' checked/initialized successfully.")
    except sqlite3.Error as e:
        # Use string concatenation for the print statement
        print("Database initialization error: " + str(e))
    finally:
        if conn:
            conn.close()

if __name__ == '__main__':
    init_sqlite_db()
