<?php
session_start();
require 'config.php';

function current_ethiopian_year() {
    $today = new DateTime();
    $gy = (int)$today->format('Y');
    $gm = (int)$today->format('m');
    $gd = (int)$today->format('d');
    $ey = $gy - 8;
    if ($gm > 9 || ($gm == 9 && $gd >= 11)) {
        $ey = $gy - 7;
    }
    return $ey;
}

function ethiopian_age_from_string($eth_yyyy_mm_dd) {
    if (!$eth_yyyy_mm_dd) return null;
    $parts = explode('-', $eth_yyyy_mm_dd);
    if (count($parts) !== 3) return null;
    $by = (int)$parts[0];
    if ($by <= 0) return null;
    $currentEy = current_ethiopian_year();
    return $currentEy - $by;
}

$admin_id = $_SESSION['admin_id'] ?? 1;


$sql = "SELECT * FROM students ORDER BY created_at DESC";
$all_students = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$students = array_values(array_filter($all_students, function($s){
    $age = ethiopian_age_from_string($s['birth_date'] ?? '');
    return $age !== null && $age >= 18;
}));

$page_title = '18+ Students';
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Noto Sans Ethiopic','sans-serif'] } } } };
    </script>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">18+ (ወጣቶች)</h1>
            <div class="flex gap-2">
                <a href="students_all.php" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50">All</a>
                <a href="students_18_plus.php" class="px-3 py-1.5 bg-blue-600 text-white border rounded">18+</a>
                <a href="students_under_18.php" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50">Under 18</a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input id="searchInput" type="text" class="px-3 py-2 border rounded" placeholder="Search by name or phone...">
                <select id="gradeFilter" class="px-3 py-2 border rounded">
                    <option value="">All classes</option>
                    <option value="new">New</option>
                    <option value="1st">1st</option>
                    <option value="2nd">2nd</option>
                    <option value="3rd">3rd</option>
                    <option value="4th">4th</option>
                    <option value="5th">5th</option>
                    <option value="6th">6th</option>
                    <option value="7th">7th</option>
                    <option value="8th">8th</option>
                    <option value="9th">9th</option>
                    <option value="10th">10th</option>
                    <option value="11th">11th</option>
                    <option value="12th">12th</option>
                </select>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">Total: <?= count($students) ?></span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Photo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Christian Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gender</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Birth Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Education Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Field of Study</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emergency Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emergency Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emergency Alt Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emergency Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Special Interests</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Physical Disability</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transferred From Other School</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Came From Other Religion</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="studentsBody">
                    <?php foreach ($students as $s): ?>
                    <tr data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'] ?? '', 'UTF-8')) ?>" data-phone="<?= htmlspecialchars($s['phone_number'] ?? '') ?>" data-grade="<?= htmlspecialchars($s['current_grade'] ?? '') ?>">
                        <td class="px-4 py-3">
                            <?php if (!empty($s['photo_path'])): ?>
                                <img src="<?= htmlspecialchars($s['photo_path']) ?>" alt="photo" class="h-10 w-10 rounded-full object-cover">
                            <?php else: ?>
                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-user text-gray-400"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['full_name'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['christian_name'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= (($s['gender'] ?? '') === 'male') ? 'ወንድ' : 'ሴት' ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['birth_date'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars(ucfirst($s['current_grade'] ?? '-')) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['education_level'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['field_of_study'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['phone_number'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['emergency_name'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['emergency_phone'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['emergency_alt_phone'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['emergency_address'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['special_interests'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['physical_disability'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['transferred_from_other_school'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['came_from_other_religion'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="student_view.php?id=<?= (int)$s['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-eye"></i></a>
                            <a href="student_edit.php?id=<?= (int)$s['id'] ?>" class="text-yellow-600 hover:text-yellow-800 mr-2"><i class="fas fa-edit"></i></a>
                            <a href="#" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        const searchInput = document.getElementById('searchInput');
        const gradeFilter = document.getElementById('gradeFilter');
        const tbody = document.getElementById('studentsBody');
        function applyFilters(){
            const q = (searchInput.value || '').trim().toLowerCase();
            const g = (gradeFilter.value || '').toLowerCase();
            tbody.querySelectorAll('tr').forEach(tr => {
                const n = tr.getAttribute('data-name') || '';
                const p = (tr.getAttribute('data-phone') || '').toLowerCase();
                const gr = (tr.getAttribute('data-grade') || '').toLowerCase();
                let show = true;
                if (q) show = (n.includes(q) || p.includes(q));
                if (show && g) show = (gr === g);
                tr.style.display = show ? '' : 'none';
            });
        }
        searchInput.addEventListener('input', applyFilters);
        gradeFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>

<?php
// Redirect to dashboard with 18+ tab and a flag for standalone view
header('Location: dashboard.php?tab=youth&view=standalone');
exit;
?>


