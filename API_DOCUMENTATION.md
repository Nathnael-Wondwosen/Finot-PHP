# Attendance API Documentation

This is a simple REST API for the student registration system that allows your PWA attendance application to access student data and record attendance.

## Setup Instructions

1. Upload `attendance_api.php` to your cPanel hosting
2. Run `setup_attendance_table.php` once to create the attendance table in your database
3. Delete `setup_attendance_table.php` after running it (for security)

## API Endpoints

All endpoints return JSON data and support CORS for web applications.

### 1. Get All Students (Regular + Instrument Students)
```
GET /attendance_api.php?endpoint=students
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "full_name": "John Doe",
      "christian_name": "John",
      "gender": "male",
      "current_grade": "10th",
      "photo_path": "uploads/photo.jpg",
      "phone_number": "1234567890",
      "created_at": "2023-01-01 10:00:00"
    }
  ],
  "count": 1
}
```

### 2. Get Specific Student Details
```
GET /attendance_api.php?endpoint=student&id=1
GET /attendance_api.php?endpoint=student&id=1&source_type=instrument
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "full_name": "John Doe",
    "christian_name": "John",
    "gender": "male",
    "current_grade": "10th",
    "photo_path": "uploads/photo.jpg",
    "phone_number": "1234567890",
    "father_full_name": "John Sr.",
    "father_phone": "0987654321",
    "mother_full_name": "Jane Doe",
    "mother_phone": "1122334455",
    // ... other student fields
  }
}
```

### 3. Get Attendance Records
```
GET /attendance_api.php?endpoint=attendance&date=2023-12-01
GET /attendance_api.php?endpoint=attendance&student_id=1&date=2023-12-01
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "date": "2023-12-01",
      "status": "present",
      "notes": "On time",
      "created_at": "2023-12-01 09:00:00",
      "updated_at": "2023-12-01 09:00:00"
    }
  ],
  "date": "2023-12-01"
}
```

### 4. Get Instrument Students Only
```
GET /attendance_api.php?endpoint=instrument_students
```

### 5. Get Attendance Reports
```
GET /attendance_api.php?endpoint=attendance_report
GET /attendance_api.php?endpoint=attendance_report&days=7
GET /attendance_api.php?endpoint=attendance_report&date_from=2023-12-01&date_to=2023-12-31
```

**Response:**
``json
{
  "success": true,
  "summary": {
    "total_students": 45,
    "present_count": 120,
    "absent_count": 15,
    "late_count": 8,
    "total_attendance_records": 143
  },
  "daily_trend": [
    {
      "date": "2023-12-01",
      "total_records": 45,
      "present": 40,
      "absent": 3,
      "late": 2
    }
  ],
  "top_students": [
    {
      "student_id": 1,
      "full_name": "John Doe",
      "source_type": "student",
      "present_count": 25,
      "total_attendance": 28,
      "attendance_rate": 89.29
    }
  ],
  "date_range": {
    "from": "2023-11-01",
    "to": "2023-12-01"
  }
}
```

### 6. Get Individual Student Attendance
```
GET /attendance_api.php?endpoint=student_attendance&student_id=1
GET /attendance_api.php?endpoint=student_attendance&student_id=5&source_type=instrument
GET /attendance_api.php?endpoint=student_attendance&student_id=1&days=14
```

**Response:**
```json
{
  "success": true,
  "student": {
    "full_name": "John Doe",
    "christian_name": "John",
    "gender": "male",
    "current_grade": "10th",
    "photo_path": "uploads/photo.jpg"
  },
  "summary": {
    "total_days": 28,
    "present_days": 25,
    "absent_days": 2,
    "late_days": 1,
    "attendance_rate": 89.29
  },
  "history": [
    {
      "date": "2023-12-01",
      "status": "present",
      "notes": "On time",
      "created_at": "2023-12-01 09:00:00",
      "updated_at": "2023-12-01 09:00:00"
    }
  ],
  "date_range": {
    "from": "2023-11-01",
    "to": "2023-12-01"
  }
}
```

### 7. Record Attendance
```
POST /attendance_api.php?endpoint=attendance
Content-Type: application/json

{
  "student_id": 1,
  "source_type": "student", // or "instrument"
  "date": "2023-12-01",
  "status": "present",
  "notes": "On time"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Attendance recorded successfully",
  "attendance_id": 1
}
```

## Usage in PWA Application

### Example JavaScript code for your PWA:

```
// Get all students (regular + instrument students)
async function getStudents() {
  try {
    const response = await fetch('https://yourdomain.com/attendance_api.php?endpoint=students');
    const data = await response.json();
    return data.data;
  } catch (error) {
    console.error('Error fetching students:', error);
    return [];
  }
}

// Get instrument students only
async function getInstrumentStudents() {
  try {
    const response = await fetch('https://yourdomain.com/attendance_api.php?endpoint=instrument_students');
    const data = await response.json();
    return data.data;
  } catch (error) {
    console.error('Error fetching instrument students:', error);
    return [];
  }
}

// Record attendance for any student
async function recordAttendance(studentId, sourceType = 'student', status, notes = '') {
  try {
    const response = await fetch('https://yourdomain.com/attendance_api.php?endpoint=attendance', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        student_id: studentId,
        source_type: sourceType, // 'student' or 'instrument'
        date: new Date().toISOString().split('T')[0], // Today's date
        status: status, // 'present', 'absent', or 'late'
        notes: notes
      })
    });
    
    const data = await response.json();
    return data.success;
  } catch (error) {
    console.error('Error recording attendance:', error);
    return false;
  }
}

// Get student details (works for both regular and instrument students)
async function getStudentDetails(studentId, sourceType = 'student') {
  try {
    const response = await fetch(`https://yourdomain.com/attendance_api.php?endpoint=student&id=${studentId}&source_type=${sourceType}`);
    const data = await response.json();
    return data.data;
  } catch (error) {
    console.error('Error fetching student details:', error);
    return null;
  }
}

// Get attendance report
async function getAttendanceReport(days = 30) {
  try {
    const response = await fetch(`https://yourdomain.com/attendance_api.php?endpoint=attendance_report&days=${days}`);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching attendance report:', error);
    return null;
  }
}

// Get individual student attendance
async function getStudentAttendance(studentId, sourceType = 'student', days = 30) {
  try {
    const response = await fetch(`https://yourdomain.com/attendance_api.php?endpoint=student_attendance&student_id=${studentId}&source_type=${sourceType}&days=${days}`);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching student attendance:', error);
    return null;
  }
}
```

## Security Notes

1. The API is currently open (no authentication required)
2. For production use, consider adding API key authentication
3. Always use HTTPS in production
4. Validate and sanitize all input data in your PWA before sending to the API

## Troubleshooting

1. If you get database connection errors, update the database credentials in `attendance_api.php`
2. Make sure the attendance table is created by running `setup_attendance_table.php`
3. Check that your cPanel PHP version supports PDO