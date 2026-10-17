<?php
// Compatibility for existing callers; challenge handling lives in OtpService.
require_once __DIR__ . "/OtpService.php";
class SmsService extends OtpService {}
