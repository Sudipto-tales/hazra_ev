<?php

/**
 * Migration 020: Warranty registration system
 * - warranty_registrations: Customer warranty submissions (free & paid extension)
 * - otp_challenges: Mobile verification OTP challenges & issued session tokens
 */
class WarrantyRegistrations extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS warranty_registrations (
                id                     {uuid} PRIMARY KEY,
                type                   {str:16} NOT NULL,
                status                 {str:32} NOT NULL DEFAULT 'pending',
                reference_no           {str:64} NOT NULL UNIQUE,
                customer_name          {str} NOT NULL,
                email                  {email} NOT NULL,
                mobile                 {str:16} NOT NULL,
                mobile_verified_at     {ts},
                vehicle_model          {str} NOT NULL DEFAULT '',
                chassis_no             {str:64} NOT NULL,
                motor_no               {str:64} NOT NULL,
                controller_no          {str:64},
                battery_type           {str:32} NOT NULL DEFAULT 'na',
                battery_serial         {str:64},
                battery_volt           {str:16},
                charger_serial         {str:64},
                state                  {str:64} NOT NULL DEFAULT '',
                district               {str:64} NOT NULL DEFAULT '',
                dealer_name            {str} NOT NULL DEFAULT '',
                dealer_email           {email},
                purchase_date          {date} NOT NULL,
                invoice_path           {str:512} NOT NULL,
                plan                   {str:32},
                amount_paise           {int},
                payment_status         {str:32} DEFAULT 'unpaid',
                parent_registration_id {uuid},
                admin_notes            {text},
                reviewed_by            {uuid},
                reviewed_at            {ts},
                org_id                 {uuid},
                ip_address             {str:64},
                user_agent             {str:512},
                created_at             {ts} NOT NULL,
                updated_at             {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_warranty_reg_type_status ON warranty_registrations (type, status);
            CREATE INDEX IF NOT EXISTS idx_warranty_reg_chassis ON warranty_registrations (chassis_no);
            CREATE INDEX IF NOT EXISTS idx_warranty_reg_motor ON warranty_registrations (motor_no);
            CREATE INDEX IF NOT EXISTS idx_warranty_reg_mobile ON warranty_registrations (mobile);
            CREATE INDEX IF NOT EXISTS idx_warranty_reg_created ON warranty_registrations (created_at);

            CREATE TABLE IF NOT EXISTS otp_challenges (
                id            {uuid} PRIMARY KEY,
                mobile        {str:16} NOT NULL,
                purpose       {str:32} NOT NULL,
                code_hash     {str} NOT NULL,
                expires_at    {ts} NOT NULL,
                attempts      {int} NOT NULL DEFAULT 0,
                verified_at   {ts},
                session_token {str:64},
                consumed_at   {ts},
                ip_address    {str:64},
                created_at    {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_otp_mobile_purpose ON otp_challenges (mobile, purpose);
            CREATE INDEX IF NOT EXISTS idx_otp_session_token ON otp_challenges (session_token);
        ");
    }

    public function down()
    {
        $this->drop(['warranty_registrations', 'otp_challenges']);
    }
}
