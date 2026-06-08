
import mysql.connector
import json
import os
import re
import datetime

# Database Configuration
db_config = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'osas',
    'raise_on_warnings': True
}

json_file_path = r'c:\wamp64\www\OSAS_WEB\scripts\students_data.json'

def seed_database():
    try:
        print("Connecting to database...")
        cnx = mysql.connector.connect(**db_config)
        cursor = cnx.cursor()
        print("Connected.")

        # Load JSON data
        if not os.path.exists(json_file_path):
            print(f"Error: JSON file not found at {json_file_path}")
            return

        with open(json_file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)

        # Disable foreign key checks
        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        
        # Truncate tables
        print("Truncating tables...")
        cursor.execute("TRUNCATE TABLE students")
        cursor.execute("TRUNCATE TABLE sections")
        cursor.execute("TRUNCATE TABLE departments")
        
        # Clean up users table (remove students only)
        print("Cleaning up student users...")
        cursor.execute("DELETE FROM users WHERE role = 'student' OR student_id IS NOT NULL")
        
        # Enable foreign key checks
        cursor.execute("SET FOREIGN_KEY_CHECKS = 1")

        dept_map = {} # Code -> ID
        section_map = {} # Code -> ID

        # Insert Departments
        print("Inserting Departments...")
        add_dept = ("INSERT INTO departments "
                    "(department_code, department_name, status) "
                    "VALUES (%s, %s, 'active')")
        
        for code, dept in data['departments'].items():
            cursor.execute(add_dept, (dept['code'], dept['name']))
            dept_id = cursor.lastrowid
            dept_map[code] = dept_id

        # Insert Sections
        print("Inserting Sections...")
        add_section = ("INSERT INTO sections "
                       "(section_code, section_name, department_id, status) "
                       "VALUES (%s, %s, %s, 'active')")
        
        for section in data['sections']:
            dept_code = section['department_code']
            if dept_code in dept_map:
                dept_id = dept_map[dept_code]
                cursor.execute(add_section, (section['code'], section['name'], dept_id))
                section_id = cursor.lastrowid
                section_map[section['code']] = section_id
            else:
                print(f"  Warning: Department {dept_code} not found for section {section['code']}")

        # Insert Students and Users
        print("Inserting Students and Users...")
        
        # Insert into students table (removed password column)
        add_student = ("INSERT INTO students "
                       "(student_id, first_name, middle_name, last_name, "
                       "department, section_id, yearlevel, "
                       "email, status, created_at) "
                       "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 'active', NOW())")

        # Insert into users table
        add_user = ("INSERT INTO users "
                    "(username, email, password, role, full_name, student_id, is_active, created_at) "
                    "VALUES (%s, %s, %s, 'user', %s, %s, 1, NOW())")

        # Valid BCRYPT hash for 'password123' generated from PHP
        default_password_hash = '$2y$12$95ivuxDsbXnvq.o.nhD8W.c8CWA9LpLQvO/7ThprttW8qa6WPh2oy'

        count = 0
        user_count = 0
        
        for student in data['students']:
            section_code = student['section_code']
            section_id = section_map.get(section_code)
            
            # Year level from section code
            year_level = 1
            match = re.search(r'(\d+)$', section_code)
            if match:
                year_level = int(match.group(1))

            email = f"{student['first_name'].lower().replace(' ', '.').replace('..', '.')}.{student['last_name'].lower()}@colegiodenaujan.edu.ph"
            email = re.sub(r'[^a-zA-Z0-9.@]', '', email)
            
            # Ensure unique email by appending ID if needed? 
            # For now, let's just trust uniqueness or catch error.
            # Actually, `users` table enforces unique email.
            
            # Student data
            data_student = (
                student['student_id'],
                student['first_name'],
                student['middle_name'],
                student['last_name'],
                student['department_code'],
                section_id,
                year_level,
                email
            )
            
            # User data
            full_name = f"{student['first_name']} {student['last_name']}"
            data_user = (
                student['student_id'], # username = student_id
                email,
                default_password_hash,
                full_name,
                student['student_id']
            )
            
            try:
                # Insert Student
                cursor.execute(add_student, data_student)
                count += 1
                
                # Insert User
                try:
                    cursor.execute(add_user, data_user)
                    user_count += 1
                except mysql.connector.Error as err:
                    print(f"Error inserting user for {student['student_id']}: {err}")
                    
            except mysql.connector.Error as err:
                print(f"Error inserting student {student['student_id']}: {err}")
                
            if count % 50 == 0:
                print(f"  Inserted {count} students...")

        cnx.commit()
        print(f"Completed! Inserted {count} students and {user_count} users.")
        
        cursor.close()
        cnx.close()
        print("Database connection closed.")

    except mysql.connector.Error as err:
        print(f"Database Error: {err}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    seed_database()
