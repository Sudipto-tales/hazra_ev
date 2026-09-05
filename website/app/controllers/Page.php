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

    public function blog()
    {
        return $this->respond('/app/page/blog.php');
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

    public function admin()
    {
        return $this->respond('/app/page/admin.php');
    }
}
