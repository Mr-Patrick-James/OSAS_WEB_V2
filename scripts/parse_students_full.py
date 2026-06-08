
import sys
import pandas as pd
import json
import re
import os

# Default values
file_path = r'c:\wamp64\www\OSAS_WEB\app\assets\Students\LIST-OF-ENROLLED-FOR-2ND-SEM-2025-26.xlsx'
output_path = r'c:\wamp64\www\OSAS_WEB\scripts\students_data.json'

# Accept command line arguments for input and output paths
if len(sys.argv) > 1:
    file_path = sys.argv[1]
if len(sys.argv) > 2:
    output_path = sys.argv[2]

def extract_department_code(sheet_name):
    # Remove numbers
    base = re.sub(r'\d+', '', sheet_name)
    # Split by hyphen if exists
    parts = base.split('-')
    return parts[0]

def extract_section_code(sheet_name):
    return sheet_name

def parse_excel():
    try:
        xl = pd.ExcelFile(file_path)
    except Exception as e:
        print(f"Error opening Excel file: {e}")
        return None

    all_data = {
        "departments": {},
        "sections": [],
        "students": []
    }

    print(f"Found sheets: {xl.sheet_names}")

    for sheet_name in xl.sheet_names:
        print(f"Processing sheet: {sheet_name}")
        df = pd.read_excel(xl, sheet_name=sheet_name, header=None)
        
        dept_code = extract_department_code(sheet_name)
        section_code = extract_section_code(sheet_name)
        section_name = section_code # Default fallback
        
        # Determine department name (simple mapping or fallback)
        dept_name = dept_code 
        if dept_code == "BTVTED":
            dept_name = "Bachelor of Technical-Vocational Teacher Education"
        elif dept_code == "BPA":
            dept_name = "Bachelor of Public Administration"
        elif dept_code == "BSIS":
            dept_name = "Bachelor of Science in Information Systems"
            
        if dept_code not in all_data["departments"]:
            all_data["departments"][dept_code] = {
                "code": dept_code,
                "name": dept_name
            }
            
        # Scan for section name in content
        # Look for the first non-empty cell that contains "YEAR" or the sheet name base
        found_section_name = False
        
        sheet_students = []
        
        for index, row in df.iterrows():
            r = [str(x).strip() if pd.notna(x) else "" for x in row]
            
            # Check for student row
            is_student = False
            # Column 0 is number, Column 2 is ID (Format 20XX-XXXX)
            if len(r) > 2 and r[0].isdigit() and re.match(r'\d{4}-\d{4}', r[2]):
                is_student = True
                
            if is_student:
                # Name format: "Lastname, Firstname M."
                full_name = r[1]
                parts = full_name.split(',')
                last_name = parts[0].strip()
                first_name = ""
                middle_name = ""
                
                if len(parts) > 1:
                    rest = parts[1].strip()
                    # Check for middle initial at the end (e.g. "Jerlyn M.")
                    # Or "Althea Nicole Shane M."
                    name_parts = rest.split(' ')
                    if len(name_parts) > 1 and len(name_parts[-1]) <= 2 and name_parts[-1].endswith('.'):
                        middle_name = name_parts[-1].replace('.', '')
                        first_name = " ".join(name_parts[:-1])
                    else:
                        first_name = rest
                
                student = {
                    "student_id": r[2],
                    "last_name": last_name,
                    "first_name": first_name,
                    "middle_name": middle_name,
                    "section_code": section_code,
                    "department_code": dept_code,
                    "sex": r[4] if len(r) > 4 else "",
                    "contact_number": "", # Not in file
                    "email": "", # Not in file
                    "address": "" # Not in file
                }
                sheet_students.append(student)
                continue
                
            # Try to find section name if not found yet
            if not found_section_name and not is_student:
                # Check column 1 (B) usually
                candidate = r[1] if len(r) > 1 else ""
                if candidate and ("YEAR" in candidate or dept_code in candidate) and "LIST OF" not in candidate:
                    section_name = candidate
                    found_section_name = True

        # Add section
        all_data["sections"].append({
            "code": section_code,
            "name": section_name,
            "department_code": dept_code
        })
        
        # Add students
        all_data["students"].extend(sheet_students)
        print(f"  Found {len(sheet_students)} students in {section_code}")

    return all_data

data = parse_excel()
if data:
    try:
        with open(output_path, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2)
        print(f"Successfully saved data to {output_path}")
    except Exception as e:
        print(f"Error saving output file: {e}")
        sys.exit(1)
    
    print(f"Total Departments: {len(data['departments'])}")
    print(f"Total Sections: {len(data['sections'])}")
    print(f"Total Students: {len(data['students'])}")
else:
    print("Failed to parse data.")
    sys.exit(1)
