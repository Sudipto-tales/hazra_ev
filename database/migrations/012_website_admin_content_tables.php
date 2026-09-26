<?php
/**
 * Migration 012: Create admin content tables.
 *
 * Tables (all IF NOT EXISTS):
 *   - admin_posts      (blogs & news unified, differentiated by `type`)
 *   - admin_gallery_items
 *   - admin_jobs
 *   - admin_job_applications
 */
class WebsiteAdminContentTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS admin_posts (
                id           {uuid} PRIMARY KEY,
                type         {str:32} NOT NULL DEFAULT 'blog',
                title        {str} NOT NULL,
                slug         {str} NOT NULL DEFAULT '',
                excerpt      {text},
                content      {text} NOT NULL DEFAULT '',
                cover_image  {text},
                author       {str},
                published_at {ts},
                status       {str:32} NOT NULL DEFAULT 'draft',
                created_at   {ts} NOT NULL,
                updated_at   {ts} NOT NULL
            ) {opts};

            CREATE TABLE IF NOT EXISTS admin_gallery_items (
                id          {uuid} PRIMARY KEY,
                title       {str} NOT NULL,
                image_url   {text} NOT NULL DEFAULT '',
                image_path  {text} NOT NULL DEFAULT '',
                alt         {str} NOT NULL DEFAULT '',
                caption     {text},
                order_num   {int} NOT NULL DEFAULT 0,
                status      {str:32} NOT NULL DEFAULT 'active',
                created_at  {ts} NOT NULL,
                updated_at  {ts} NOT NULL
            ) {opts};

            CREATE TABLE IF NOT EXISTS admin_jobs (
                id           {uuid} PRIMARY KEY,
                title        {str} NOT NULL,
                location     {str},
                type         {str:32} NOT NULL DEFAULT 'full-time',
                description  {text} NOT NULL DEFAULT '',
                requirements {text},
                positions    {int} NOT NULL DEFAULT 1,
                apply_email  {str},
                expires_at   {ts},
                status       {str:32} NOT NULL DEFAULT 'open',
                created_at   {ts} NOT NULL,
                updated_at   {ts} NOT NULL
            ) {opts};

            CREATE TABLE IF NOT EXISTS admin_job_applications (
                id             {uuid} PRIMARY KEY,
                job_id         {uuid} NOT NULL,
                applicant_name {str} NOT NULL DEFAULT '',
                name           {str} NOT NULL DEFAULT '',
                phone          {str:64},
                email          {str} NOT NULL DEFAULT '',
                resume_url     {text},
                resume_path    {text},
                message        {text},
                cover_letter   {text},
                status         {str:32} NOT NULL DEFAULT 'new',
                created_at     {ts} NOT NULL,
                updated_at     {ts} NOT NULL
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop(['admin_job_applications', 'admin_jobs', 'admin_gallery_items', 'admin_posts']);
    }
}
