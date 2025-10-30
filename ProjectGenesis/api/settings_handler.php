<?php

include '../config/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'js.api.invalidAction'];

define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 72);
define('MIN_USERNAME_LENGTH', 6);
define('MAX_USERNAME_LENGTH', 32);
define('MAX_EMAIL_LENGTH', 255);
define('CODE_RESEND_COOLDOWN_SECONDS', 60); 

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'js.settings.errorNoSession';
    echo json_encode($response);
    exit;
}
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

function generateVerificationCode()
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
}

function generateDefaultAvatar($pdo, $userId, $username, $basePath)
{
    try {
        $savePathDir = dirname(__DIR__) . '/assets/uploads/avatars';
        $fileName = "user-{$userId}.png";
        $fullSavePath = $savePathDir . '/' . $fileName;
        $publicUrl = $basePath . '/assets/uploads/avatars/' . $fileName;

        if (!is_dir($savePathDir)) {
            mkdir($savePathDir, 0755, true);
        }

        $avatarColors = ['206BD3', 'D32029', '28A745', 'E91E63', 'F57C00'];
        $selectedColor = $avatarColors[array_rand($avatarColors)];
        $nameParam = urlencode($username);

        $apiUrl = "https://ui-avatars.com/api/?name={$nameParam}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";

        $imageData = @file_get_contents($apiUrl);

        if ($imageData === false) {
            return null;
        }

        file_put_contents($fullSavePath, $imageData);
        return $publicUrl;
    } catch (Exception $e) {
        return null;
    }
}

function deleteOldAvatar($oldUrl, $basePath, $userId)
{
    if (strpos($oldUrl, 'ui-avatars.com') !== false) {
        return;
    }

    if (strpos($oldUrl, 'user-' . $userId . '.png') !== false) {
        return;
    }

    $relativePath = str_replace($basePath, '', $oldUrl);
    $serverPath = dirname(__DIR__) . $relativePath;

    if (file_exists($serverPath)) {
        @unlink($serverPath);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        $response['message'] = 'js.api.errorSecurityRefresh';
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'upload-avatar') {
            try {
                if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('js.settings.errorAvatarUpload');
                }

                $file = $_FILES['avatar'];
                $fileSize = $file['size'];
                $fileTmpName = $file['tmp_name'];

                if ($fileSize > 2 * 1024 * 1024) {
                    throw new Exception('js.settings.errorAvatarSize');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($fileTmpName);

                $allowedTypes = [
                    'image/png'  => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];

                if (!array_key_exists($mimeType, $allowedTypes)) {
                    throw new Exception('js.settings.errorAvatarFormat');
                }
                $extension = $allowedTypes[$mimeType];

                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                $newFileName = "user-{$userId}-" . time() . "." . $extension;
                $saveDir = dirname(__DIR__) . '/assets/uploads/avatars/';
                $newFilePath = $saveDir . $newFileName;
                $newPublicUrl = $basePath . '/assets/uploads/avatars/' . $newFileName;

                if (!move_uploaded_file($fileTmpName, $newFilePath)) {
                    throw new Exception('js.settings.errorAvatarSave');
                }

                $stmt = $pdo->prepare("UPDATE users SET profile_image_url = ? WHERE id = ?");
                $stmt->execute([$newPublicUrl, $userId]);

                if ($oldUrl) {
                    deleteOldAvatar($oldUrl, $basePath, $userId);
                }

                $response['success'] = true;
                $response['message'] = 'js.settings.successAvatarUpdate';
                $response['newAvatarUrl'] = $newPublicUrl;
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - upload-avatar');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'remove-avatar') {
            try {
                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                $newDefaultUrl = generateDefaultAvatar($pdo, $userId, $username, $basePath);

                if (!$newDefaultUrl) {
                    throw new Exception('js.settings.errorAvatarApi');
                }

                $stmt = $pdo->prepare("UPDATE users SET profile_image_url = ? WHERE id = ?");
                $stmt->execute([$newDefaultUrl, $userId]);

                if ($oldUrl) {
                    deleteOldAvatar($oldUrl, $basePath, $userId);
                }

                $response['success'] = true;
                $response['message'] = 'js.settings.successAvatarRemoved';
                $response['newAvatarUrl'] = $newDefaultUrl;
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - remove-avatar');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'update-username') {
            try {

                define('USERNAME_CHANGE_COOLDOWN_DAYS', 30);

                $stmt_check = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'username' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check->execute([$userId]);
                $lastLog = $stmt_check->fetch();

                if ($lastLog) {
                    $lastChangeTime = new DateTime($lastLog['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);
                    $daysPassed = (int)$interval->format('%a');

                    if ($daysPassed < USERNAME_CHANGE_COOLDOWN_DAYS) {
                        $daysRemaining = USERNAME_CHANGE_COOLDOWN_DAYS - $daysPassed;
                        $response['message'] = 'js.settings.errorUsernameCooldown';
                        $response['data'] = ['days' => $daysRemaining];
                        echo json_encode($response);
                        exit;
                    }
                }

                $newUsername = trim($_POST['username'] ?? '');
                $oldUsername = $_SESSION['username'];

                if (empty($newUsername)) {
                    throw new Exception('js.settings.errorUsernameEmpty');
                }
                if (strlen($newUsername) < MIN_USERNAME_LENGTH) {
                    throw new Exception('js.auth.errorUsernameMinLength');
                }
                if (strlen($newUsername) > MAX_USERNAME_LENGTH) {
                    throw new Exception('js.auth.errorUsernameMaxLength');
                }
                if ($newUsername === $oldUsername) {
                    throw new Exception('js.settings.errorUsernameIsCurrent');
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$newUsername, $userId]);
                if ($stmt->fetch()) {
                    throw new Exception('js.auth.errorUsernameInUse');
                }

                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                $isDefaultAvatar = false;
                if ($oldUrl) {
                    $isDefaultAvatar = strpos($oldUrl, 'ui-avatars.com') !== false ||
                        strpos($oldUrl, 'user-' . $userId . '.png') !== false;
                } else {
                    $isDefaultAvatar = true;
                }

                $newAvatarUrl = null;

                if ($isDefaultAvatar) {

                    $oldInitial = mb_substr($oldUsername, 0, 1, 'UTF-8');
                    $newInitial = mb_substr($newUsername, 0, 1, 'UTF-8');

                    if (strcasecmp($oldInitial, $newInitial) !== 0) {
                        $newAvatarUrl = generateDefaultAvatar($pdo, $userId, $newUsername, $basePath);
                    }
                }


                if ($newAvatarUrl) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_image_url = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $newAvatarUrl, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $userId]);
                }

                $stmt_log = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'username', ?, ?, ?)"
                );
                $stmt_log->execute([$userId, $oldUsername, $newUsername, getIpAddress()]);

                $_SESSION['username'] = $newUsername;
                if ($newAvatarUrl) {
                    $_SESSION['profile_image_url'] = $newAvatarUrl;
                }

                $response['success'] = true;
                $response['message'] = 'js.settings.successUsernameUpdate';
                $response['newUsername'] = $newUsername;

                if ($newAvatarUrl) {
                    $response['newAvatarUrl'] = $newAvatarUrl;
                }
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-username');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorUsernameMinLength') {
                        $response['data'] = ['length' => MIN_USERNAME_LENGTH];
                    } elseif ($response['message'] === 'js.auth.errorUsernameMaxLength') {
                         $response['data'] = ['length' => MAX_USERNAME_LENGTH];
                    }
                }
            }
        } elseif ($action === 'request-email-change-code') {
            try {
                $identifier = $userId;
                $codeType = 'email_change';

                $stmt_check = $pdo->prepare(
                    "SELECT created_at FROM verification_codes 
                     WHERE identifier = ? AND code_type = ? 
                     ORDER BY created_at DESC LIMIT 1"
                );
                $stmt_check->execute([$identifier, $codeType]);
                $codeData = $stmt_check->fetch();

                if ($codeData) {
                    $lastCodeTime = new DateTime($codeData['created_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $secondsPassed = $currentTime->getTimestamp() - $lastCodeTime->getTimestamp();

                    if ($secondsPassed < CODE_RESEND_COOLDOWN_SECONDS) {
                        $secondsRemaining = CODE_RESEND_COOLDOWN_SECONDS - $secondsPassed;
                        throw new Exception('js.auth.errorCodeCooldown');
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = ?");
                $stmt->execute([$identifier, $codeType]);

                $verificationCode = str_replace('-', '', generateVerificationCode());

                $stmt = $pdo->prepare(
                    "INSERT INTO verification_codes (identifier, code_type, code) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$identifier, $codeType, $verificationCode]);


                $response['success'] = true;
                $response['message'] = 'js.settings.successCodeGenerated';
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - request-email-change-code');
                    if (strpos($e->getMessage(), 'Data truncated') !== false) {
                        $response['message'] = "js.api.errorDatabaseEnum";
                    } else {
                        $response['message'] = 'js.api.errorDatabase';
                    }
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorCodeCooldown') {
                        $response['data'] = ['seconds' => $secondsRemaining ?? CODE_RESEND_COOLDOWN_SECONDS];
                    }
                }
            }
        } elseif ($action === 'verify-email-change-code') {
            try {
                $submittedCode = str_replace('-', '', $_POST['verification_code'] ?? '');
                $identifier = $userId;
                $codeType = 'email_change';

                $ip = getIpAddress();

                if (checkLockStatus($pdo, $identifier, $ip)) {
                    throw new Exception('js.auth.errorTooManyAttempts');
                }


                if (empty($submittedCode)) {
                    throw new Exception('js.settings.errorEnterCode');
                }

                $stmt = $pdo->prepare(
                    "SELECT * FROM verification_codes 
                     WHERE identifier = ? 
                     AND code_type = ?
                     AND created_at > (NOW() - INTERVAL 15 MINUTE)"
                );
                $stmt->execute([$identifier, $codeType]);
                $codeData = $stmt->fetch();

                if (!$codeData || strtolower($codeData['code']) !== strtolower($submittedCode)) {

                    logFailedAttempt($pdo, $identifier, $ip, 'password_verify_fail');
                    throw new Exception('js.auth.errorCodeExpired');
                }


                clearFailedAttempts($pdo, $identifier);

                $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                $stmt->execute([$codeData['id']]);

                $response['success'] = true;
                $response['message'] = 'js.settings.successVerification';
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - verify-email-change-code');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorTooManyAttempts') {
                        $response['data'] = ['minutes' => LOCKOUT_TIME_MINUTES];
                    }
                }
            }
        } elseif ($action === 'update-email') {
            try {

                define('EMAIL_CHANGE_COOLDOWN_DAYS', 12);

                $stmt_check_email = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'email' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check_email->execute([$userId]);
                $lastLogEmail = $stmt_check_email->fetch();

                if ($lastLogEmail) {
                    $lastChangeTime = new DateTime($lastLogEmail['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);
                    $daysPassed = (int)$interval->format('%a');

                    if ($daysPassed < EMAIL_CHANGE_COOLDOWN_DAYS) {
                        $daysRemaining = EMAIL_CHANGE_COOLDOWN_DAYS - $daysPassed;
                        $response['message'] = 'js.settings.errorEmailCooldown';
                        $response['data'] = ['days' => $daysRemaining];
                        echo json_encode($response);
                        exit;
                    }
                }

                $newEmail = trim($_POST['email'] ?? '');
                $oldEmail = $_SESSION['email'];

                if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('js.auth.errorInvalidEmail');
                }
                if (strlen($newEmail) > MAX_EMAIL_LENGTH) {
                    throw new Exception('js.auth.errorEmailLength');
                }
                if ($newEmail === $oldEmail) {
                    throw new Exception('js.settings.errorEmailIsCurrent');
                }

                $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
                $emailDomain = substr($newEmail, strrpos($newEmail, '@') + 1);

                if (!in_array(strtolower($emailDomain), $allowedDomains)) {
                    throw new Exception('js.auth.errorEmailDomain');
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$newEmail, $userId]);

                if ($stmt->fetch()) {
                    throw new Exception('js.auth.errorEmailInUse');
                }

                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$newEmail, $userId]);

                $stmt_log_email = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'email', ?, ?, ?)"
                );
                $stmt_log_email->execute([$userId, $oldEmail, $newEmail, getIpAddress()]);

                $_SESSION['email'] = $newEmail;

                $response['success'] = true;
                $response['message'] = 'js.settings.successEmailUpdate';
                $response['newEmail'] = $newEmail;
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-email');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'verify-current-password') {
            try {
                $ip = getIpAddress();
                $identifier = $userId;
                $currentPassword = $_POST['current_password'] ?? '';

                if (checkLockStatus($pdo, $identifier, $ip)) {
                    throw new Exception('js.auth.errorTooManyAttempts');
                }

                if (empty($currentPassword)) {
                    throw new Exception('js.settings.errorEnterCurrentPass');
                }

                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hashedPassword = $stmt->fetchColumn();

                if ($hashedPassword && password_verify($currentPassword, $hashedPassword)) {
                    clearFailedAttempts($pdo, $identifier);
                    $response['success'] = true;
                    $response['message'] = 'js.settings.successPasswordVerify';
                } else {
                    logFailedAttempt($pdo, $identifier, $ip, 'password_verify_fail');
                    throw new Exception('js.settings.errorPasswordVerifyIncorrect');
                }
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - verify-current-password');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorTooManyAttempts') {
                        $response['data'] = ['minutes' => LOCKOUT_TIME_MINUTES];
                    }
                }
            }
        } elseif ($action === 'update-password') {
            try {

                define('PASSWORD_CHANGE_COOLDOWN_HOURS', 24);

                $stmt_check_pass = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'password' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check_pass->execute([$userId]);
                $lastLogPass = $stmt_check_pass->fetch();

                if ($lastLogPass) {
                    $lastChangeTime = new DateTime($lastLogPass['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);

                    $hoursPassed = ($interval->d * 24) + $interval->h;

                    if ($hoursPassed < PASSWORD_CHANGE_COOLDOWN_HOURS) {
                        $hoursRemaining = PASSWORD_CHANGE_COOLDOWN_HOURS - $hoursPassed;
                        $response['message'] = 'js.settings.errorPasswordCooldown';
                        $response['data'] = ['hours' => $hoursRemaining];
                        echo json_encode($response);
                        exit;
                    }
                }

                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception('js.settings.errorNewPasswordEmpty');
                }
                if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
                    throw new Exception('js.auth.errorPasswordMinLength');
                }
                if (strlen($newPassword) > MAX_PASSWORD_LENGTH) {
                    throw new Exception('js.auth.errorPasswordMaxLength');
                }
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('js.auth.errorPasswordMismatch');
                }

                $stmt_get_old = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt_get_old->execute([$userId]);
                $oldHashedPassword = $stmt_get_old->fetchColumn();
                if (!$oldHashedPassword) {
                    $oldHashedPassword = 'hash_desconocido';
                }

                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHashedPassword, $userId]);

                $stmt_log_pass = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'password', ?, ?, ?)"
                );
                $stmt_log_pass->execute([$userId, $oldHashedPassword, $newHashedPassword, getIpAddress()]);

                $newTimestamp = gmdate('Y-m-d H:i:s'); 

                $response['success'] = true;
                $response['message'] = 'js.settings.successPassUpdate';
                
                $response['newTimestamp'] = $newTimestamp; 

            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-password');
                    if (strpos($e->getMessage(), "Data truncated for column 'change_type'") !== false) {
                        $response['message'] = 'js.api.errorDatabaseEnum';
                    } else {
                        $response['message'] = 'js.api.errorDatabase';
                    }
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorPasswordMinLength') {
                        $response['data'] = ['length' => MIN_PASSWORD_LENGTH];
                    } elseif ($response['message'] === 'js.auth.errorPasswordMaxLength') {
                         $response['data'] = ['length' => MAX_PASSWORD_LENGTH];
                    }
                }
            }
        } elseif ($action === 'toggle-2fa') {
            try {
                $stmt_get = $pdo->prepare("SELECT is_2fa_enabled FROM users WHERE id = ?");
                $stmt_get->execute([$userId]);
                $currentState = (int)$stmt_get->fetchColumn();

                $newState = $currentState === 1 ? 0 : 1;

                $stmt_set = $pdo->prepare("UPDATE users SET is_2fa_enabled = ? WHERE id = ?");
                $stmt_set->execute([$newState, $userId]);

                $response['success'] = true;
                $response['newState'] = $newState;
                $response['message'] = $newState === 1
                    ? 'js.settings.success2faEnabled'
                    : 'js.settings.success2faDisabled';
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - toggle-2fa');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'update-preference') {
            try {
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';

                $allowedFields = [
                    'language' => ['en-us', 'fr-fr', 'es-latam', 'es-mx'],
                    'theme' => ['system', 'light', 'dark'],
                    'usage_type' => ['personal', 'student', 'teacher', 'small_business', 'large_company'],
                    'open_links_in_new_tab' => ['0', '1'],
                    'increase_message_duration' => ['0', '1']
                ];

                if (!array_key_exists($field, $allowedFields)) {
                    throw new Exception('js.settings.errorPreferenceInvalid');
                }

                if (!in_array($value, $allowedFields[$field])) {
                    if ($field === 'open_links_in_new_tab' || $field === 'increase_message_duration') {
                        throw new Exception('js.settings.errorPreferenceToggle');
                    }
                    throw new Exception('js.settings.errorPreferenceInvalid');
                }

                $sql = "UPDATE user_preferences SET $field = ? WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);

                $finalValue = ($field === 'open_links_in_new_tab' || $field === 'increase_message_duration')
                    ? (int)$value
                    : $value;

                $stmt->execute([$finalValue, $userId]);

                $response['success'] = true;
                $response['message'] = 'js.settings.successPreference';
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-preference');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'logout-all-devices') {
            try {
                $newAuthToken = bin2hex(random_bytes(32));

                $stmt = $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
                $stmt->execute([$newAuthToken, $userId]);

                $_SESSION['auth_token'] = $newAuthToken;

                $response['success'] = true;
                $response['message'] = 'js.settings.successLogoutAll';
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - logout-all-devices');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        } elseif ($action === 'delete-account') {
            try {
                $ip = getIpAddress();
                $identifier = $userId;
                $currentPassword = $_POST['current_password'] ?? '';

                if (checkLockStatus($pdo, $identifier, $ip)) {
                    throw new Exception('js.auth.errorTooManyAttempts');
                }

                if (empty($currentPassword)) {
                    throw new Exception('js.settings.errorEnterCurrentPass');
                }

                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hashedPassword = $stmt->fetchColumn();

                if ($hashedPassword && password_verify($currentPassword, $hashedPassword)) {
                    clearFailedAttempts($pdo, $identifier);
                    
                    $stmt_delete = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE id = ?");
                    $stmt_delete->execute([$userId]);

                    $_SESSION = [];
                    session_destroy();
                    
                    $response['success'] = true;
                    $response['message'] = 'js.settings.successAccountDeleted';

                } else {
                    logFailedAttempt($pdo, $identifier, $ip, 'password_verify_fail');
                    throw new Exception('js.settings.errorPasswordVerifyIncorrect');
                }
            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - delete-account');
                    $response['message'] = 'js.api.errorDatabase';
                } else {
                    $response['message'] = $e->getMessage();
                    if ($response['message'] === 'js.auth.errorTooManyAttempts') {
                        $response['data'] = ['minutes' => LOCKOUT_TIME_MINUTES];
                    }
                }
            }
        }
    }
}

echo json_encode($response);
exit;

?>