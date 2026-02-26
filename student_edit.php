<?php
session_start();
require 'config.php';
requireAdminLogin();

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    echo '<h2>Invalid student ID.</h2>';
    exit;
}

$sql = "SELECT s.*, 
    f.full_name AS father_full_name, f.phone_number AS father_phone, f.occupation AS father_occupation,
    m.full_name AS mother_full_name, m.phone_number AS mother_phone, m.occupation AS mother_occupation,
    g.full_name AS guardian_full_name, g.phone_number AS guardian_phone, g.occupation AS guardian_occupation
FROM students s
LEFT JOIN parents f ON s.id = f.student_id AND f.parent_type = 'father'
LEFT JOIN parents m ON s.id = m.student_id AND m.parent_type = 'mother'
LEFT JOIN parents g ON s.id = g.student_id AND g.parent_type = 'guardian'
WHERE s.id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$student_id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { echo '<h2>Student not found.</h2>'; exit; }
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans">
    <div class="max-w-3xl mx-auto mt-10 bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold">Edit Student</h1>
            <div class="flex items-center gap-2">
                <button onclick="history.back()" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">Back</button>
            </div>
        </div>
        <form id="editForm" class="space-y-5" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int)$student_id ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Full Name</label>
                    <input type="text" name="full_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['full_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Christian Name</label>
                    <input type="text" name="christian_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['christian_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Gender</label>
                    <select name="gender" class="w-full px-3 py-2 border rounded">
                        <option value="male" <?= ($s['gender']??'')==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= ($s['gender']??'')==='female'?'selected':'' ?>>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">የተወለዱበት ቀን (ዓ.ም) <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <select id="edit_birth_year_et" name="birth_year_et" class="px-3 py-2 border rounded" required>
                            <option value="">ዓመት</option>
                        </select>
                        <select id="edit_birth_month_et" name="birth_month_et" class="px-3 py-2 border rounded" required>
                            <option value="">ወር</option>
                        </select>
                        <select id="edit_birth_day_et" name="birth_day_et" class="px-3 py-2 border rounded" required>
                            <option value="">ቀን</option>
                        </select>
                    </div>
                    <!-- Hidden field to maintain compatibility with existing backend -->
                    <input type="hidden" name="birth_date" id="edit_birth_date_hidden">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Class</label>
                    <select name="current_grade" class="w-full px-3 py-2 border rounded">
                        <?php foreach (['new','1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th','11th','12th'] as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>" <?= (($s['current_grade']??'')===$g)?'selected':'' ?>><?= strtoupper($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Phone Number</label>
                    <input type="text" name="phone_number" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['phone_number'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Sub City</label>
                    <input type="text" name="sub_city" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['sub_city'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">District</label>
                    <input type="text" name="district" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['district'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Specific Area</label>
                    <input type="text" name="specific_area" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['specific_area'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">House Number</label>
                    <input type="text" name="house_number" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['house_number'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Spiritual Father Name</label>
                    <input type="text" name="spiritual_father_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['spiritual_father_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Spiritual Father Phone</label>
                    <input type="text" name="spiritual_father_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['spiritual_father_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Has Spiritual Father</label>
                    <select name="has_spiritual_father" class="w-full px-3 py-2 border rounded">
                        <?php $hsf = $s['has_spiritual_father'] ?? ''; ?>
                        <option value="">Select</option>
                        <option value="own" <?= $hsf==='own'?'selected':'' ?>>Own</option>
                        <option value="family" <?= $hsf==='family'?'selected':'' ?>>Family</option>
                        <option value="none" <?= $hsf==='none'?'selected':'' ?>>None</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Spiritual Father Church</label>
                    <input type="text" name="spiritual_father_church" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['spiritual_father_church'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Living With</label>
                    <select name="living_with" class="w-full px-3 py-2 border rounded">
                        <?php $lw = $s['living_with'] ?? ''; ?>
                        <option value="">Select</option>
                        <option value="both_parents" <?= $lw==='both_parents'?'selected':'' ?>>Both Parents</option>
                        <option value="father_only" <?= $lw==='father_only'?'selected':'' ?>>Father Only</option>
                        <option value="mother_only" <?= $lw==='mother_only'?'selected':'' ?>>Mother Only</option>
                        <option value="relative_or_guardian" <?= $lw==='relative_or_guardian'?'selected':'' ?>>Relative/Guardian</option>
                    </select>
                </div>
            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Regular School Name</label>
                    <input type="text" name="regular_school_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['regular_school_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Regular School Grade</label>
                    <input type="text" name="regular_school_grade" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['regular_school_grade'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">School Year Start (YYYY)</label>
                    <input type="text" name="school_year_start" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['school_year_start'] ?? '') ?>" placeholder="2016">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Education Level</label>
                    <input type="text" name="education_level" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['education_level'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Field of Study</label>
                    <input type="text" name="field_of_study" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['field_of_study'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Siblings In School</label>
                    <input type="text" name="siblings_in_school" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['siblings_in_school'] ?? '') ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Father Full Name</label>
                    <input type="text" name="father_full_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['father_full_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Father Phone</label>
                    <input type="text" name="father_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['father_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Father Occupation</label>
                    <input type="text" name="father_occupation" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['father_occupation'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Mother Full Name</label>
                    <input type="text" name="mother_full_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['mother_full_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Mother Phone</label>
                    <input type="text" name="mother_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['mother_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Mother Occupation</label>
                    <input type="text" name="mother_occupation" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['mother_occupation'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Guardian Full Name</label>
                    <input type="text" name="guardian_full_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['guardian_full_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Guardian Phone</label>
                    <input type="text" name="guardian_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['guardian_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Guardian Occupation</label>
                    <input type="text" name="guardian_occupation" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['guardian_occupation'] ?? '') ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Emergency Name</label>
                    <input type="text" name="emergency_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['emergency_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['emergency_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Emergency Alt Phone</label>
                    <input type="text" name="emergency_alt_phone" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['emergency_alt_phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Emergency Address</label>
                    <input type="text" name="emergency_address" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($s['emergency_address'] ?? '') ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Special Interests</label>
                    <textarea name="special_interests" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($s['special_interests'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Physical Disability</label>
                    <textarea name="physical_disability" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($s['physical_disability'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Weak Side</label>
                    <textarea name="weak_side" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($s['weak_side'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Transferred From Other School</label>
                    <textarea name="transferred_from_other_school" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($s['transferred_from_other_school'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Came From Other Religion</label>
                    <textarea name="came_from_other_religion" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($s['came_from_other_religion'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Photo</label>
                    <input type="file" name="student_photo" accept="image/*" class="w-full">
                </div>
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="remove_photo"> Remove current photo</label>
                    <?php if (!empty($s['photo_path'])): ?>
                        <img src="<?= htmlspecialchars($s['photo_path']) ?>" alt="photo" class="h-10 w-10 rounded-full object-cover border">
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
                <button type="button" onclick="history.back()" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Save</button>
            </div>
        </form>
    </div>
    <script>
        const form = document.getElementById('editForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            try {
                const res = await fetch('update_student.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data && data.success) {
                    alert('Saved successfully');
                    history.back();
                } else {
                    alert(data && data.message ? data.message : 'Save failed');
                }
            } catch (err) {
                alert('Network error');
            }
        });
    </script>
    
    <script>
    // Ethiopian Calendar for Student Edit Page
    (function() {
        const ySel = document.getElementById('edit_birth_year_et');
        const mSel = document.getElementById('edit_birth_month_et');
        const dSel = document.getElementById('edit_birth_day_et');
        const hiddenField = document.getElementById('edit_birth_date_hidden');
        
        if (!ySel || !mSel || !dSel) return;
        
        const monthNames = ['መስከረም', 'ጥቅምት', 'ሕዳር', 'ታህሳስ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'];
        
        function getCurrentEthiopianYear() {
            const today = new Date();
            const gYear = today.getFullYear();
            const gMonth = today.getMonth() + 1;
            const gDay = today.getDate();
            let eYear = gYear - 8;
            if (gMonth > 9 || (gMonth === 9 && gDay >= 11)) eYear = gYear - 7;
            return eYear;
        }
        
        function isEthiopianLeapYear(eYear) {
            return eYear % 4 === 3;
        }
        
        function daysInEthiopianMonth(eYear, eMonth) {
            if (eMonth >= 1 && eMonth <= 12) return 30;
            return isEthiopianLeapYear(eYear) ? 6 : 5; // Pagume
        }
        
        function populateYears() {
            const current = getCurrentEthiopianYear();
            const min = current - 40;
            ySel.innerHTML = '<option value="">ዓመት</option>';
            for (let y = current; y >= min; y--) {
                const opt = document.createElement('option');
                opt.value = String(y);
                opt.textContent = y;
                ySel.appendChild(opt);
            }
        }
        
        function populateMonths() {
            mSel.innerHTML = '<option value="">ወር</option>';
            monthNames.forEach((name, idx) => {
                const opt = document.createElement('option');
                opt.value = String(idx + 1);
                opt.textContent = name;
                mSel.appendChild(opt);
            });
        }
        
        function populateDays() {
            const y = parseInt(ySel.value, 10);
            const m = parseInt(mSel.value, 10);
            dSel.innerHTML = '<option value="">ቀን</option>';
            if (!y || !m) return;
            const dim = daysInEthiopianMonth(y, m);
            for (let d = 1; d <= dim; d++) {
                const opt = document.createElement('option');
                opt.value = String(d);
                opt.textContent = String(d);
                dSel.appendChild(opt);
            }
            updateHiddenBirthDate();
        }
        
        function updateHiddenBirthDate() {
            const y = ySel.value;
            const m = mSel.value;
            const d = dSel.value;
            
            if (y && m && d && hiddenField) {
                const formattedDate = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                hiddenField.value = formattedDate;
            } else if (hiddenField) {
                hiddenField.value = '';
            }
        }
        
        // Set existing birth date
        function setExistingBirthDate() {
            const existingBirthDate = '<?= htmlspecialchars($s['birth_date'] ?? '') ?>';
            if (existingBirthDate) {
                const parts = existingBirthDate.split('-');
                if (parts.length === 3) {
                    ySel.value = parts[0];
                    mSel.value = parseInt(parts[1], 10).toString();
                    populateDays();
                    dSel.value = parseInt(parts[2], 10).toString();
                    updateHiddenBirthDate();
                }
            }
        }
        
        // Event listeners
        ySel.addEventListener('change', function() { populateDays(); updateHiddenBirthDate(); });
        mSel.addEventListener('change', function() { populateDays(); updateHiddenBirthDate(); });
        dSel.addEventListener('change', function() { updateHiddenBirthDate(); });
        
        // Initialize
        populateYears();
        populateMonths();
        setExistingBirthDate();
    })();
    </script>
</body>
</html>


