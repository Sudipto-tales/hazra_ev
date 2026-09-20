<?php
class Page extends BaseController
{
    public function index()
    {
        return $this->respond('/app/page/index.php');
    }

    public function ourStory()
    {
        return $this->respond('/app/page/our-story.php');
    }

    public function career()
    {
        return $this->respond('/app/page/career.php');
    }

    public function products()
    {
        return $this->respond('/app/page/products.php');
    }

    public function productDetail()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function chalo1000v2()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function chaloNeo()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function chaloSmartEco()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function chaloSmartPlus()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function chaloSmartPro()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function nja7()
    {
        return $this->respond('/app/page/product-detail.php');
    }

    public function batteryUse()
    {
        return $this->respond('/app/page/battery-use.php');
    }

    public function evFuture()
    {
        return $this->respond('/app/page/ev-future.php');
    }

    public function contact()
    {
        return $this->respond('/app/page/contact.php');
    }

    public function gallery()
    {
        return $this->respond('/app/page/gallery.php');
    }

    public function blog()
    {
        return $this->respond('/app/page/blog.php');
    }

    public function blogSingle()
    {
        return $this->respond('/app/page/blog-single.php');
    }

    public function newsSingle()
    {
        return $this->respond('/app/page/news-single.php');
    }

    public function news()
    {
        return $this->respond('/app/page/news.php');
    }

    public function contest()
    {
        return $this->respond('/app/page/contest.php');
    }

    public function dealerLocator()
    {
        return $this->respond('/app/page/dealer-locator.php');
    }

    public function becomeDealer()
    {
        return $this->respond('/app/page/become-a-dealer.php');
    }

    public function dealershipEnquiry()
    {
        return $this->respond('/app/page/dealership-enquiry.php');
    }

    public function warrantyFree()
    {
        return $this->respond('/app/page/warranty-free.php');
    }

    public function warrantyPaid()
    {
        return $this->respond('/app/page/warranty-paid.php');
    }

    private function guardAdmin()
    {
        Csrf::ensureSession();

        if (empty($_SESSION['admin_logged_in']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: ' . base_url('/admin/login'));
            exit;
        }
    }

    public function admin()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/dashboard.php');
    }

    public function adminLogin()
    {
        return $this->respond('/app/page/admin/login.php');
    }

    public function adminLogout()
    {
        return $this->respond('/app/page/admin/logout.php');
    }

    public function adminDashboard()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/dashboard.php');
    }

    public function adminProducts()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/products.php');
    }

    public function adminProductForm()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/product-form.php');
    }

    public function adminBlogs()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/blogs.php');
    }

    public function adminBlogForm()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/blog-form.php');
    }

    public function adminNews()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/news.php');
    }

    public function adminNewsForm()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/news-form.php');
    }

    public function adminGallery()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/gallery.php');
    }

    public function adminJobs()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/jobs.php');
    }

    public function adminJobForm()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/job-form.php');
    }

    public function adminCareerApps()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/career-apps.php');
    }

    public function adminTestDrive()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/test-drive.php');
    }

    public function adminDealership()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/dealership.php');
    }

    public function adminContact()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/contact.php');
    }

    public function adminSettings()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/settings.php');
    }

    public function adminProfile()
    {
        $this->guardAdmin();
        return $this->respond('/app/page/admin/profile.php');
    }

    public function sitemap()
    {
        header('Content-Type: application/xml; charset=utf-8');
        
        $baseUrl = rtrim(base_url('/'), '/');
        $now = date('c');
        
        $urls = [
            ['loc' => $baseUrl, 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $baseUrl . 'our-story', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $baseUrl . 'products', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $baseUrl . 'blog', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $baseUrl . 'news', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $baseUrl . 'contact', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . 'dealer-locator', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl . 'become-a-dealer', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];
        
        // Blog posts
        $blogPosts = db_fetch_all("SELECT slug, updated_at FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY published_at DESC");
        foreach ($blogPosts as $post) {
            $urls[] = [
                'loc' => $baseUrl . 'blog/' . $post['slug'],
                'lastmod' => date('c', strtotime($post['updated_at'])),
                'changefreq' => 'monthly',
                'priority' => '0.8'
            ];
        }
        
        // News posts
        $newsPosts = db_fetch_all("SELECT slug, updated_at FROM posts WHERE type = 'news' AND status = 'published' ORDER BY published_at DESC");
        foreach ($newsPosts as $post) {
            $urls[] = [
                'loc' => $baseUrl . 'news/' . $post['slug'],
                'lastmod' => date('c', strtotime($post['updated_at'])),
                'changefreq' => 'monthly',
                'priority' => '0.8'
            ];
        }
        
        // Product detail pages
        $products = db_fetch_all("SELECT slug, updated_at FROM products WHERE status = 'active' ORDER BY created_at DESC");
        foreach ($products as $product) {
            $urls[] = [
                'loc' => $baseUrl . 'product-detail/' . $product['slug'],
                'lastmod' => date('c', strtotime($product['updated_at'])),
                'changefreq' => 'monthly',
                'priority' => '0.8'
            ];
        }
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            echo '<url>';
            echo '<loc>' . htmlspecialchars($url['loc']) . '</loc>';
            echo '<lastmod>' . $url['lastmod'] . '</lastmod>';
            echo '<changefreq>' . $url['changefreq'] . '</changefreq>';
            echo '<priority>' . $url['priority'] . '</priority>';
            echo '</url>';
        }
        echo '</urlset>';
        exit;
    }
}
