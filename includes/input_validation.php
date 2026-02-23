<?php
/**
 * Input Validation and Sanitization Functions
 */

function sanitizeInput($input, $type = "string") {
    switch ($type) {
        case "email":
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case "url":
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case "int":
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case "float":
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case "string":
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    // Ethiopian phone number validation
    $phone = preg_replace("/[^0-9]/", "", $phone);
    return preg_match("/^(09|\\+2519|2519)[0-9]{8}$/", $phone);
}

function validateEthiopianName($name) {
    // Allow Ethiopian characters and basic Latin
    return preg_match("/^[\p{L}\p{M}\s\'-]{2,50}$/u", $name);
}

function validateAge($birthDate) {
    try {
        $birth = new DateTime($birthDate);
        $now = new DateTime();
        $age = $now->diff($birth)->y;
        return $age >= 3 && $age <= 100;
    } catch (Exception $e) {
        return false;
    }
}

function validateFileUpload($file, $allowedTypes = ["image/jpeg", "image/png", "image/webp"], $maxSize = 5242880) {
    if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file["size"] > $maxSize) {
        return false;
    }

    if (!in_array($file["type"], $allowedTypes)) {
        return false;
    }

    // Check file extension
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($extension, $allowedExtensions)) {
        return false;
    }

    return true;
}

function validateStudentData($data) {
    $errors = [];

    // Required fields
    $required = ["full_name", "gender", "birth_date"];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace("_", " ", $field)) . " is required";
        }
    }

    // Name validation
    if (!empty($data["full_name"]) && !validateEthiopianName($data["full_name"])) {
        $errors[] = "Invalid full name format";
    }

    // Email validation
    if (!empty($data["email"]) && !validateEmail($data["email"])) {
        $errors[] = "Invalid email address";
    }

    // Phone validation
    if (!empty($data["phone_number"]) && !validatePhone($data["phone_number"])) {
        $errors[] = "Invalid phone number";
    }

    // Age validation
    if (!empty($data["birth_date"]) && !validateAge($data["birth_date"])) {
        $errors[] = "Invalid birth date or age";
    }

    // Gender validation
    if (!empty($data["gender"]) && !in_array($data["gender"], ["male", "female"])) {
        $errors[] = "Invalid gender";
    }

    return $errors;
}
?>