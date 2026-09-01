<?php

require_once __DIR__ . '/../core/RouteProvider.php';

class ViewRouteProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            'default' => ['Welcome', 'index'],
            'index' => ['Page', 'index'],
            'our-story' => ['Page', 'ourStory'],
            'career' => ['Page', 'career'],
            'chalo-1000-v2' => ['Page', 'chalo1000v2'],
            'chalo-neo' => ['Page', 'chaloNeo'],
            'chalo-smart-eco' => ['Page', 'chaloSmartEco'],
            'chalo-smart-plus' => ['Page', 'chaloSmartPlus'],
            'chalo-smart-pro' => ['Page', 'chaloSmartPro'],
            'nja-7' => ['Page', 'nja7'],
            'battery-use' => ['Page', 'batteryUse'],
            'ev-future' => ['Page', 'evFuture'],
            'blog' => ['Page', 'blog'],
            'contest' => ['Page', 'contest'],
            'dealer-locator' => ['Page', 'dealerLocator'],
            'become-a-dealer' => ['Page', 'becomeDealer'],
            'dealership-enquiry' => ['Page', 'dealershipEnquiry'],
            'warranty-free' => ['Page', 'warrantyFree'],
            'warranty-paid' => ['Page', 'warrantyPaid'],
        ];
    }
}

return ViewRouteProvider::routes();
