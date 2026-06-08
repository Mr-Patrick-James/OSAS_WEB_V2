<?php
/**
 * Chatbot API Endpoint
 * Handles chat messages and integrates with AI API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../app/config/db_connect.php';

// Load AI API configuration
$ai_config_file = __DIR__ . '/../app/config/ai_config.php';
if (file_exists($ai_config_file)) {
    require_once $ai_config_file;
} else {
    // Default configuration
    define('AI_API_TYPE', 'openai'); // 'openai' or 'custom'
    define('AI_API_KEY', ''); // Set your API key here
    define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
    define('AI_MODEL', 'gpt-3.5-turbo');
    define('USE_DATABASE_CONTEXT', true); // Whether to include database context in prompts
}

// Verify configuration is loaded
if (!defined('AI_API_KEY')) {
    define('AI_API_KEY', '');
}
if (!defined('AI_API_TYPE')) {
    define('AI_API_TYPE', 'openai');
}
if (!defined('AI_API_URL')) {
    define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
}
if (!defined('AI_MODEL')) {
    define('AI_MODEL', 'gpt-3.5-turbo');
}
if (!defined('USE_DATABASE_CONTEXT')) {
    define('USE_DATABASE_CONTEXT', true);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$conversation_history = $input['history'] ?? [];

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Get user context from session if available
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Build system prompt with optional database context
$isStudentRole = ($user_role === 'user');

$system_prompt = <<<PROMPT
You are **OSAS Bot**, the intelligent virtual assistant for the **E-OSAS (Electronic Office of Student Affairs System)** — a web-based student discipline and records management platform used by the Office of Student Affairs.

═══════════════════════════════════════════
IDENTITY & PERSONALITY
═══════════════════════════════════════════
- Name: OSAS Bot
- Role: AI assistant embedded in the E-OSAS web application
- Tone: Friendly, professional, helpful, and concise
- Language: Respond in the same language the user uses (English or Filipino/Tagalog). If the user mixes languages (Taglish), match that style.
- System Owner/Administrator/Head: Cedrick H. Almarez

═══════════════════════════════════════════
VIOLATION LEVELS & SANCTIONS KNOWLEDGE
═══════════════════════════════════════════
- **1st Offense:** Verbal reminder — please comply with dress code
- **2nd Offense:** Written reminder — dress code must be followed
- **3rd Offense:** First formal warning — counseling referral possible
- **4th Offense:** Second formal warning — parent conference required
- **5th Offense:** Final warning — automatically triggers Disciplinary Action
- **Disciplinary Action:** Referral to discipline office; suspension or serious sanctions apply
- Due process: Notice → Hearing → Decision → Appeal (if applicable)
- All violations are tracked per semester; records may be archived at semester end

═══════════════════════════════════════════
RESPONSE RULES
═══════════════════════════════════════════
1. **DATA ACCURACY:** ONLY use ACTUAL DATA from the context provided. NEVER invent student names, IDs, case numbers, or statistics.
2. **Formatting:** Use bullet points, numbered lists, and bold text for clarity.
3. **Length:** Be concise. Simple questions get 1-3 sentences. How-to guides use step-by-step format.
4. **Scope:** Only answer questions related to E-OSAS, student affairs, and school discipline.
5. **Conversational Style:** Be casual and approachable. Match the user's language (English, Filipino, or Taglish).
6. **Troubleshooting:** For problems, suggest: clear cache, check connection, verify login, contact admin.

PROMPT;

if ($isStudentRole) {
    $system_prompt .= <<<STUDENT_PROMPT

═══════════════════════════════════════════
CURRENT USER: STUDENT
═══════════════════════════════════════════
You are talking to a STUDENT. The student portal has ONLY these 3 pages:

1. **My Dashboard** — Shows compliance overview: total violations, permitted count, warning count, recent violations list, and "Tips to Stay Compliant".
2. **My Violations** — The student's own violation records only. Can filter by time period, type, and status. Has list/table/grid views. Has a "Download Report" button for their own records.
3. **Announcements** — Read-only list of school announcements. Can filter by category and status.

WHAT STUDENTS CAN DO:
- View and filter their own violations
- Download their own violation report
- Read school announcements
- Ask about violation policies, levels, and what their status means
- Ask about the entrance slip process and how to appeal

WHAT STUDENTS CANNOT DO — NEVER describe these as available to students:
- There is NO Departments page, NO Students module, NO Reports module, NO Settings page
- Students CANNOT record, create, edit, or delete violations
- Students CANNOT see other students' records, names, IDs, or counts

HOW-TO FOR STUDENTS:
- Check violations: Click "My Violations" in the top navigation
- Filter violations: Use the time period, type, and status dropdowns
- Download report: Click "Download Report" button on the My Violations page
- Read announcements: Click "Announcements" in the top navigation
- Entrance slip: Show it to your instructor to return to class after a violation
- Appeal: Contact the OSAS office directly

PRIVACY RULES (STRICT):
- If asked about other students' data, total students, department counts, or system-wide stats, say: "That information is only available to authorized OSAS administrators and staff. I can only help you with your own records."
- NEVER reveal other students' violation records, names, IDs, or any personal data.

STUDENT_PROMPT;
} else {
    $system_prompt .= <<<STAFF_PROMPT

═══════════════════════════════════════════
CURRENT USER: ADMIN / STAFF ({$user_role})
═══════════════════════════════════════════
You are talking to an authorized OSAS staff member or administrator with FULL system access.

ADMIN PORTAL PAGES:
1. **Dashboard** — System overview: total students, active violations, departments, recent activity
2. **Students** — Add, import (Excel), edit, search, view student profiles with photos
3. **Violations** — Record violations, assign types/levels, track status, generate entrance slips, archive records
4. **Departments** — Create and manage academic departments with codes
5. **Sections** — Create sections linked to departments
6. **Announcements** — Create, edit, publish announcements with audience targeting (all/students/staff)
7. **Reports** — Generate PDF/Excel reports filtered by date, department, violation type
8. **Settings** — System config, user management, backup/restore

HOW-TO GUIDES:
- Record a violation: Violations → Add Violation → Select student → Choose type/level → Save
- Import students: Students → Import → Download template → Fill data → Upload → Confirm
- Generate report: Reports → Select type → Set filters → Generate → Download PDF/Excel
- Create announcement: Announcements → New → Enter title/message → Select audience → Publish
- Add department: Departments → Add → Enter name and code → Save
- Backup system: Settings → Backup → Download database backup

Use ALL data from the context below freely. Cite specific numbers and records when available.

STAFF_PROMPT;
}


// Optionally add database context
if (USE_DATABASE_CONTEXT && $conn && !$conn->connect_error) {
    $context = getDatabaseContext($conn, $user_id, $user_role);
    if ($context) {
        $system_prompt .= "\n\nCurrent system context:\n" . $context;
    }
}

// Prepare messages for AI API
$messages = [
    ['role' => 'system', 'content' => $system_prompt]
];

// Add conversation history
foreach ($conversation_history as $msg) {
    $messages[] = [
        'role' => $msg['role'] ?? 'user',
        'content' => $msg['content'] ?? ''
    ];
}

// Add current message
$messages[] = ['role' => 'user', 'content' => $message];

// Call AI API
try {
    $response = callAIAPI($messages);
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'response' => $response['message'],
            'usage' => $response['usage'] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $response['error'] ?? 'Failed to get AI response'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Chatbot API Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Get database context for the chatbot
 */
function getDatabaseContext($conn, $user_id, $user_role) {
    $context = [];
    $isStudent = ($user_role === 'user');
    try {
        // ── General stats (safe for all roles) ──
        $stats = [];

        // Students only see department/section counts, not total student headcount
        if (!$isStudent) {
            $result = @$conn->query("SELECT COUNT(*) as count FROM students");
            if ($result) { $stats['total_students'] = $result->fetch_assoc()['count'] ?? 0; }
        }

        $result = @$conn->query("SELECT COUNT(*) as count FROM departments");
        if ($result) { $stats['total_departments'] = $result->fetch_assoc()['count'] ?? 0; }

        // Students only see total counts, not per-student breakdowns
        if (!$isStudent) {
            $result = @$conn->query("SELECT COUNT(*) as count FROM violations WHERE is_archived = 0");
            if ($result) { $stats['active_violations'] = $result->fetch_assoc()['count'] ?? 0; }

            $result = @$conn->query("SELECT COUNT(*) as count FROM violations");
            if ($result) { $stats['total_violations_all_time'] = $result->fetch_assoc()['count'] ?? 0; }
        }

        $context[] = "SYSTEM STATISTICS: " . json_encode($stats);

        // ── Departments (safe for all, but students only see names not full list) ──
        $result = @$conn->query("SELECT department_code, department_name FROM departments ORDER BY department_name");
        if ($result && $result->num_rows > 0) {
            $depts = [];
            while ($row = $result->fetch_assoc()) {
                $depts[] = $row['department_name'] . " (" . $row['department_code'] . ")";
            }
            if (!$isStudent) {
                $context[] = "DEPARTMENTS: " . implode(', ', $depts);
            }
            // Students only know their own department exists, not the full list
        }

        // ── Sections — admin/staff only ──
        if (!$isStudent) {
            $result = @$conn->query("SELECT s.section_name, s.section_code, d.department_code FROM sections s LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.section_name LIMIT 30");
            if ($result && $result->num_rows > 0) {
                $sections = [];
                while ($row = $result->fetch_assoc()) {
                    $sections[] = $row['section_name'] . " (" . ($row['department_code'] ?? '') . ")";
                }
                $context[] = "SECTIONS: " . implode(', ', $sections);
            }
        }

        // ── Recent violations — ADMIN/STAFF ONLY ──
        if (!$isStudent) {
            $result = @$conn->query("
                SELECT v.id, v.case_id, v.student_id, v.violation_date, v.status,
                       CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as student_name,
                       s.department,
                       vt.name as violation_type,
                       vl.name as violation_level
                FROM violations v
                LEFT JOIN students s ON v.student_id = s.student_id
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                LEFT JOIN violation_levels vl ON v.violation_level_id = vl.id
                WHERE v.is_archived = 0
                ORDER BY v.created_at DESC
                LIMIT 20
            ");
            if ($result && $result->num_rows > 0) {
                $violations = [];
                while ($row = $result->fetch_assoc()) {
                    $violations[] = "Case " . ($row['case_id'] ?? $row['id']) . ": " . trim($row['student_name']) .
                        " (ID: " . $row['student_id'] . ", Dept: " . ($row['department'] ?? 'N/A') .
                        ") - Type: " . ($row['violation_type'] ?? 'Unknown') .
                        ", Level: " . ($row['violation_level'] ?? 'Unknown') .
                        ", Status: " . ($row['status'] ?? 'pending') .
                        ", Date: " . ($row['violation_date'] ?? 'N/A');
                }
                $context[] = "RECENT VIOLATIONS (actual records):\n" . implode("\n", $violations);
            }

            // Violation type counts — admin only
            $result = @$conn->query("
                SELECT vt.name as type_name, COUNT(*) as count
                FROM violations v
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                WHERE v.is_archived = 0
                GROUP BY vt.name
                ORDER BY count DESC
            ");
            if ($result && $result->num_rows > 0) {
                $typeCounts = [];
                while ($row = $result->fetch_assoc()) {
                    $typeCounts[] = ($row['type_name'] ?? 'Unknown') . ": " . $row['count'];
                }
                $context[] = "VIOLATION COUNTS BY TYPE (this month): " . implode(', ', $typeCounts);
            }
        }

        // ── Announcements (safe for all) ──
        $result = @$conn->query("SELECT title, message, type, created_at FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            $announcements = [];
            while ($row = $result->fetch_assoc()) {
                $announcements[] = "\"" . $row['title'] . "\" (Type: " . ($row['type'] ?? 'general') . ", Date: " . $row['created_at'] . ")";
            }
            $context[] = "ACTIVE ANNOUNCEMENTS: " . implode('; ', $announcements);
        }

        // ── Extended admin/staff-only context ──
        if (!$isStudent) {

            // Students per department
            $result = @$conn->query("
                SELECT d.department_name, d.department_code, COUNT(s.id) as student_count
                FROM departments d
                LEFT JOIN students s ON s.department = d.department_code
                GROUP BY d.id, d.department_name, d.department_code
                ORDER BY student_count DESC
            ");
            if ($result && $result->num_rows > 0) {
                $deptCounts = [];
                while ($row = $result->fetch_assoc()) {
                    $deptCounts[] = $row['department_name'] . " (" . $row['department_code'] . "): " . $row['student_count'] . " students";
                }
                $context[] = "STUDENTS PER DEPARTMENT:\n" . implode("\n", $deptCounts);
            }

            // Violation status breakdown
            $result = @$conn->query("
                SELECT status, COUNT(*) as count
                FROM violations
                WHERE is_archived = 0
                GROUP BY status
                ORDER BY count DESC
            ");
            if ($result && $result->num_rows > 0) {
                $statusBreakdown = [];
                while ($row = $result->fetch_assoc()) {
                    $statusBreakdown[] = ucfirst($row['status']) . ": " . $row['count'];
                }
                $context[] = "VIOLATIONS BY STATUS (active): " . implode(', ', $statusBreakdown);
            }

            // Violations per department
            $result = @$conn->query("
                SELECT s.department, COUNT(v.id) as count
                FROM violations v
                LEFT JOIN students s ON v.student_id = s.student_id
                WHERE v.is_archived = 0
                GROUP BY s.department
                ORDER BY count DESC
            ");
            if ($result && $result->num_rows > 0) {
                $deptViolations = [];
                while ($row = $result->fetch_assoc()) {
                    $deptViolations[] = ($row['department'] ?? 'Unknown') . ": " . $row['count'];
                }
                $context[] = "VIOLATIONS PER DEPARTMENT (active): " . implode(', ', $deptViolations);
            }

            // Top 5 students with most violations
            $result = @$conn->query("
                SELECT v.student_id,
                       CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as student_name,
                       s.department, COUNT(v.id) as violation_count
                FROM violations v
                LEFT JOIN students s ON v.student_id = s.student_id
                GROUP BY v.student_id, student_name, s.department
                ORDER BY violation_count DESC
                LIMIT 5
            ");
            if ($result && $result->num_rows > 0) {
                $topOffenders = [];
                while ($row = $result->fetch_assoc()) {
                    $topOffenders[] = trim($row['student_name']) . " (ID: " . $row['student_id'] . ", Dept: " . ($row['department'] ?? 'N/A') . ") — " . $row['violation_count'] . " violation(s)";
                }
                $context[] = "TOP STUDENTS BY VIOLATION COUNT:\n" . implode("\n", $topOffenders);
            }

            // Violations this month vs last month
            $result = @$conn->query("
                SELECT
                    SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as this_month,
                    SUM(CASE WHEN MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as last_month
                FROM violations
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $context[] = "VIOLATIONS THIS MONTH: " . ($row['this_month'] ?? 0) . " | LAST MONTH: " . ($row['last_month'] ?? 0);
            }

            // Recent students added
            $result = @$conn->query("
                SELECT student_id, CONCAT(first_name, ' ', last_name) as name, department, section, created_at
                FROM students
                ORDER BY created_at DESC
                LIMIT 5
            ");
            if ($result && $result->num_rows > 0) {
                $recentStudents = [];
                while ($row = $result->fetch_assoc()) {
                    $recentStudents[] = trim($row['name']) . " (ID: " . $row['student_id'] . ", " . ($row['department'] ?? '') . " - " . ($row['section'] ?? '') . ")";
                }
                $context[] = "RECENTLY ADDED STUDENTS:\n" . implode("\n", $recentStudents);
            }

            // Logged-in staff info
            if ($user_id) {
                $stmt = $conn->prepare("SELECT full_name, role, email FROM users WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $row = $res->fetch_assoc()) {
                        $context[] = "LOGGED-IN STAFF: " . ($row['full_name'] ?? 'Unknown') . " | Role: " . ($row['role'] ?? $user_role) . " | Email: " . ($row['email'] ?? 'N/A');
                    }
                    $stmt->close();
                }
            }
        }

        // ── User-specific context ──
        if ($user_id && $user_role) {
            $context[] = "Current user role: " . $user_role;

            if ($isStudent) {
                $studentId = $_SESSION['student_id_code'] ?? '';
                if ($studentId) {
                    // Only fetch THIS student's own violations
                    $stmt = $conn->prepare("
                        SELECT v.case_id, v.violation_date, v.status,
                               vt.name as violation_type, vl.name as violation_level
                        FROM violations v
                        LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                        LEFT JOIN violation_levels vl ON v.violation_level_id = vl.id
                        WHERE v.student_id = ?
                        ORDER BY v.created_at DESC
                        LIMIT 10
                    ");
                    if ($stmt) {
                        $stmt->bind_param("s", $studentId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result && $result->num_rows > 0) {
                            $myViolations = [];
                            while ($row = $result->fetch_assoc()) {
                                $myViolations[] = "Case " . ($row['case_id'] ?? '') . ": " .
                                    ($row['violation_type'] ?? 'Unknown') .
                                    " (" . ($row['violation_level'] ?? '') . ")" .
                                    " - Status: " . ($row['status'] ?? 'pending') .
                                    ", Date: " . ($row['violation_date'] ?? 'N/A');
                            }
                            $context[] = "YOUR OWN VIOLATIONS (Student ID: $studentId):\n" . implode("\n", $myViolations);
                        } else {
                            $context[] = "You (Student ID: $studentId) have no violations recorded.";
                        }
                        $stmt->close();
                    }
                }
                // Explicitly tell the AI NOT to reveal other students' data
                $context[] = "PRIVACY NOTICE: The above is the ONLY student data available. Do NOT reveal any other student's records.";
            }
        }

    } catch (Exception $e) {
        error_log("Error getting database context: " . $e->getMessage());
    }

    return implode("\n", $context);
}

/**
 * Call AI API (OpenAI, Groq, Hugging Face, Cohere, Gemini, or custom)
 */
function callAIAPI($messages) {
    // Check if constants are defined
    if (!defined('AI_API_KEY') || empty(AI_API_KEY) || AI_API_KEY === '') {
        return [
            'success' => false,
            'error' => 'AI API key not configured. Please set AI_API_KEY in app/config/ai_config.php'
        ];
    }
    
    // Check if API type is defined
    $api_type = defined('AI_API_TYPE') ? AI_API_TYPE : 'openai';
    
    // Route to appropriate API handler
    switch ($api_type) {
        case 'groq':
            return callGroqAPI($messages);
        case 'huggingface':
            return callHuggingFaceAPI($messages);
        case 'cohere':
            return callCohereAPI($messages);
        case 'gemini':
            return callGeminiAPI($messages);
        case 'openai':
            return callOpenAI($messages);
        default:
            return callCustomAI($messages);
    }
}

/**
 * Call OpenAI API
 */
function callOpenAI($messages) {
    // Check if CURL is available
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'CURL is not enabled on this server. Please enable the PHP CURL extension.'];
    }
    
    // Validate API key
    if (empty(AI_API_KEY) || strlen(AI_API_KEY) < 20) {
        return ['success' => false, 'error' => 'Invalid API key. Please check your API key in app/config/ai_config.php'];
    }
    
    $ch = curl_init(AI_API_URL);
    
    if (!$ch) {
        return ['success' => false, 'error' => 'Failed to initialize CURL'];
    }
    
    $data = [
        'model' => AI_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
    
    $json_data = json_encode($data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        curl_close($ch);
        return ['success' => false, 'error' => 'Failed to encode request data: ' . json_last_error_msg()];
    }
    
    // SSL Configuration
    // Check if SSL verification should be disabled (for local development)
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : true;
    
    // Auto-detect local development environment
    $is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
                strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false;
    
    // Use config setting, but allow auto-disable for local development if not explicitly set
    if (!$verify_ssl || ($is_local && !defined('VERIFY_SSL_CERTIFICATE'))) {
        $verify_ssl = false;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        // SSL verification - disabled for local development by default
        // WARNING: Always enable in production!
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging (remove in production)
    error_log("OpenAI API Response Code: " . $http_code);
    if ($curl_error) {
        error_log("CURL Error: " . $curl_error);
    }
    
    if ($curl_error) {
        return ['success' => false, 'error' => 'Connection error: ' . $curl_error];
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = 'API request failed';
        
        if (isset($error_data['error']['message'])) {
            $error_message = $error_data['error']['message'];
        } elseif (isset($error_data['error'])) {
            $error_message = is_string($error_data['error']) ? $error_data['error'] : 'Unknown API error';
        } else {
            $error_message = 'API request failed with HTTP code ' . $http_code;
            if ($response) {
                $error_message .= '. Response: ' . substr($response, 0, 200);
            }
        }
        
        return [
            'success' => false,
            'error' => $error_message
        ];
    }
    
    if (empty($response)) {
        return ['success' => false, 'error' => 'Empty response from API'];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Failed to parse API response: ' . json_last_error_msg()];
    }
    
    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'message' => trim($data['choices'][0]['message']['content']),
            'usage' => $data['usage'] ?? null
        ];
    }
    
    // Log the response for debugging
    error_log("Unexpected API response format: " . substr($response, 0, 500));
    
    return ['success' => false, 'error' => 'Invalid API response format. Check server logs for details.'];
}

/**
 * Call Groq API (FREE - Very Fast!) with automatic key rotation on rate limit
 */
function callGroqAPI($messages) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'CURL is not enabled on this server.'];
    }
    
    // Build list of all available API keys (primary + backups)
    $allKeys = [AI_API_KEY];
    if (defined('AI_API_KEYS_BACKUP')) {
        $backupKeys = @unserialize(AI_API_KEYS_BACKUP);
        if (is_array($backupKeys)) {
            foreach ($backupKeys as $key) {
                if (!empty($key) && strlen($key) > 10) {
                    $allKeys[] = $key;
                }
            }
        }
    }
    
    $lastError = '';
    
    // Try each key until one works
    foreach ($allKeys as $apiKey) {
        $result = callGroqWithKey($messages, $apiKey);
        
        if ($result['success']) {
            return $result;
        }
        
        $lastError = $result['error'] ?? 'Unknown error';
        
        // Only retry with next key if it's a rate limit error (429)
        $isRateLimit = (isset($result['http_code']) && $result['http_code'] === 429) 
                    || stripos($lastError, 'rate limit') !== false;
        
        if (!$isRateLimit) {
            return $result;
        }
        
        error_log("Groq rate limited on key ending ..." . substr($apiKey, -6) . ", trying next key...");
    }
    
    return ['success' => false, 'error' => 'All API keys rate limited. Please wait a moment and try again.'];
}

/**
 * Make a single Groq API call with a specific key
 */
function callGroqWithKey($messages, $apiKey) {
    $ch = curl_init(AI_API_URL);
    if (!$ch) {
        return ['success' => false, 'error' => 'Failed to initialize CURL', 'http_code' => 0];
    }
    
    $data = [
        'model' => AI_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 450
    ];
    
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : false;
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['success' => false, 'error' => 'Connection error: ' . $curl_error, 'http_code' => 0];
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed with code ' . $http_code;
        return ['success' => false, 'error' => $error_message, 'http_code' => $http_code];
    }
    
    $data = json_decode($response, true);
    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'message' => trim($data['choices'][0]['message']['content'])
        ];
    }
    
    return ['success' => false, 'error' => 'Invalid API response format', 'http_code' => $http_code];
}

/**
 * Call Hugging Face API (FREE)
 */
function callHuggingFaceAPI($messages) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'CURL is not enabled on this server.'];
    }
    
    // Convert messages to prompt format
    $prompt = '';
    foreach ($messages as $msg) {
        if ($msg['role'] === 'system') {
            $prompt .= $msg['content'] . "\n\n";
        } elseif ($msg['role'] === 'user') {
            $prompt .= "User: " . $msg['content'] . "\n";
        } elseif ($msg['role'] === 'assistant') {
            $prompt .= "Assistant: " . $msg['content'] . "\n";
        }
    }
    $prompt .= "Assistant: ";
    
    $ch = curl_init(AI_API_URL);
    if (!$ch) {
        return ['success' => false, 'error' => 'Failed to initialize CURL'];
    }
    
    $data = ['inputs' => $prompt];
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : false;
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data[0]['generated_text'])) {
            $text = $data[0]['generated_text'];
            // Extract assistant response
            $parts = explode('Assistant: ', $text);
            $assistant_response = end($parts);
            return [
                'success' => true,
                'message' => trim($assistant_response)
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Hugging Face API call failed'];
}

/**
 * Call Cohere API (FREE Tier)
 */
function callCohereAPI($messages) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'CURL is not enabled on this server.'];
    }
    
    $ch = curl_init(AI_API_URL);
    if (!$ch) {
        return ['success' => false, 'error' => 'Failed to initialize CURL'];
    }
    
    // Convert messages format for Cohere
    $chat_history = [];
    $message = '';
    foreach ($messages as $msg) {
        if ($msg['role'] === 'user') {
            if (!empty($message)) {
                $chat_history[] = ['role' => 'assistant', 'message' => $message];
                $message = '';
            }
            $message = $msg['content'];
        } elseif ($msg['role'] === 'assistant') {
            $message = $msg['content'];
        }
    }
    
    $data = [
        'model' => AI_MODEL,
        'message' => $message,
        'chat_history' => $chat_history
    ];
    
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : false;
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['text'])) {
            return [
                'success' => true,
                'message' => trim($data['text'])
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Cohere API call failed'];
}

/**
 * Call Google Gemini API (FREE Tier)
 */
function callGeminiAPI($messages) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'CURL is not enabled on this server.'];
    }
    
    $url = AI_API_URL . '?key=' . AI_API_KEY;
    $ch = curl_init($url);
    if (!$ch) {
        return ['success' => false, 'error' => 'Failed to initialize CURL'];
    }
    
    // Convert messages format for Gemini
    $parts = [];
    foreach ($messages as $msg) {
        if ($msg['role'] !== 'system') {
            $parts[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }
    }
    
    $data = ['contents' => $parts];
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : false;
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'success' => true,
                'message' => trim($data['candidates'][0]['content']['parts'][0]['text'])
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Gemini API call failed'];
}

/**
 * Call Custom AI API (for other providers)
 */
function callCustomAI($messages) {
    // Implement custom AI API call here
    // This is a placeholder for other AI providers
    
    $ch = curl_init(AI_API_URL);
    
    $data = [
        'messages' => $messages
    ];
    
    $verify_ssl = defined('VERIFY_SSL_CERTIFICATE') ? VERIFY_SSL_CERTIFICATE : false;
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verify_ssl,
        CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'message' => $data['response'] ?? $data['message'] ?? 'Response received'
        ];
    }
    
    return ['success' => false, 'error' => 'Custom AI API call failed'];
}

