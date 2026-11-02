from flask import Flask, jsonify, abort
import pymysql
from datetime import datetime

app = Flask(__name__)

def get_db_connection():
    return pymysql.connect(
        host='localhost',
        user='YOUR_USER_NAME',
        password='YOUR_PASSWORD',
        database='YOUR_DATABASE_NAME',
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )

@app.route('/api/user/<int:user_id>', methods=['GET'])
def get_user(user_id):
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            sql = """
                SELECT id, username, fullname, email, password_hash,
                       created_at, avatar, bio, updated_at
                FROM users
                WHERE id = %s
            """
            cursor.execute(sql, (user_id,))
            user = cursor.fetchone()
            if not user:
                abort(404, description="User not found")
                
            if user.get('created_at'):
                user['created_at'] = user['created_at'].isoformat()
            if user.get('updated_at'):
                user['updated_at'] = user['updated_at'].isoformat()
            return jsonify(user)
    finally:
        conn.close()

if __name__ == '__main__':
    app.run(debug=True, host='127.0.0.1', port=5000)
