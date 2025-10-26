<?php

include '../config/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => __('api.invalidAction')];

define('CODE_RESEND_COOLDOWN_SECONDS', 60);

define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 72);
define('MIN_USERNAME_LENGTH', 6);
define('MAX_USERNAME_LENGTH', 32);
define('MAX_EMAIL_LENGTH', 255);


function generateVerificationCode() {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
}

// ---
// La función getPreferredLanguage() se ha movido a config/config.php
// ---

function logUserMetadata($pdo, $userId) {
    try {
        $ip = getIpAddress(); 
        $browserInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $deviceType = 'Desktop';
        if (preg_match('/(mobile|android|iphone|ipad)/i', strtolower($browserInfo))) {
            $deviceType = 'Mobile';
        }

        $stmt = $pdo->prepare(
            "INSERT INTO user_metadata (user_id, ip_address, device_type, browser_info) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $ip, $deviceType, $browserInfo]);

    } catch (PDOException $e) {
        logDatabaseError($e, 'auth_handler - logUserMetadata');
    }
}


function createUserAndLogin($pdo, $basePath, $email, $username, $passwordHash, $userIdFromVerification) {
    
    $authToken = bin2hex(random_bytes(32)); 
    $stmt = $pdo->prepare("INSERT INTO users (email, username, password, is_2fa_enabled, auth_token) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$email, $username, $passwordHash, $authToken]);
    $userId = $pdo->lastInsertId();

    $localAvatarUrl = null;
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
        
        $imageData = file_get_contents($apiUrl);

        if ($imageData !== false) {
            file_put_contents($fullSavePath, $imageData);
            $localAvatarUrl = $publicUrl;
        }

    } catch (Exception $e) {
    }

    if ($localAvatarUrl) {
        $stmt = $pdo->prepare("UPDATE users SET profile_image_url = ? WHERE id = ?");
        $stmt->execute([$localAvatarUrl, $userId]);
    }

    try {
        // Usar el idioma detectado y guardado en la sesión por config.php
        $preferredLanguage = $_SESSION['language_detected'] ?? 'es-419';

        $stmt_prefs = $pdo->prepare(
            "INSERT INTO user_preferences (user_id, language, theme, usage_type) 
             VALUES (?, ?, 'system', 'personal')"
        );
        $stmt_prefs->execute([$userId, $preferredLanguage]);
        
        // Actualizar la sesión principal con el idioma seleccionado
        $_SESSION['language'] = $preferredLanguage;

    } catch (PDOException $e) {
        logDatabaseError($e, 'auth_handler - createUser - preferences');
    }


    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['profile_image_url'] = $localAvatarUrl;
    $_SESSION['role'] = 'user'; 
    $_SESSION['auth_token'] = $authToken; 
    
    logUserMetadata($pdo, $userId); 
    

    $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
    $stmt->execute([$userIdFromVerification]);

    generateCsrfToken();

    unset($_SESSION['registration_step']);
    unset($_SESSION['registration_email']); 

    return true;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        $response['success'] = false;
        $response['message'] = __('api.securityErrorReload');
        echo json_encode($response);
        exit;
    }


    if (isset($_POST['action'])) {
    
        $action = $_POST['action'];

        if ($action === 'register-check-email') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
            $emailDomain = substr($email, strrpos($email, '@') + 1);

            if (empty($email) || empty($password)) {
                $response['message'] = __('api.register.error.emptyEmailPass');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = __('api.register.error.invalidEmailFormat');
            } elseif (strlen($email) > MAX_EMAIL_LENGTH) {
                $response['message'] = __('api.register.error.emailTooLong', ['max' => MAX_EMAIL_LENGTH]);
            } elseif (!in_array(strtolower($emailDomain), $allowedDomains)) {
                $response['message'] = __('api.register.error.domainNotAllowed');
            } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
                $response['message'] = __('api.register.error.passwordTooShort', ['min' => MIN_PASSWORD_LENGTH]);
            } elseif (strlen($password) > MAX_PASSWORD_LENGTH) {
                $response['message'] = __('api.register.error.passwordTooLong', ['max' => MAX_PASSWORD_LENGTH]);
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $response['message'] = __('api.register.error.emailInUse');
                    } else {
                        $_SESSION['registration_step'] = 2;
                        $_SESSION['registration_email'] = $email; 
                        $response['success'] = true;
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - register-check-email');
                    $response['message'] = __('api.dbError');
                }
            }
        }

        elseif ($action === 'register-check-username-and-generate-code') {
            
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $username = $_POST['username'] ?? '';

            if (empty($email) || empty($password) || empty($username)) {
                $response['message'] = __('api.register.error.missingStepData');
            } elseif (strlen($password) < MIN_PASSWORD_LENGTH || strlen($password) > MAX_PASSWORD_LENGTH) {
                 $response['message'] = __('api.register.error.passwordLengthInvalid', ['min' => MIN_PASSWORD_LENGTH, 'max' => MAX_PASSWORD_LENGTH]);
            } elseif (strlen($username) < MIN_USERNAME_LENGTH) {
                $response['message'] = __('api.register.error.usernameTooShort', ['min' => MIN_USERNAME_LENGTH]);
            } elseif (strlen($username) > MAX_USERNAME_LENGTH) {
                $response['message'] = __('api.register.error.usernameTooLong', ['max' => MAX_USERNAME_LENGTH]);
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $response['message'] = __('api.register.error.usernameInUse');
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'");
                        $stmt->execute([$email]);

                        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                        $verificationCode = str_replace('-', '', generateVerificationCode());
                        
                        $payload = json_encode([
                            'username' => $username,
                            'password_hash' => $hashedPassword
                        ]);

                        $stmt = $pdo->prepare(
                            "INSERT INTO verification_codes (identifier, code_type, code, payload) 
                             VALUES (?, 'registration', ?, ?)"
                        );
                        $stmt->execute([$email, $verificationCode, $payload]);

                        $_SESSION['registration_step'] = 3;

                        $response['success'] = true;
                        $response['message'] = __('api.register.codeGenerated');
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - register-check-username');
                    $response['message'] = __('api.dbError');
                }
            }
        }

        elseif ($action === 'register-verify') {
            if (empty($_POST['email']) || empty($_POST['verification_code'])) {
                $response['message'] = __('api.register.error.emptyEmailOrCode');
            } else {
                $email = $_POST['email'];
                $submittedCode = str_replace('-', '', $_POST['verification_code']); 

                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? 
                         AND code_type = 'registration'
                         AND created_at > (NOW() - INTERVAL 15 MINUTE)"
                    );
                    $stmt->execute([$email]);
                    $pendingUser = $stmt->fetch();

                    if (!$pendingUser) {
                        $response['message'] = __('api.register.error.codeInvalidOrExpired');
                        unset($_SESSION['registration_step']);
                        unset($_SESSION['registration_email']); 
                    
                    } else {
                        if (strtolower($pendingUser['code']) !== strtolower($submittedCode)) {
                            $response['message'] = __('api.register.error.codeIncorrect');
                        } else {
                            $payloadData = json_decode($pendingUser['payload'], true);
                            
                            if (!$payloadData || empty($payloadData['username']) || empty($payloadData['password_hash'])) {
                                $response['message'] = __('api.register.error.corruptData');
                            } else {
                                createUserAndLogin(
                                    $pdo, 
                                    $basePath, 
                                    $pendingUser['identifier'], 
                                    $payloadData['username'], 
                                    $payloadData['password_hash'], 
                                    $pendingUser['id'] 
                                );
                                
                                $response['success'] = true;
                                $response['message'] = __('api.register.success');
                            }
                        }
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - register-verify');
                    $response['message'] = __('api.dbError');
                }
            }
        }
        
        elseif ($action === 'register-resend-code') {
            $email = $_POST['email'] ?? '';

            if (empty($email)) {
                $response['message'] = __('api.resend.error.noEmail');
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? AND code_type = 'registration' 
                         ORDER BY created_at DESC LIMIT 1"
                    );
                    $stmt->execute([$email]);
                    $codeData = $stmt->fetch();

                    if (!$codeData) {
                        throw new Exception(__('api.resend.error.noData'));
                    }

                    $lastCodeTime = new DateTime($codeData['created_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $secondsPassed = $currentTime->getTimestamp() - $lastCodeTime->getTimestamp();

                    if ($secondsPassed < CODE_RESEND_COOLDOWN_SECONDS) {
                        $secondsRemaining = CODE_RESEND_COOLDOWN_SECONDS - $secondsPassed;
                        throw new Exception(__('api.resend.error.wait', ['seconds' => $secondsRemaining]));
                    }

                    $newCode = str_replace('-', '', generateVerificationCode());
                    
                    $stmt = $pdo->prepare(
                        "UPDATE verification_codes SET code = ?, created_at = NOW() WHERE id = ?"
                    );
                    $stmt->execute([$newCode, $codeData['id']]);


                    $response['success'] = true;
                    $response['message'] = __('api.resend.success');

                } catch (Exception $e) {
                    if ($e instanceof PDOException) {
                        logDatabaseError($e, 'auth_handler - register-resend-code');
                        $response['message'] = __('api.dbError');
                    } else {
                        $response['message'] = $e->getMessage();
                    }
                }
            }
        }

        elseif ($action === 'login-check-credentials') {
            if (!empty($_POST['email']) && !empty($_POST['password'])) {
                
                $email = $_POST['email'];
                $password = $_POST['password'];
                $ip = getIpAddress(); 

                if (checkLockStatus($pdo, $email, $ip)) {
                    $response['message'] = __('api.login.error.tooManyAttempts', ['minutes' => LOCKOUT_TIME_MINUTES]);
                    echo json_encode($response);
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password'])) {
                        
                        clearFailedAttempts($pdo, $email);

                        if ($user['is_2fa_enabled'] == 1) {
                            
                            $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = '2fa'");
                            $stmt->execute([$email]);

                            $verificationCode = str_replace('-', '', generateVerificationCode());
                            $stmt = $pdo->prepare(
                                "INSERT INTO verification_codes (identifier, code_type, code) 
                                 VALUES (?, '2fa', ?)"
                            );
                            $stmt->execute([$email, $verificationCode]);


                            $response['success'] = true;
                            $response['message'] = __('api.login.2faRequired');
                            $response['is_2fa_required'] = true; 

                        } else {
                            session_regenerate_id(true);

                            $authToken = $user['auth_token'];
                            if (empty($authToken)) {
                                $authToken = bin2hex(random_bytes(32));
                                $stmt_token = $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
                                $stmt_token->execute([$authToken, $user['id']]);
                            }

                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['profile_image_url'] = $user['profile_image_url'];
                            $_SESSION['role'] = $user['role']; 
                            $_SESSION['auth_token'] = $authToken; 
                            
                            // Cargar preferencias en la sesión al iniciar sesión
                            $stmt_prefs = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                            $stmt_prefs->execute([$user['id']]);
                            $prefs = $stmt_prefs->fetch();
                            if ($prefs) {
                                $_SESSION['language'] = $prefs['language'];
                                $_SESSION['theme'] = $prefs['theme'];
                                $_SESSION['usage_type'] = $prefs['usage_type'];
                                $_SESSION['open_links_in_new_tab'] = (int)$prefs['open_links_in_new_tab'];
                                $_SESSION['increase_message_duration'] = (int)$prefs['increase_message_duration'];
                            }
                            
                            logUserMetadata($pdo, $user['id']); 

                            generateCsrfToken();

                            $response['success'] = true;
                            $response['message'] = __('api.login.success');
                            $response['is_2fa_required'] = false; 
                        }
                    
                    } else {
                        logFailedAttempt($pdo, $email, $ip, 'login_fail');
                        $response['message'] = __('api.login.error.invalidCredentials');
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - login-check-credentials');
                    $response['message'] = __('api.dbError');
                }
            } else {
                $response['message'] = __('api.login.error.emptyFields');
            }
        }


        elseif ($action === 'login-verify-2fa') {
            $email = $_POST['email'] ?? '';
            $submittedCode = str_replace('-', '', $_POST['verification_code'] ?? '');
            $ip = getIpAddress();

            if (empty($email) || empty($submittedCode)) {
                $response['message'] = __('api.login.error.empty2faFields');
            } else {
                
                if (checkLockStatus($pdo, $email, $ip)) {
                    $response['message'] = __('api.login.error.tooManyAttempts', ['minutes' => LOCKOUT_TIME_MINUTES]);
                    echo json_encode($response);
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? 
                         AND code_type = '2fa'
                         AND created_at > (NOW() - INTERVAL 15 MINUTE)" 
                    );
                    $stmt->execute([$email]);
                    $codeData = $stmt->fetch();

                    if (!$codeData || strtolower($codeData['code']) !== strtolower($submittedCode)) {
                        logFailedAttempt($pdo, $email, $ip, 'login_fail');
                        $response['message'] = __('api.login.error.invalid2faCode');
                    } else {
                        
                        clearFailedAttempts($pdo, $email);

                        $stmt_user = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                        $stmt_user->execute([$email]);
                        $user = $stmt_user->fetch();

                        if ($user) {
                            session_regenerate_id(true);

                            $authToken = $user['auth_token'];
                            if (empty($authToken)) {
                                $authToken = bin2hex(random_bytes(32));
                                $stmt_token = $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
                                $stmt_token->execute([$authToken, $user['id']]);
                            }

                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['profile_image_url'] = $user['profile_image_url'];
                            $_SESSION['role'] = $user['role']; 
                            $_SESSION['auth_token'] = $authToken; 
                            
                            // Cargar preferencias en la sesión
                            $stmt_prefs = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                            $stmt_prefs->execute([$user['id']]);
                            $prefs = $stmt_prefs->fetch();
                            if ($prefs) {
                                $_SESSION['language'] = $prefs['language'];
                                $_SESSION['theme'] = $prefs['theme'];
                                $_SESSION['usage_type'] = $prefs['usage_type'];
                                $_SESSION['open_links_in_new_tab'] = (int)$prefs['open_links_in_new_tab'];
                                $_SESSION['increase_message_duration'] = (int)$prefs['increase_message_duration'];
                            }
                            
                            logUserMetadata($pdo, $user['id']); 
                            
                            generateCsrfToken();

                            $stmt_delete = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                            $stmt_delete->execute([$codeData['id']]);

                            $response['success'] = true;
                            $response['message'] = __('api.login.success');
                        } else {
                            $response['message'] = __('api.login.error.userNotFound');
                        }
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - login-verify-2fa');
                    $response['message'] = __('api.dbError');
                }
            }
        }



        elseif ($action === 'reset-check-email') {
            $email = $_POST['email'] ?? '';

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = __('api.reset.error.invalidEmail');
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if (!$stmt->fetch()) {
                        $response['success'] = true; 
                        $response['message'] = __('api.reset.emailSentIfValid');
                        
                        $_SESSION['reset_step'] = 2;
                        $_SESSION['reset_email'] = $email;
                        
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'password_reset'");
                        $stmt->execute([$email]);

                        $verificationCode = str_replace('-', '', generateVerificationCode());

                        $stmt = $pdo->prepare(
                            "INSERT INTO verification_codes (identifier, code_type, code) 
                             VALUES (?, 'password_reset', ?)"
                        );
                        $stmt->execute([$email, $verificationCode]);

                        $response['success'] = true;
                        $response['message'] = __('api.reset.codeGenerated');
                        
                        $_SESSION['reset_step'] = 2;
                        $_SESSION['reset_email'] = $email;
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - reset-check-email');
                    $response['message'] = __('api.dbError');
                }
            }
        }

        elseif ($action === 'reset-resend-code') {
            $email = $_POST['email'] ?? '';

            if (empty($email)) {
                $response['message'] = __('api.resend.error.noEmail');
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? AND code_type = 'password_reset' 
                         ORDER BY created_at DESC LIMIT 1"
                    );
                    $stmt->execute([$email]);
                    $codeData = $stmt->fetch();

                    if (!$codeData) {
                        throw new Exception(__('api.resend.error.noResetData'));
                    }

                    $lastCodeTime = new DateTime($codeData['created_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $secondsPassed = $currentTime->getTimestamp() - $lastCodeTime->getTimestamp();

                    if ($secondsPassed < CODE_RESEND_COOLDOWN_SECONDS) {
                        $secondsRemaining = CODE_RESEND_COOLDOWN_SECONDS - $secondsPassed;
                        throw new Exception(__('api.resend.error.wait', ['seconds' => $secondsRemaining]));
                    }

                    $newCode = str_replace('-', '', generateVerificationCode());
                    
                    $stmt = $pdo->prepare(
                        "UPDATE verification_codes SET code = ?, created_at = NOW() WHERE id = ?"
                    );
                    $stmt->execute([$newCode, $codeData['id']]);

                    $response['success'] = true;
                    $response['message'] = __('api.resend.success');

                } catch (Exception $e) {
                    if ($e instanceof PDOException) {
                        logDatabaseError($e, 'auth_handler - reset-resend-code');
                        $response['message'] = __('api.dbError');
                    } else {
                        $response['message'] = $e->getMessage();
                    }
                }
            }
        }

        elseif ($action === 'reset-check-code') {
            $email = $_POST['email'] ?? '';
            $submittedCode = str_replace('-', '', $_POST['verification_code'] ?? '');
            $ip = getIpAddress(); 

            if (empty($email) || empty($submittedCode)) {
                $response['message'] = __('api.reset.error.emptyFields');
            } else {
                
                if (checkLockStatus($pdo, $email, $ip)) {
                    $response['message'] = __('api.login.error.tooManyAttempts', ['minutes' => LOCKOUT_TIME_MINUTES]);
                    echo json_encode($response);
                    exit;
                }
                
                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? 
                         AND code_type = 'password_reset'
                         AND created_at > (NOW() - INTERVAL 15 MINUTE)"
                    );
                    $stmt->execute([$email]);
                    $codeData = $stmt->fetch();

                    if (!$codeData || strtolower($codeData['code']) !== strtolower($submittedCode)) {
                        logFailedAttempt($pdo, $email, $ip, 'reset_fail');
                        $response['message'] = __('api.reset.error.invalidCode');
                    } else {
                        clearFailedAttempts($pdo, $email);
                        $response['success'] = true;
                        
                        $_SESSION['reset_step'] = 3;
                        $_SESSION['reset_code'] = $submittedCode; 
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - reset-check-code');
                    $response['message'] = __('api.dbError');
                }
            }
        }

        elseif ($action === 'reset-update-password') {
            $email = $_SESSION['reset_email'] ?? '';
            $submittedCode = $_SESSION['reset_code'] ?? '';
            
            $newPassword = $_POST['password'] ?? '';
            $ip = getIpAddress(); 

            if (empty($email) || empty($submittedCode) || empty($newPassword)) {
                $response['message'] = __('api.reset.error.missingData');
            } elseif (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
                $response['message'] = __('api.register.error.passwordTooShort', ['min' => MIN_PASSWORD_LENGTH]);
            } elseif (strlen($newPassword) > MAX_PASSWORD_LENGTH) {
                $response['message'] = __('api.register.error.passwordTooLong', ['max' => MAX_PASSWORD_LENGTH]);
            } else {
                
                if (checkLockStatus($pdo, $email, $ip)) {
                    $response['message'] = __('api.login.error.tooManyAttempts', ['minutes' => LOCKOUT_TIME_MINUTES]);
                    echo json_encode($response);
                    exit;
                }

                try {
                    $stmt = $pdo->prepare(
                        "SELECT * FROM verification_codes 
                         WHERE identifier = ? 
                         AND code_type = 'password_reset'
                         AND code = ?" 
                    );
                    $stmt->execute([$email, $submittedCode]);
                    $codeData = $stmt->fetch();

                    if (!$codeData) {
                        logFailedAttempt($pdo, $email, $ip, 'reset_fail');
                        $response['message'] = __('api.reset.error.invalidSession');
                        
                        unset($_SESSION['reset_step']);
                        unset($_SESSION['reset_email']);
                        unset($_SESSION['reset_code']);
                        
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                        
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->execute([$hashedPassword, $email]);

                        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                        $stmt->execute([$codeData['id']]);

                        clearFailedAttempts($pdo, $email);

                        $response['success'] = true;
                        $response['message'] = __('api.reset.success');
                        
                        unset($_SESSION['reset_step']);
                        unset($_SESSION['reset_email']);
                        unset($_SESSION['reset_code']);
                    }
                } catch (PDOException $e) {
                    logDatabaseError($e, 'auth_handler - reset-update-password');
                    $response['message'] = __('api.dbError');
                }
            }
        }
        
    }
}

echo json_encode($response);
exit;
?>