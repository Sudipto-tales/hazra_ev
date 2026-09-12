<?php

/**
 * Migration 014: Optimized content tables and featured product fields.
 *
 * Adds:
 * - products: is_featured, featured_order, slug, hero_image
 * - posts (blogs & news unified)
 * - gallery_items
 * - jobs
 * - job_applications
 */
class OptimizedContentTables extends Migration
{
    public function up()
    {
        // Add featured & hero fields to products table
        $this->addColumn('products', 'is_featured', '{int} NOT NULL DEFAULT 0');
        $this->addColumn('products', 'featured_order', '{int} NOT NULL DEFAULT 0');
        $this->addColumn('products', 'slug', '{str} NOT NULL DEFAULT \'\'');
        $this->addColumn('products', 'hero_image', '{text}');

        // Execute schema for posts, gallery, jobs, job_applications
        $this->exec("
            CREATE TABLE IF NOT EXISTS posts (
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
            CREATE INDEX IF NOT EXISTS idx_posts_type_status ON posts (type, status);

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

            CREATE TABLE IF NOT EXISTS gallery_items (
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

            CREATE TABLE IF NOT EXISTS jobs (
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

            CREATE TABLE IF NOT EXISTS job_applications (
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
        $this->drop(['job_applications', 'admin_job_applications', 'jobs', 'admin_jobs', 'gallery_items', 'admin_gallery_items', 'posts', 'admin_posts']);
    }
}
