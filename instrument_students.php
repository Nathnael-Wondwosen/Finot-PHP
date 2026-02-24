<?php
session_start();
require 'config.php';

$sql = "SELECT ir.*, s.full_name AS student_full_name, s.gender, s.birth_date, s.current_grade, s.phone_number AS student_phone, s.photo_path AS student_photo
FROM instrument_registrations ir
LEFT JOIN students s ON ir.student_id = s.id
ORDER BY ir.created_at DESC";
$instrument_students = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Instrument Students';
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
            <h1 class="text-2xl font-semibold">Instrument Students</h1>
            <div class="flex gap-2">
                <a href="students_all.php" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50">All</a>
                <a href="students_18_plus.php" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50">18+</a>
                <a href="students_under_18.php" class="px-3 py-1.5 bg-white border rounded hover:bg-gray-50">Under 18</a>
                <a href="instrument_students.php" class="px-3 py-1.5 bg-blue-600 text-white border rounded">Instrument</a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input id="searchInput" type="text" class="px-3 py-2 border rounded" placeholder="Search by name, instrument or phone...">
                <select id="instrumentFilter" class="px-3 py-2 border rounded">
                    <option value="">All Instruments</option>
                    <option value="begena">በገና</option>
                    <option value="masenqo">መሰንቆ</option>
                    <option value="kebero">ከበሮ</option>
                    <option value="krar">ክራር</option>
                </select>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">Total: <?= count($instrument_students) ?></span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Photo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instrument</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gender</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Birth Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone Number</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="studentsBody">
                    <?php foreach ($instrument_students as $s): ?>
                    <tr data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'] ?? '', 'UTF-8')) ?>" data-instrument="<?= htmlspecialchars($s['instrument'] ?? '') ?>" data-phone="<?= htmlspecialchars($s['phone_number'] ?? '') ?>">
                        <td class="px-4 py-3">
                            <?php if (!empty($s['person_photo_path'])): ?>
                                <img src="<?= htmlspecialchars($s['person_photo_path']) ?>" alt="photo" class="h-10 w-10 rounded-full object-cover">
                            <?php elseif (!empty($s['student_photo'])): ?>
                                <img src="<?= htmlspecialchars($s['student_photo']) ?>" alt="photo" class="h-10 w-10 rounded-full object-cover">
                            <?php else: ?>
                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"><i class="fas fa-user text-gray-400"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['full_name'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars(ucfirst($s['instrument'] ?? '-')) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= (($s['gender'] ?? '') === 'male') ? 'ወንድ' : 'ሴት' ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['birth_year_et'] ?? '-') ?> / <?= htmlspecialchars($s['birth_month_et'] ?? '-') ?> / <?= htmlspecialchars($s['birth_day_et'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars(ucfirst($s['current_grade'] ?? '-')) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($s['phone_number'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-right text-sm">
                            <!-- You can add view/edit/delete actions here if needed -->
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        const searchInput = document.getElementById('searchInput');
        const instrumentFilter = document.getElementById('instrumentFilter');
        const tbody = document.getElementById('studentsBody');
        function applyFilters(){
            const q = (searchInput.value || '').trim().toLowerCase();
            const instr = (instrumentFilter.value || '').toLowerCase();
            tbody.querySelectorAll('tr').forEach(tr => {
                const n = tr.getAttribute('data-name') || '';
                const p = (tr.getAttribute('data-phone') || '').toLowerCase();
                const i = (tr.getAttribute('data-instrument') || '').toLowerCase();
                let show = true;
                if (q) show = (n.includes(q) || p.includes(q));
                if (show && instr) show = (i === instr);
                tr.style.display = show ? '' : 'none';
            });
        }
        searchInput.addEventListener('input', applyFilters);
        instrumentFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>
