import mysql.connector

db_config = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'osas',
    'raise_on_warnings': True
}

try:
    cnx = mysql.connector.connect(**db_config)
    cursor = cnx.cursor()

    cursor.execute("SELECT email FROM students LIMIT 5")
    print("Sample student emails:")
    for (email,) in cursor:
        print(f" - {email}")

    cursor.close()
    cnx.close()

except mysql.connector.Error as err:
    print(f"Database Error: {err}")
