<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/SmsService.php';

final class WarrantyController extends V1Controller
{
    private const ALLOWED_INVOICE_EXTS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const MAX_INVOICE_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Checks for admin session or JWT bearer token.
     */
    private function requireAdminAuth(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['admin_logged_in']) && ($_SESSION['user_role'] ?? '') === 'admin') {
            return [
                'id'    => $_SESSION['user_id'] ?? 'admin-session',
                'name'  => $_SESSION['user_name'] ?? 'Admin',
                'email' => $_SESSION['user_email'] ?? 'admin@hazraev.com',
                'role'  => 'admin',
            ];
        }

        try {
            $payload = JwtAuth::authenticate();
            if ($payload && !empty($payload['sub'])) {
                $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$payload['sub']]);
                if ($user && ($user['role'] ?? '') === 'admin') {
                    return $user;
                }
            }
        } catch (\Throwable) {}

        Envelope::unauthorized('Admin sign-in required');
    }

    // =========================================================================
    // PUBLIC OTP ENDPOINTS
    // =========================================================================

    /**
     * POST /api/v1/warranty/otp/send
     * Body: { mobile: string, purpose: 'warranty_free'|'warranty_paid' }
     */
    public function sendOtp(): never
    {
        $body = ApiRequest::body();
        $mobile = trim((string) ($body['mobile'] ?? ''));
        $purpose = (string) ($body['purpose'] ?? 'warranty_free');

        if (!in_array($purpose, ['warranty_free', 'warranty_paid'], true)) {
            $purpose = 'warranty_free';
        }

        if ($mobile === '') {
            Envelope::fail('INVALID_MOBILE', 'Mobile number is required.', 400, 'mobile');
        }

        $res = SmsService::sendOtp($mobile, $purpose);

        if (!$res['success']) {
            $statusCode = ($res['cooldown'] ?? 0) > 0 ? 429 : 400;
            Envelope::fail('OTP_SEND_FAILED', $res['message'], $statusCode, 'mobile');
        }

        Envelope::ok([
            'sent'            => true,
            'message'         => $res['message'],
            'cooldownSeconds' => $res['cooldown'],
            'debugCode'       => $res['debug_code'] ?? null,
        ]);
    }

    /**
     * POST /api/v1/warranty/otp/verify
     * Body: { mobile: string, purpose: 'warranty_free'|'warranty_paid', code: string }
     */
    public function verifyOtp(): never
    {
        $body = ApiRequest::body();
        $mobile  = trim((string) ($body['mobile'] ?? ''));
        $purpose = (string) ($body['purpose'] ?? 'warranty_free');
        $code    = trim((string) ($body['code'] ?? ''));

        if ($mobile === '' || $code === '') {
            Envelope::fail('INVALID_INPUT', 'Mobile number and OTP code are required.', 400);
        }

        $res = SmsService::verifyOtp($mobile, $purpose, $code);

        if (!$res['success']) {
            Envelope::fail('OTP_VERIFY_FAILED', $res['message'], 400, 'code');
        }

        Envelope::ok([
            'verified'     => true,
            'message'      => $res['message'],
            'sessionToken' => $res['session_token'],
            'expiresAt'    => $res['expires_at'],
        ]);
    }

    // =========================================================================
    // PUBLIC LOOKUP (FOR PAID EXTENSION PRE-CHECK)
    // =========================================================================

    /**
     * GET /api/v1/warranty/registrations/lookup
     * Query: ?chassis=... OR ?reference_no=... OR ?mobile=...
     */
    public function lookup(): never
    {
        $chassis = trim((string) $this->query('chassis', $this->query('chassis_no', '')));
        $ref     = trim((string) $this->query('reference_no', $this->query('ref', '')));
        $mobile  = trim((string) $this->query('mobile', ''));

        if ($chassis === '' && $ref === '' && $mobile === '') {
            Envelope::fail('MISSING_QUERY', 'Chassis number, reference number or mobile is required for lookup.', 400);
        }

        $where = ["type = 'free'"];
        $params = [];

        if ($ref !== '') {
            $where[] = 'reference_no = ?';
            $params[] = strtoupper($ref);
        } elseif ($chassis !== '') {
            $where[] = 'LOWER(chassis_no) = LOWER(?)';
            $params[] = $chassis;
        } else {
            $norm = SmsService::normalizeMobile($mobile);
            $where[] = 'mobile = ?';
            $params[] = $norm ?: $mobile;
        }

        $clause = implode(' AND ', $where);
        $reg = db_fetch_one("SELECT * FROM warranty_registrations WHERE {$clause} ORDER BY created_at DESC LIMIT 1", $params);

        if (!$reg) {
            Envelope::fail(
                'FREE_WARRANTY_NOT_FOUND',
                'No free warranty registration was found matching your details. You must register for free warranty first on the day of purchase.',
                404
            );
        }

        if ($reg['status'] !== 'approved') {
            $statusName = ucwords(str_replace('_', ' ', $reg['status']));
            Envelope::fail(
                'FREE_WARRANTY_NOT_APPROVED',
                "Your free warranty registration ({$reg['reference_no']}) is currently '{$statusName}'. It must be approved before you can purchase an extended warranty.",
                400
            );
        }

        // Check 3 months window (90 days from purchase_date or reviewed_at)
        $anchorDate = !empty($reg['purchase_date']) ? $reg['purchase_date'] : substr($reg['created_at'], 0, 10);
        $diffDays = (int) floor((time() - strtotime($anchorDate)) / 86400);

        if ($diffDays > 90) {
            Envelope::fail(
                'EXTENSION_WINDOW_EXPIRED',
                "Paid warranty extensions must be applied within 3 months (90 days) of purchase. It has been {$diffDays} days since purchase date ({$reg['purchase_date']}).",
                400
            );
        }

        // Check if an active/pending paid warranty already exists
        $existingPaid = db_fetch_one(
            "SELECT reference_no, status FROM warranty_registrations
              WHERE parent_registration_id = ? AND status IN ('pending', 'under_review', 'approved')
              LIMIT 1",
            [$reg['id']]
        );

        if ($existingPaid) {
            Envelope::fail(
                'PAID_WARRANTY_ALREADY_EXISTS',
                "An extended warranty application ({$existingPaid['reference_no']}) has already been submitted for this vehicle (Status: {$existingPaid['status']}).",
                400
            );
        }

        Envelope::ok([
            'id'            => $reg['id'],
            'reference_no'  => $reg['reference_no'],
            'customer_name' => $reg['customer_name'],
            'mobile'        => $reg['mobile'],
            'email'         => $reg['email'],
            'vehicle_model' => $reg['vehicle_model'],
            'chassis_no'    => $reg['chassis_no'],
            'motor_no'      => $reg['motor_no'],
            'controller_no' => $reg['controller_no'],
            'purchase_date' => $reg['purchase_date'],
            'status'        => $reg['status'],
            'days_since'    => $diffDays,
            'eligible'      => true,
        ]);
    }

    // =========================================================================
    // PUBLIC REGISTRATION SUBMIT
    // =========================================================================

    /**
     * POST /api/v1/warranty/registrations
     * Multipart or form submission.
     */
    public function store(): never
    {
        $input = !empty($_POST) ? $_POST : (ApiRequest::body() ?: []);

        $type = strtolower(trim((string) ($input['type'] ?? 'free')));
        if (!in_array($type, ['free', 'paid'], true)) {
            $type = 'free';
        }

        // 1. Validate sessionToken
        $sessionToken = trim((string) ($input['sessionToken'] ?? $input['session_token'] ?? ''));
        if ($sessionToken === '') {
            Envelope::fail('OTP_REQUIRED', 'Mobile OTP verification is required before submitting.', 401, 'sessionToken');
        }

        $now = gmdate('Y-m-d H:i:s');
        $challenge = db_fetch_one(
            "SELECT * FROM otp_challenges
              WHERE session_token = ? AND verified_at IS NOT NULL AND consumed_at IS NULL AND expires_at > ?
              LIMIT 1",
            [$sessionToken, $now]
        );

        if (!$challenge) {
            Envelope::fail(
                'INVALID_OTP_SESSION',
                'Your OTP verification session has expired or was already used. Please verify your mobile number again.',
                401
            );
        }

        // Ensure token purpose matches registration type
        $expectedPurpose = $type === 'paid' ? 'warranty_paid' : 'warranty_free';
        if ($challenge['purpose'] !== $expectedPurpose) {
            Envelope::fail('TOKEN_PURPOSE_MISMATCH', 'OTP token does not match registration type.', 400);
        }

        // 2. Validate customer & mobile
        $customerName = trim((string) ($input['name'] ?? $input['customer_name'] ?? ''));
        $email        = trim((string) ($input['email'] ?? $input['userEmail'] ?? ''));
        $mobile       = trim((string) ($input['mobile'] ?? $input['userMobile'] ?? ''));
        $normMobile   = SmsService::normalizeMobile($mobile);

        if (!$customerName) {
            Envelope::fail('VALIDATION_ERROR', 'Customer full name is required.', 400, 'name');
        }
        if (!$normMobile) {
            Envelope::fail('VALIDATION_ERROR', 'Valid 10-digit mobile number is required.', 400, 'mobile');
        }
        if ($challenge['mobile'] !== $normMobile) {
            Envelope::fail('MOBILE_MISMATCH', 'Form mobile number does not match verified OTP mobile number.', 400, 'mobile');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Envelope::fail('VALIDATION_ERROR', 'Valid email address is required.', 400, 'email');
        }

        // 3. Vehicle Details
        $chassisNo    = strtoupper(trim((string) ($input['chassis'] ?? $input['chassis_no'] ?? '')));
        $motorNo      = strtoupper(trim((string) ($input['motor'] ?? $input['motor_no'] ?? '')));
        $controllerNo = strtoupper(trim((string) ($input['controller'] ?? $input['controller_no'] ?? '')));
        $vehicleModel = trim((string) ($input['model'] ?? $input['vehicle_model'] ?? ''));

        if (!$chassisNo) {
            Envelope::fail('VALIDATION_ERROR', 'Chassis number (VIN) is required.', 400, 'chassis');
        }
        if (!$motorNo) {
            Envelope::fail('VALIDATION_ERROR', 'Motor number is required.', 400, 'motor');
        }

        // 4. Type-specific rules
        $parentRegId = null;
        $plan = null;
        $amountPaise = null;
        $parentReg = null;

        if ($type === 'free') {
            // Check for duplicate free registration
            $existing = db_fetch_one(
                "SELECT reference_no, status FROM warranty_registrations
                  WHERE type = 'free' AND (chassis_no = ? OR motor_no = ?) AND status NOT IN ('rejected', 'cancelled')
                  LIMIT 1",
                [$chassisNo, $motorNo]
            );

            if ($existing) {
                Envelope::fail(
                    'DUPLICATE_CHASSIS',
                    "A free warranty registration ({$existing['reference_no']}) already exists for this vehicle chassis/motor (Status: {$existing['status']}).",
                    409
                );
            }
        } else {
            // Paid flow: verify parent free warranty
            $parentReg = db_fetch_one(
                "SELECT * FROM warranty_registrations
                  WHERE type = 'free' AND (LOWER(chassis_no) = LOWER(?) OR LOWER(reference_no) = LOWER(?))
                    AND status = 'approved'
                  ORDER BY created_at DESC LIMIT 1",
                [$chassisNo, $input['free_reference'] ?? $chassisNo]
            );

            if (!$parentReg) {
                Envelope::fail(
                    'NO_APPROVED_FREE_WARRANTY',
                    'An approved free warranty registration is required before purchasing an extended warranty.',
                    400
                );
            }

            $parentRegId = $parentReg['id'];
            $plan = strtolower(trim((string) ($input['plan'] ?? 'basic')));
            if (!in_array($plan, ['basic', 'premium', '+1y', '+2y'], true)) {
                $plan = 'basic';
            }

            // Normalise plan & price
            if ($plan === 'premium' || $plan === '+2y') {
                $plan = '+2y';
                $amountPaise = 350000; // ₹3,500
            } else {
                $plan = '+1y';
                $amountPaise = 200000; // ₹2,000
            }

            // Check duplicate paid
            $existingPaid = db_fetch_one(
                "SELECT reference_no FROM warranty_registrations
                  WHERE parent_registration_id = ? AND status IN ('pending', 'under_review', 'approved')
                  LIMIT 1",
                [$parentRegId]
            );
            if ($existingPaid) {
                Envelope::fail(
                    'DUPLICATE_PAID_REGISTRATION',
                    "A paid warranty registration ({$existingPaid['reference_no']}) is already on record for this vehicle.",
                    409
                );
            }
        }

        // 5. Battery and Dealer Details
        $batteryType   = trim((string) ($input['batteryType'] ?? $input['battery_type'] ?? ($parentReg['battery_type'] ?? 'na')));
        $batterySerial = trim((string) ($input['batterySerial'] ?? $input['battery_serial'] ?? ($parentReg['battery_serial'] ?? '')));
        $batteryVolt   = trim((string) ($input['batteryVolt'] ?? $input['battery_volt'] ?? ($parentReg['battery_volt'] ?? '')));
        $chargerSerial = trim((string) ($input['chargerSerial'] ?? $input['charger_serial'] ?? ($parentReg['charger_serial'] ?? '')));

        $state        = trim((string) ($input['state'] ?? ($parentReg['state'] ?? '')));
        $district     = trim((string) ($input['dist'] ?? $input['district'] ?? ($parentReg['district'] ?? '')));
        $dealerName   = trim((string) ($input['dealer'] ?? $input['dealer_name'] ?? ($parentReg['dealer_name'] ?? '')));
        $dealerEmail  = trim((string) ($input['dealerEmail'] ?? $input['dealer_email'] ?? ($parentReg['dealer_email'] ?? '')));
        $purchaseDate = trim((string) ($input['purchase_date'] ?? ($parentReg['purchase_date'] ?? date('Y-m-d'))));

        // 6. Invoice handling
        $invoicePath = null;
        $uploadedFile = $_FILES['invoice_file'] ?? $_FILES['invoice'] ?? null;

        if ($uploadedFile && !empty($uploadedFile['tmp_name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            if ($uploadedFile['size'] > self::MAX_INVOICE_BYTES) {
                Envelope::fail('FILE_TOO_LARGE', 'Invoice file size must not exceed 5 MB.', 400);
            }

            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_INVOICE_EXTS, true)) {
                Envelope::fail('INVALID_FILE_TYPE', 'Invoice must be a PDF, JPG, PNG or WebP file.', 400);
            }

            $uploadDir = __BASEDIR__ . '/storage/uploads/warranty';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $safeFilename = 'inv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destination = $uploadDir . '/' . $safeFilename;

            if (!move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
                Envelope::fail('FILE_SAVE_FAILED', 'Failed to save uploaded invoice file.', 500);
            }

            $invoicePath = 'storage/uploads/warranty/' . $safeFilename;
        } elseif ($type === 'paid' && !empty($parentReg['invoice_path'])) {
            // Re-use parent invoice for paid extensions if no new invoice submitted
            $invoicePath = $parentReg['invoice_path'];
        } else {
            Envelope::fail('INVOICE_REQUIRED', 'Purchase invoice file upload is required.', 400, 'invoice_file');
        }

        // 7. Generate Reference Number
        // e.g. HZ-FW-20260926-A1B2 or HZ-PW-20260926-C3D4
        $prefix = $type === 'paid' ? 'HZ-PW-' : 'HZ-FW-';
        $randCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $refNo = $prefix . date('Ymd') . '-' . $randCode;

        $id = Uuid::v4();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $verifiedAt = $challenge['verified_at'] ?? $now;

        db_execute(
            "INSERT INTO warranty_registrations (
                id, type, status, reference_no, customer_name, email, mobile, mobile_verified_at,
                vehicle_model, chassis_no, motor_no, controller_no, battery_type, battery_serial,
                battery_volt, charger_serial, state, district, dealer_name, dealer_email,
                purchase_date, invoice_path, plan, amount_paise, payment_status,
                parent_registration_id, ip_address, user_agent, created_at, updated_at
            ) VALUES (
                ?, ?, 'pending', ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )",
            [
                $id, $type, $refNo, $customerName, $email, $normMobile, $verifiedAt,
                $vehicleModel, $chassisNo, $motorNo, $controllerNo, $batteryType, $batterySerial,
                $batteryVolt, $chargerSerial, $state, $district, $dealerName, $dealerEmail,
                $purchaseDate, $invoicePath, $plan, $amountPaise, $type === 'paid' ? 'unpaid' : null,
                $parentRegId, $ip, $ua, $now, $now
            ]
        );

        // Mark OTP token as consumed
        db_execute("UPDATE otp_challenges SET consumed_at = ? WHERE id = ?", [$now, $challenge['id']]);

        // Send email notification to sales/admin if email is configured
        try {
            $siteSettings = db_fetch_all("SELECT setting_key, setting_value FROM website_settings");
            $cfg = array_column($siteSettings, 'setting_value', 'setting_key');
            $adminEmail = $cfg['recipient_emails'] ?? $cfg['smtp_from_email'] ?? 'sales@hazraev.com';

            $subject = "[Hazra EV] New {$type} Warranty Registration ({$refNo})";
            $message = "A new {$type} warranty registration has been submitted.\n\n"
                     . "Reference: {$refNo}\n"
                     . "Customer: {$customerName}\n"
                     . "Mobile: {$normMobile}\n"
                     . "Chassis: {$chassisNo}\n"
                     . "Purchase Date: {$purchaseDate}\n";
            @Mailer::send($adminEmail, $subject, $message);
        } catch (\Throwable) {}

        Envelope::created([
            'id'           => $id,
            'reference_no' => $refNo,
            'type'         => $type,
            'status'       => 'pending',
            'customer_name'=> $customerName,
            'mobile'       => $normMobile,
            'chassis_no'   => $chassisNo,
            'plan'         => $plan,
            'amount_paise' => $amountPaise,
            'message'      => 'Warranty registration submitted successfully.',
        ]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * GET /api/v1/admin/warranty/registrations
     * Admin list with filtering, searching, counts and pagination.
     */
    public function adminList(): never
    {
        $this->requireAdminAuth();

        $type   = strtolower((string) $this->query('type', 'all'));
        $status = strtolower((string) $this->query('status', 'all'));
        $q      = trim((string) $this->query('q', ''));
        $page   = max(1, (int) $this->query('page', 1));
        $limit  = max(1, min(100, (int) $this->query('pageSize', $this->query('limit', 20))));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if ($type !== 'all' && in_array($type, ['free', 'paid'], true)) {
            $where[] = 'type = ?';
            $params[] = $type;
        }

        if ($status !== 'all' && in_array($status, ['pending', 'under_review', 'approved', 'rejected', 'cancelled'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(customer_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR chassis_no LIKE ? OR motor_no LIKE ? OR reference_no LIKE ? OR dealer_name LIKE ?)';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
        }

        $clause = implode(' AND ', $where);

        $totalRow = db_fetch_one("SELECT COUNT(*) AS total FROM warranty_registrations WHERE {$clause}", $params);
        $total = (int) ($totalRow['total'] ?? 0);

        // Sorting
        $sort = (string) $this->query('sort', 'created_at');
        $dir  = strtolower((string) $this->query('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $allowedSorts = ['created_at', 'purchase_date', 'customer_name', 'status', 'type', 'reference_no'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $rows = db_fetch_all(
            "SELECT id, type, status, reference_no, customer_name, email, mobile,
                    mobile_verified_at, vehicle_model, chassis_no, motor_no, controller_no,
                    battery_type, battery_serial, battery_volt, charger_serial,
                    state, district, dealer_name, dealer_email, purchase_date,
                    invoice_path, plan, amount_paise, payment_status, parent_registration_id,
                    admin_notes, reviewed_by, reviewed_at, created_at, updated_at
               FROM warranty_registrations
              WHERE {$clause}
              ORDER BY {$sort} {$dir}
              LIMIT {$limit} OFFSET {$offset}",
            $params
        ) ?: [];

        // Global status counts
        $allCounts = db_fetch_all("SELECT status, COUNT(*) AS count FROM warranty_registrations GROUP BY status") ?: [];
        $counts = [
            'all'          => (int) (db_fetch_one("SELECT COUNT(*) AS c FROM warranty_registrations")['c'] ?? 0),
            'pending'      => 0,
            'under_review' => 0,
            'approved'     => 0,
            'rejected'     => 0,
            'cancelled'    => 0,
            'free'         => (int) (db_fetch_one("SELECT COUNT(*) AS c FROM warranty_registrations WHERE type = 'free'")['c'] ?? 0),
            'paid'         => (int) (db_fetch_one("SELECT COUNT(*) AS c FROM warranty_registrations WHERE type = 'paid'")['c'] ?? 0),
        ];

        foreach ($allCounts as $c) {
            $st = $c['status'];
            if (isset($counts[$st])) {
                $counts[$st] = (int) $c['count'];
            }
        }

        // Add formatted invoice URLs
        $data = array_map(function ($r) {
            $r['invoice_url'] = !empty($r['invoice_path'])
                ? base_url('api/v1/admin/warranty/registrations/' . $r['id'] . '/invoice')
                : null;
            return $r;
        }, $rows);

        Envelope::ok($data, [
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $limit,
            'pages'    => (int) ceil($total / max(1, $limit)),
            'counts'   => $counts,
        ]);
    }

    /**
     * GET /api/v1/admin/warranty/registrations/{id}
     */
    public function adminShow(): never
    {
        $this->requireAdminAuth();
        $id = (string) $this->param('id');

        $row = db_fetch_one("SELECT * FROM warranty_registrations WHERE id = ?", [$id]);
        if (!$row) {
            Envelope::notFound('REGISTRATION_NOT_FOUND', 'Warranty registration record not found.');
        }

        $row['invoice_url'] = !empty($row['invoice_path'])
            ? base_url('api/v1/admin/warranty/registrations/' . $row['id'] . '/invoice')
            : null;

        if (!empty($row['parent_registration_id'])) {
            $parent = db_fetch_one("SELECT * FROM warranty_registrations WHERE id = ?", [$row['parent_registration_id']]);
            $row['parent_registration'] = $parent ?: null;
        }

        Envelope::ok($row);
    }

    /**
     * PATCH /api/v1/admin/warranty/registrations/{id}
     * Body: { status: 'approved'|'rejected'|'under_review'|'pending', admin_notes: string }
     */
    public function adminUpdate(): never
    {
        $admin = $this->requireAdminAuth();
        $id = (string) $this->param('id');

        $existing = db_fetch_one("SELECT * FROM warranty_registrations WHERE id = ?", [$id]);
        if (!$existing) {
            Envelope::notFound('REGISTRATION_NOT_FOUND', 'Warranty registration record not found.');
        }

        $body = ApiRequest::body();
        $allowedStatuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
        $newStatus = Wire::enumIn((string) ($body['status'] ?? $existing['status']), $allowedStatuses) ?? $existing['status'];
        $adminNotes = isset($body['admin_notes']) ? (string) $body['admin_notes'] : ($existing['admin_notes'] ?? '');

        $now = Wire::now();
        $adminId = $admin['id'] ?? null;

        db_execute(
            "UPDATE warranty_registrations
                SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
              WHERE id = ?",
            [$newStatus, $adminNotes, $adminId, $now, $now, $id]
        );

        $updated = array_merge($existing, [
            'status'      => $newStatus,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $adminId,
            'reviewed_at' => $now,
            'updated_at'  => $now,
        ]);

        Envelope::ok($updated);
    }

    /**
     * GET /api/v1/admin/warranty/registrations/{id}/invoice
     * Securely streams the invoice file to authorized admins.
     */
    public function adminInvoice(): never
    {
        $this->requireAdminAuth();
        $id = (string) $this->param('id');

        $row = db_fetch_one("SELECT invoice_path, reference_no FROM warranty_registrations WHERE id = ?", [$id]);
        if (!$row || empty($row['invoice_path'])) {
            http_response_code(404);
            die('Invoice file not found.');
        }

        $filePath = __BASEDIR__ . '/' . ltrim($row['invoice_path'], '/');
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('Invoice file does not exist on storage.');
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentTypes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ];
        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($row['reference_no']) . '_invoice.' . $ext . '"');
        header('Cache-Control: private, max-age=3600');

        readfile($filePath);
        exit;
    }
}
