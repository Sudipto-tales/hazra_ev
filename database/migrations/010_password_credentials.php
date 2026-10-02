<?php

/**
 * One flag, so a generated credential can announce itself.
 *
 * An admin creating an employee may either type a password or let the server
 * generate one. A generated password is a first-login credential, not a secret
 * — it is read off a screen and passed along — so the account is marked and the
 * app can say so until the employee replaces it. Nothing enforces the change;
 * the flag is advisory.
 *
 * `users` predates this, so it is an ALTER rather than a column in 001. See
 * Migration::addColumn — neither driver takes `ADD COLUMN IF NOT EXISTS`
 * portably, so it introspects and returns early when the column already exists.
 */
class PasswordCredentials extends Migration
{
    public function up()
    {
        $this->addColumn('users', 'must_change_password', '{bool} NOT NULL DEFAULT 0');
    }

    public function down()
    {
        // Deliberately empty. Dropping the column would silently un-flag every
        // account still carrying a generated password, and both drivers rewrite
        // the whole table to do it. Rolling back leaves an unused column.
    }
}
