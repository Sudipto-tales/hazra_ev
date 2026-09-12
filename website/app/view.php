<?php

require_once __DIR__ . '/../core/RouteProvider.php';

class ViewRouteProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            'default' => ['Page', 'index'],
            'index' => ['Page', 'index'],
            'index.php' => ['Page', 'index'],

            'our-story' => ['Page', 'ourStory'],
            'our-story.php' => ['Page', 'ourStory'],

            'career' => ['Page', 'career'],
            'career.php' => ['Page', 'career'],

            'products' => ['Page', 'products'],
            'products.php' => ['Page', 'products'],

            'product-detail' => ['Page', 'productDetail'],
            'product-detail.php' => ['Page', 'productDetail'],

            'chalo-1000-v2' => ['Page', 'productDetail'],
            'chalo-1000-v2.php' => ['Page', 'productDetail'],

            'chalo-neo' => ['Page', 'productDetail'],
            'chalo-neo.php' => ['Page', 'productDetail'],

            'chalo-smart-eco' => ['Page', 'productDetail'],
            'chalo-smart-eco.php' => ['Page', 'productDetail'],

            'chalo-smart-plus' => ['Page', 'productDetail'],
            'chalo-smart-plus.php' => ['Page', 'productDetail'],

            'chalo-smart-pro' => ['Page', 'productDetail'],
            'chalo-smart-pro.php' => ['Page', 'productDetail'],

            'nja-7' => ['Page', 'productDetail'],
            'nja-7.php' => ['Page', 'productDetail'],

            'battery-use' => ['Page', 'batteryUse'],
            'battery-use.php' => ['Page', 'batteryUse'],

            'ev-future' => ['Page', 'evFuture'],
            'ev-future.php' => ['Page', 'evFuture'],

            'contact' => ['Page', 'contact'],
            'contact.php' => ['Page', 'contact'],

            'blog' => ['Page', 'blog'],
            'blog.php' => ['Page', 'blog'],

            'blog-single' => ['Page', 'blogSingle'],
            'blog-single.php' => ['Page', 'blogSingle'],

            'news-single' => ['Page', 'newsSingle'],
            'news-single.php' => ['Page', 'newsSingle'],

            'contest' => ['Page', 'contest'],
            'contest.php' => ['Page', 'contest'],

            'dealer-locator' => ['Page', 'dealerLocator'],
            'dealer-locator.php' => ['Page', 'dealerLocator'],

            'become-a-dealer' => ['Page', 'becomeDealer'],
            'become-a-dealer.php' => ['Page', 'becomeDealer'],

            'dealership-enquiry' => ['Page', 'dealershipEnquiry'],
            'dealership-enquiry.php' => ['Page', 'dealershipEnquiry'],

            'warranty-free' => ['Page', 'warrantyFree'],
            'warranty-free.php' => ['Page', 'warrantyFree'],

            'warranty-paid' => ['Page', 'warrantyPaid'],
            'warranty-paid.php' => ['Page', 'warrantyPaid'],

            'admin' => ['Page', 'admin'],
            'admin.php' => ['Page', 'admin'],
        ];
    }
}

return ViewRouteProvider::routes();
