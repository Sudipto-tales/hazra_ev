<?php

class AccountDeletionAdminNote extends Migration
{
    public function up()
    {
        $this->addColumn('account_deletion_requests', 'admin_note', '{text}');
    }
}
