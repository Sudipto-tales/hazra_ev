<?php

/**
 * Makes a LIVE OTP revealable to an admin, and only while it is live.
 *
 * otp_challenges has always stored a bcrypt hash, which verifies a code but
 * cannot show one. Support needs to see the code a customer says never arrived,
 * so it is also kept as AES-256-GCM ciphertext (core/Secrets.php, OTP purpose)
 * beside the hash — never instead of it: verification still goes through
 * password_verify, so a missing or unreadable cipher cannot weaken the check.
 *
 * The cipher is wiped the moment the challenge is verified, consumed, expired or
 * superseded, and code_cipher_expires_at is a second, independent deadline so a
 * row that somehow escapes those paths still stops being revealable. The reveal
 * endpoint is admin-only, off by default, and writes an otp_reveal_audit row on
 * every single reveal — the audit is the reason the feature is defensible.
 */
class OtpCodeCipher extends Migration
{
    public function up()
    {
        $this->addColumn('otp_challenges', 'code_cipher', '{text}');
        $this->addColumn('otp_challenges', 'code_cipher_expires_at', '{ts}');

        $this->exec("
            CREATE TABLE IF NOT EXISTS otp_reveal_audit (
                id             {uuid} PRIMARY KEY,
                challenge_id   {uuid} NOT NULL,
                message_id     {uuid},
                channel        {str:20},
                admin_user_id  {uuid},
                admin_email    {email},
                ip             {str:64},
                user_agent     {str:512},
                revealed_at    {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_otp_reveal_challenge ON otp_reveal_audit(challenge_id);
            CREATE INDEX IF NOT EXISTS idx_otp_reveal_admin ON otp_reveal_audit(admin_user_id, revealed_at);
            CREATE INDEX IF NOT EXISTS idx_otp_cipher_expiry ON otp_challenges(code_cipher_expires_at);
        ");
    }

    public function down()
    {
        $this->drop(['otp_reveal_audit']);
    }
}
