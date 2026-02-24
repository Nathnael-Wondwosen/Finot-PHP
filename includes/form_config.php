<?php
// includes/form_config.php
require_once __DIR__ . '/../config.php';

if (!function_exists('form_config_init')) {
    function form_config_init(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS form_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(32) NOT NULL,
            field_key VARCHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL,
            placeholder VARCHAR(255) DEFAULT NULL,
            required TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_type_field (type, field_key),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('get_default_fields_schema')) {
    function get_default_fields_schema(string $type): array {
        // Core shared fields
        $base = [
            ['field_key'=>'student_photo','label'=>'የተማሪ ፎቶ','placeholder'=>'','required'=>1],
            ['field_key'=>'full_name','label'=>'ሙሉ ስም እስከ አያት','placeholder'=>'','required'=>1],
            ['field_key'=>'christian_name','label'=>'የክርስትና ስም','placeholder'=>'','required'=>1],
            ['field_key'=>'gender','label'=>'ጾታ','placeholder'=>'','required'=>1],
            ['field_key'=>'birth_date_et','label'=>'የትውልድ ቀን (ዓ.ም)','placeholder'=>'','required'=>1],
            ['field_key'=>'phone_number','label'=>'ስልክ ቁጥር','placeholder'=>'09XXXXXXXX','required'=>1],
            // Address
            ['field_key'=>'sub_city','label'=>'ክ/ከተማ','placeholder'=>'','required'=>0],
            ['field_key'=>'district','label'=>'ወረዳ','placeholder'=>'','required'=>0],
            ['field_key'=>'specific_area','label'=>'ሰፈር / ልዩ ስም','placeholder'=>'','required'=>0],
            ['field_key'=>'house_number','label'=>'የቤት ቁጥር','placeholder'=>'','required'=>0],
            // Emergency
            ['field_key'=>'emergency_name','label'=>'የአደጋ ጊዜ ስም','placeholder'=>'','required'=>0],
            ['field_key'=>'emergency_phone','label'=>'የአደጋ ጊዜ ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
            ['field_key'=>'emergency_alt_phone','label'=>'ተዋጭ ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
            ['field_key'=>'emergency_address','label'=>'የአደጋ ጊዜ አድራሻ','placeholder'=>'','required'=>0],
        ];

        if ($type === 'instrument') {
            $fields = array_merge([
                ['field_key'=>'instrument','label'=>'የዜማ መሳሪያ','placeholder'=>'','required'=>1],
            ], $base, [
                // Spiritual father
                ['field_key'=>'has_spiritual_father','label'=>'የንስሐ አባት አለ/አልበለጠ','placeholder'=>'own/family/none','required'=>1],
                ['field_key'=>'spiritual_father_name','label'=>'የካህኑ ስም','placeholder'=>'','required'=>0],
                ['field_key'=>'spiritual_father_phone','label'=>'የካህኑ ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
                ['field_key'=>'spiritual_father_church','label'=>'ካህኑ የሚያገለግሉበት ደብር','placeholder'=>'','required'=>0],
            ]);
            return $fields;
        }

        if ($type === 'youth') {
            $fields = array_merge($base, [
                // Spiritual father (youth form also uses this)
                ['field_key'=>'has_spiritual_father','label'=>'የንስሐ አባት አለ/አልበለጠ','placeholder'=>'own/family/none','required'=>1],
                ['field_key'=>'spiritual_father_name','label'=>'የካህኑ ስም','placeholder'=>'','required'=>0],
                ['field_key'=>'spiritual_father_phone','label'=>'የካህኑ ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
                ['field_key'=>'spiritual_father_church','label'=>'ካህኑ የሚያገለግሉበት ደብር','placeholder'=>'','required'=>0],
                // Education and additional info
                ['field_key'=>'current_grade','label'=>'የአሁኑ ክፍል','placeholder'=>'','required'=>0],
                ['field_key'=>'school_year_start','label'=>'የት/ቤት አጀመረበት ዓመት','placeholder'=>'','required'=>0],
                ['field_key'=>'education_level','label'=>'የትምህርት ደረጃ','placeholder'=>'','required'=>0],
                ['field_key'=>'field_of_study','label'=>'የስራ/ጥናት መስክ','placeholder'=>'','required'=>0],
                ['field_key'=>'special_interests','label'=>'ልዩ ፍላጎት','placeholder'=>'','required'=>0],
                ['field_key'=>'physical_disability','label'=>'የአካል ጉዳት','placeholder'=>'','required'=>0],
                ['field_key'=>'transferred_from_other_school','label'=>'ከሌላ ት/ቤት መተላለፍ','placeholder'=>'','required'=>0],
                ['field_key'=>'came_from_other_religion','label'=>'ከሌላ እምነት መጥቶ መመዝገብ','placeholder'=>'','required'=>0],
            ]);
            return $fields;
        }

        // children: align with registration.php exact field names across all sections/cards
        $children = array_merge($base, [
            // Birth date selects use separate fields in registration.php
            ['field_key'=>'birth_year_et','label'=>'የትውልድ ዓመት (ዓ.ም)','placeholder'=>'','required'=>1],
            ['field_key'=>'birth_month_et','label'=>'የትውልድ ወር (ዓ.ም)','placeholder'=>'','required'=>1],
            ['field_key'=>'birth_day_et','label'=>'የትውልድ ቀን (ዓ.ም)','placeholder'=>'','required'=>1],
            // Education/current
            ['field_key'=>'current_grade','label'=>'ክፍል (2018 ዓ.ም)','placeholder'=>'','required'=>1],
            ['field_key'=>'school_year_start','label'=>'በሰንበት ት/ቤት ያለዎት ቆይታ','placeholder'=>'','required'=>1],
            ['field_key'=>'regular_school_name','label'=>'ዓላማዊ የት/ቤት ስም እና ደረጃ','placeholder'=>'','required'=>1],
            ['field_key'=>'student_phone','label'=>'የተማሪው ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
            // Living situation
            ['field_key'=>'living_with','label'=>'ልጅዎ ከማን ጋር ይኖራሉ?','placeholder'=>'both_parents/father_only/mother_only/relative_or_guardian','required'=>1],
            // Both parents card
            ['field_key'=>'father_full_name_both','label'=>'የወላጅ አባት ሙሉ ስም (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_christian_name_both','label'=>'የወላጅ አባት ክርስትና ስም (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_occupation_both','label'=>'የወላጅ አባት ሙያ (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_phone_both','label'=>'የወላጅ አባት ስልክ (ሁለቱም)','placeholder'=>'09XXXXXXXX','required'=>1],
            ['field_key'=>'mother_full_name_both','label'=>'የወላጅ እናት ሙሉ ስም (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_christian_name_both','label'=>'የወላጅ እናት ክርስትና ስም (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_occupation_both','label'=>'የወላጅ እናት ሙያ (ሁለቱም)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_phone_both','label'=>'የወላጅ እናት ስልክ (ሁለቱም)','placeholder'=>'09XXXXXXXX','required'=>1],
            // Father only card
            ['field_key'=>'father_full_name_only','label'=>'የወላጅ አባት ሙሉ ስም (አባት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_christian_name_only','label'=>'የወላጅ አባት ክርስትና ስም (አባት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_occupation_only','label'=>'የወላጅ አባት ሙያ (አባት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'father_phone_only','label'=>'የወላጅ አባት ስልክ (አባት ብቻ)','placeholder'=>'09XXXXXXXX','required'=>1],
            // Mother only card
            ['field_key'=>'mother_full_name_only','label'=>'የወላጅ እናት ሙሉ ስም (እናት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_christian_name_only','label'=>'የወላጅ እናት ክርስትና ስም (እናት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_occupation_only','label'=>'የወላጅ እናት ሙያ (እናት ብቻ)','placeholder'=>'','required'=>1],
            ['field_key'=>'mother_phone_only','label'=>'የወላጅ እናት ስልክ (እናት ብቻ)','placeholder'=>'09XXXXXXXX','required'=>1],
            // Guardian card
            ['field_key'=>'guardian_father_full_name','label'=>'የዘመድ/አሳዳጊ አባት ሙሉ ስም','placeholder'=>'','required'=>1],
            ['field_key'=>'guardian_father_christian_name','label'=>'የዘመድ/አሳዳጊ አባት ክርስትና ስም','placeholder'=>'','required'=>1],
            ['field_key'=>'guardian_father_occupation','label'=>'የዘመድ/አሳዳጊ አባት ሙያ','placeholder'=>'','required'=>1],
            ['field_key'=>'guardian_father_phone','label'=>'የዘመድ/አሳዳጊ አባት ስልክ','placeholder'=>'09XXXXXXXX','required'=>1],
            ['field_key'=>'guardian_mother_full_name','label'=>'የዘመድ/አሳዳጊ እናት ሙሉ ስም','placeholder'=>'','required'=>0],
            ['field_key'=>'guardian_mother_christian_name','label'=>'የዘመድ/አሳዳጊ እናት ክርስትና ስም','placeholder'=>'','required'=>0],
            ['field_key'=>'guardian_mother_occupation','label'=>'የዘመድ/አሳዳጊ እናት ሙያ','placeholder'=>'','required'=>0],
            ['field_key'=>'guardian_mother_phone','label'=>'የዘመድ/አሳዳጊ እናት ስልክ','placeholder'=>'09XXXXXXXX','required'=>0],
            // Additional info card
            ['field_key'=>'special_interests','label'=>'ልዩ ፍላጎትና ተሰጥዖ/ሞያ','placeholder'=>'','required'=>0],
            ['field_key'=>'siblings_in_school','label'=>'በሰንበት ት/ቤት ውስጥ የሚማሩ እህት/ወንድሞች','placeholder'=>'','required'=>0],
            ['field_key'=>'physical_disability','label'=>'የአካል ጉዳት','placeholder'=>'','required'=>0],
            ['field_key'=>'weak_side','label'=>'ደካማ ጎን','placeholder'=>'','required'=>0],
            ['field_key'=>'transferred_from_other_school','label'=>'ከሌላ ሰንበት ት/ቤት የተዘዋወረ','placeholder'=>'','required'=>0],
            ['field_key'=>'came_from_other_religion','label'=>'ከሌላ እምነት የመጡ','placeholder'=>'','required'=>0],
        ]);
        return $children;
    }
}

if (!function_exists('get_form_config')) {
    function get_form_config(string $type, PDO $pdo): array {
        form_config_init($pdo);
        $stmt = $pdo->prepare('SELECT field_key,label,placeholder,required,sort_order FROM form_config WHERE type=? ORDER BY sort_order ASC, field_key ASC');
        $stmt->execute([$type]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Seed or backfill defaults so all sections/fields are present
        $defaults = get_default_fields_schema($type);
        $existingKeys = [];
        $maxOrder = -1;
        foreach ($rows as $r) { $existingKeys[$r['field_key']] = true; if (isset($r['sort_order'])) $maxOrder = max($maxOrder, (int)$r['sort_order']); }
        $ins = $pdo->prepare('INSERT IGNORE INTO form_config(type,field_key,label,placeholder,required,sort_order) VALUES(?,?,?,?,?,?)');
        $added = false;
        foreach ($defaults as $d) {
            $fk = $d['field_key'];
            if (!isset($existingKeys[$fk])) {
                $maxOrder++;
                $ins->execute([$type,$fk,$d['label'],$d['placeholder'],$d['required']?1:0,$maxOrder]);
                $added = true;
            }
        }
        if ($added || !$rows || count($rows)===0) {
            $stmt->execute([$type]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $rows;
    }
}

if (!function_exists('set_form_config')) {
    function set_form_config(string $type, array $fields, PDO $pdo): bool {
        form_config_init($pdo);
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare('INSERT INTO form_config(type,field_key,label,placeholder,required,sort_order) VALUES(?,?,?,?,?,?)
                                 ON DUPLICATE KEY UPDATE label=VALUES(label), placeholder=VALUES(placeholder), required=VALUES(required), sort_order=VALUES(sort_order)');
            $order = 0;
            foreach ($fields as $f) {
                $fk = (string)($f['field_key'] ?? ''); if ($fk==='') continue;
                $label = (string)($f['label'] ?? '');
                $ph = (string)($f['placeholder'] ?? '');
                $req = isset($f['required']) && (int)$f['required'] ? 1 : 0;
                $so = isset($f['sort_order']) ? (int)$f['sort_order'] : $order;
                $up->execute([$type,$fk,$label,$ph,$req,$so]);
                $order++;
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
