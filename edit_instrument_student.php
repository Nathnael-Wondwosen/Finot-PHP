<?php
session_start();
require_once 'config.php';

// Check if student ID is provided
if (!isset($_GET['id'])) {
    header('Location: students.php?view=instrument');
    exit;
}

$student_id = $_GET['id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get student data
    $stmt = $pdo->prepare("
        SELECT * FROM instrument_registrations 
        WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['error'] = 'Student not found';
        header('Location: students.php?view=instrument');
        exit;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $age = intval($_POST['age'] ?? 0);
        $education_level = trim($_POST['education_level'] ?? '');
        $instrument = $_POST['instrument'] ?? '';
        
        // Validate required fields
        if (empty($full_name) || empty($phone_number) || empty($gender) || empty($instrument)) {
            $error = 'Please fill in all required fields';
        } else {
            // Check for duplicate names (excluding current student)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM instrument_registrations 
                WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?)) 
                AND instrument = ? AND id != ?
            ");
            $stmt->execute([$full_name, $instrument, $student_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'A student with this name is already registered for this instrument';
            } else {
                // Update student record
                $stmt = $pdo->prepare("
                    UPDATE instrument_registrations 
                    SET full_name = ?, phone_number = ?, gender = ?, age = ?, 
                        education_level = ?, instrument = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$full_name, $phone_number, $gender, $age, $education_level, $instrument, $student_id])) {
                    $_SESSION['success'] = 'Student information updated successfully';
                    header('Location: students.php?view=instrument');
                    exit;
                } else {
                    $error = 'Failed to update student information';
                }
            }
        }
    }
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Instrument Student - Music School</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Edit Instrument Student</h1>
                        <p class="text-gray-600 mt-1">Update student information</p>
                    </div>
                    <a href="students.php?view=instrument" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($student['phone_number']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <!-- Gender -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo $student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <!-- Age -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Age</label>
                            <input type="number" name="age" value="<?php echo htmlspecialchars($student['age']); ?>" min="1" max="100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Education Level -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Education Level</label>
                            <input type="text" name="education_level" value="<?php echo htmlspecialchars($student['education_level']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Instrument -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Instrument <span class="text-red-500">*</span>
                            </label>
                            <select name="instrument" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">Select Instrument</option>
                                <option value="begena" <?php echo $student['instrument'] === 'begena' ? 'selected' : ''; ?>>በገና (Begena)</option>
                                <option value="masenqo" <?php echo $student['instrument'] === 'masenqo' ? 'selected' : ''; ?>>መሰንቆ (Masenqo)</option>
                                <option value="kebero" <?php echo $student['instrument'] === 'kebero' ? 'selected' : ''; ?>>ከበሮ (Kebero)</option>
                                <option value="krar" <?php echo $student['instrument'] === 'krar' ? 'selected' : ''; ?>>ክራር (Krar)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Current Photo Display -->
                    <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Photo</label>
                            <img src="<?php echo htmlspecialchars($student['photo_path']); ?>" 
                                 alt="Student Photo" class="w-24 h-24 rounded-full object-cover border">
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <a href="students.php?view=instrument" 
                           class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>