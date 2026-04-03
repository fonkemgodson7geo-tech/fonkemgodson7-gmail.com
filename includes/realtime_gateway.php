<?php

function rtGatewayStatus(): array
{
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    $smtpReady = SMTP_HOST !== '' && SMTP_PORT > 0 && EMAIL_FROM_ADDRESS !== '' && (BREVO_API_KEY !== '' || (is_file($vendorAutoload) && SMTP_USER !== '' && SMTP_PASS !== ''));
    $smsReady = TWILIO_ACCOUNT_SID !== '' && TWILIO_AUTH_TOKEN !== '' && TWILIO_FROM_NUMBER !== '';
    $voiceReady = $smsReady && TWILIO_VOICE_URL !== '';
    $paymentsReady = CINERPAY_API_KEY !== '' && CINERPAY_SITE_ID !== '';

    return [
        'sms' => $smsReady ? 'configured' : 'not-configured',
        'email' => $smtpReady ? 'configured' : 'not-configured',
        'voice' => $voiceReady ? 'configured' : 'not-configured',
        'payments' => $paymentsReady ? 'configured' : 'not-configured',
        'alerts' => ($smsReady || $smtpReady) ? 'configured' : 'not-configured',
        'verification' => ($smsReady || $smtpReady) ? 'configured' : 'not-configured',
    ];
}

function rtHttpPostForm(string $url, array $headers, array $formFields, ?string &$failureReason = null): ?array
{
    if (!function_exists('curl_init')) {
        $failureReason = 'cURL extension is not enabled.';
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($formFields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false || $curlError !== '') {
        $failureReason = 'Gateway request failed.';
        error_log('Realtime gateway HTTP form failure: ' . $curlError);
        return null;
    }

    return ['status' => $httpCode, 'body' => $responseBody];
}

function rtHttpPostJson(string $url, array $headers, array $payload, ?string &$failureReason = null): ?array
{
    if (!function_exists('curl_init')) {
        $failureReason = 'cURL extension is not enabled.';
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false || $curlError !== '') {
        $failureReason = 'Gateway request failed.';
        error_log('Realtime gateway HTTP JSON failure: ' . $curlError);
        return null;
    }

    return ['status' => $httpCode, 'body' => $responseBody];
}

function rtSendSms(string $phone, string $text, ?string &$failureReason = null): bool
{
    if (TWILIO_ACCOUNT_SID === '' || TWILIO_AUTH_TOKEN === '' || TWILIO_FROM_NUMBER === '') {
        $failureReason = 'SMS gateway is not configured on this server.';
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
    $response = rtHttpPostForm(
        $url,
        ['Authorization: Basic ' . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN)],
        [
            'To' => $phone,
            'From' => TWILIO_FROM_NUMBER,
            'Body' => $text,
        ],
        $failureReason
    );

    if ($response === null) {
        return false;
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $failureReason = 'SMS provider rejected the request.';
        error_log('Realtime SMS failure: HTTP ' . $response['status'] . ' ' . $response['body']);
        return false;
    }

    return true;
}

function rtSendEmail(string $toEmail, string $subject, string $htmlBody, string $textBody = '', ?string &$failureReason = null): bool
{
    if (BREVO_API_KEY !== '' && EMAIL_FROM_ADDRESS !== '') {
        $response = rtHttpPostJson(
            'https://api.brevo.com/v3/smtp/email',
            ['api-key: ' . BREVO_API_KEY],
            [
                'sender' => ['name' => EMAIL_FROM_NAME, 'email' => EMAIL_FROM_ADDRESS],
                'to' => [['email' => $toEmail]],
                'subject' => $subject,
                'htmlContent' => $htmlBody,
                'textContent' => $textBody !== '' ? $textBody : trim(strip_tags($htmlBody)),
            ],
            $failureReason
        );

        if ($response === null) {
            return false;
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $failureReason = 'Email provider rejected the request.';
            error_log('Realtime email failure: HTTP ' . $response['status'] . ' ' . $response['body']);
            return false;
        }

        return true;
    }

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        $failureReason = 'Email gateway is not configured on this server.';
        return false;
    }

    require_once $autoloadPath;

    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $failureReason = 'PHPMailer is not installed.';
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = SMTP_USER !== '';
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        $failureReason = 'Email could not be delivered.';
        error_log('Realtime email failure: ' . $e->getMessage());
        return false;
    }
}

function rtStartVoiceCall(string $phone, ?string &$failureReason = null): bool
{
    if (TWILIO_ACCOUNT_SID === '' || TWILIO_AUTH_TOKEN === '' || TWILIO_FROM_NUMBER === '' || TWILIO_VOICE_URL === '') {
        $failureReason = 'Voice gateway is not configured on this server.';
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Calls.json';
    $response = rtHttpPostForm(
        $url,
        ['Authorization: Basic ' . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN)],
        [
            'To' => $phone,
            'From' => TWILIO_FROM_NUMBER,
            'Url' => TWILIO_VOICE_URL,
        ],
        $failureReason
    );

    if ($response === null) {
        return false;
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $failureReason = 'Voice provider rejected the request.';
        error_log('Realtime voice failure: HTTP ' . $response['status'] . ' ' . $response['body']);
        return false;
    }

    return true;
}

function rtIssueVerificationCode(PDO $pdo, ?int $userId, string $type, string $destination, string $purpose, ?string &$failureReason = null, int $ttlSeconds = 600): ?string
{
    $code = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

    if ($type === 'sms') {
        $sent = rtSendSms($destination, $purpose . ' code: ' . $code . '. Expires in ' . (int)round($ttlSeconds / 60) . ' minutes.', $failureReason);
    } elseif ($type === 'email') {
        $html = '<p>Your ' . htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') . ' code is <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>This code expires in ' . (int)round($ttlSeconds / 60) . ' minutes.</p>';
        $sent = rtSendEmail($destination, SITE_NAME . ' verification code', $html, 'Your ' . $purpose . ' code is ' . $code . '.', $failureReason);
    } else {
        $failureReason = 'Unsupported verification channel.';
        return null;
    }

    if (!$sent) {
        return null;
    }

    $stmt = $pdo->prepare('INSERT INTO verification_codes (user_id, code, type, expires_at, used) VALUES (?, ?, ?, ?, 0)');
    $stmt->execute([$userId, $code, $type, $expiresAt]);
    return $code;
}

function rtVerifyLatestCode(PDO $pdo, ?int $userId, string $type, string $code, ?string &$failureReason = null): bool
{
    if ($userId === null) {
        $stmt = $pdo->prepare('SELECT id, code, expires_at FROM verification_codes WHERE user_id IS NULL AND type = ? AND used = 0 ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->prepare('SELECT id, code, expires_at FROM verification_codes WHERE user_id = ? AND type = ? AND used = 0 ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId, $type]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $failureReason = 'No active verification code found.';
        return false;
    }

    $expiresAtTs = strtotime((string)$row['expires_at']);
    if ($expiresAtTs === false || $expiresAtTs < time()) {
        $failureReason = 'Verification code has expired.';
        return false;
    }

    if (!hash_equals((string)$row['code'], $code)) {
        $failureReason = 'Verification code is invalid.';
        return false;
    }

    $markUsed = $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE id = ?');
    $markUsed->execute([(int)$row['id']]);
    return true;
}

function rtSendAlert(array $channels, array $destinations, string $subject, string $message, ?string &$failureReason = null): array
{
    $results = [];
    foreach ($channels as $channel) {
        if ($channel === 'sms' && !empty($destinations['sms'])) {
            $channelError = null;
            $results['sms'] = rtSendSms((string)$destinations['sms'], $message, $channelError);
            if (!$results['sms']) {
                $failureReason = $channelError;
            }
        }
        if ($channel === 'email' && !empty($destinations['email'])) {
            $channelError = null;
            $results['email'] = rtSendEmail((string)$destinations['email'], $subject, nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')), $message, $channelError);
            if (!$results['email']) {
                $failureReason = $channelError;
            }
        }
        if ($channel === 'voice' && !empty($destinations['voice'])) {
            $channelError = null;
            $results['voice'] = rtStartVoiceCall((string)$destinations['voice'], $channelError);
            if (!$results['voice']) {
                $failureReason = $channelError;
            }
        }
    }

    return $results;
}

function rtCreateCinetPayPayment(array $payload, ?string &$failureReason = null): ?array
{
    if (CINERPAY_API_KEY === '' || CINERPAY_SITE_ID === '') {
        $failureReason = 'Payment gateway is not configured on this server.';
        return null;
    }

    $apiPayload = [
        'apikey' => CINERPAY_API_KEY,
        'site_id' => CINERPAY_SITE_ID,
        'transaction_id' => (string)($payload['transaction_id'] ?? ''),
        'amount' => (string)($payload['amount'] ?? ''),
        'currency' => (string)($payload['currency'] ?? 'XAF'),
        'description' => (string)($payload['description'] ?? 'Clinic payment'),
        'notify_url' => (string)($payload['notify_url'] ?? ''),
        'return_url' => (string)($payload['return_url'] ?? ''),
        'channels' => (string)($payload['channels'] ?? 'MOBILE_MONEY'),
        'customer_name' => (string)($payload['customer_name'] ?? 'Patient'),
        'customer_surname' => (string)($payload['customer_surname'] ?? 'User'),
        'customer_email' => (string)($payload['customer_email'] ?? 'patient@example.com'),
        'customer_phone_number' => (string)($payload['customer_phone_number'] ?? ''),
        'customer_address' => (string)($payload['customer_address'] ?? 'N/A'),
        'customer_city' => (string)($payload['customer_city'] ?? 'Douala'),
        'customer_country' => (string)($payload['customer_country'] ?? 'CM'),
        'customer_state' => (string)($payload['customer_state'] ?? 'LT'),
        'customer_zip_code' => (string)($payload['customer_zip_code'] ?? '00000'),
    ];

    $response = rtHttpPostJson('https://api-checkout.cinetpay.com/v2/payment', [], $apiPayload, $failureReason);
    if ($response === null) {
        return null;
    }

    $decoded = json_decode((string)$response['body'], true);
    if (!is_array($decoded)) {
        $failureReason = 'Payment gateway returned an unreadable response.';
        error_log('CinetPay init invalid JSON: ' . $response['body']);
        return null;
    }

    $code = (string)($decoded['code'] ?? '');
    $paymentUrl = (string)($decoded['data']['payment_url'] ?? '');
    if ($paymentUrl === '' || ($code !== '' && $code !== '201')) {
        $failureReason = (string)($decoded['message'] ?? 'Could not start payment checkout.');
        error_log('CinetPay init failure: ' . $response['body']);
        return null;
    }

    return [
        'payment_url' => $paymentUrl,
        'raw' => $decoded,
    ];
}

function rtVerifyCinetPayTransaction(string $transactionId, ?string &$failureReason = null): ?array
{
    if (CINERPAY_API_KEY === '' || CINERPAY_SITE_ID === '') {
        $failureReason = 'Payment gateway is not configured on this server.';
        return null;
    }

    $response = rtHttpPostJson(
        'https://api-checkout.cinetpay.com/v2/payment/check',
        [],
        [
            'apikey' => CINERPAY_API_KEY,
            'site_id' => CINERPAY_SITE_ID,
            'transaction_id' => $transactionId,
        ],
        $failureReason
    );

    if ($response === null) {
        return null;
    }

    $decoded = json_decode((string)$response['body'], true);
    if (!is_array($decoded)) {
        $failureReason = 'Payment verification response could not be parsed.';
        error_log('CinetPay verify invalid JSON: ' . $response['body']);
        return null;
    }

    $gatewayStatus = strtoupper((string)($decoded['data']['status'] ?? ''));
    $localStatus = 'failed';
    if (in_array($gatewayStatus, ['ACCEPTED', 'SUCCESS', 'COMPLETED'], true)) {
        $localStatus = 'completed';
    } elseif (in_array($gatewayStatus, ['PENDING', 'WAITING'], true)) {
        $localStatus = 'pending';
    }

    return [
        'status' => $localStatus,
        'gateway_status' => $gatewayStatus,
        'raw' => $decoded,
    ];
}