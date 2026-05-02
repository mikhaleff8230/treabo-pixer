<?php

namespace Marvel\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class SettingsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // run your app seeder
        DB::table('settings')->insert([
            'options' => json_encode([
                "seo" => [
                    "ogImage" => null,
                    "ogTitle" => null,
                    "metaTags" => null,
                    "metaTitle" => null,
                    "canonicalUrl" => null,
                    "ogDescription" => null,
                    "twitterHandle" => null,
                    "metaDescription" => null,
                    "twitterCardType" => null
                ],
                "logo" => [
                    "id" => 935,
                    "original" => "https://pixarlaravel.s3.ap-southeast-1.amazonaws.com/931/pixer_dark.png",
                    "thumbnail" => "https://pixarlaravel.s3.ap-southeast-1.amazonaws.com/931/conversions/pixer_dark-thumbnail.jpg"
                ],

                "dark_logo" => [
                    "id" => 936,
                    "original" => "https://pixarlaravel.s3.ap-southeast-1.amazonaws.com/932/pixer_light.png",
                    "thumbnail" => "https://pixarlaravel.s3.ap-southeast-1.amazonaws.com/932/conversions/pixer_light-thumbnail.jpg"
                ],
                "siteTitle" => "Pixer",
                "siteSubtitle" => "Your next ecommerce",
                // "useOtp" => false,
                "currency" => "USD",
                "taxClass" => "1",
                "signupPoints" => 100,
                "useGoogleMap" => false,
                "siteSubtitle" => "Your next ecommerce",
                // "shippingClass" => "1",
                // "contactDetails" => [
                //     "contact" => "+161649646591, +5454645431",
                //     "socials" => [],
                //     "website" => "https://redq.io/",
                //     "location" => [
                //         "lat" => 48.2016556,
                //         "lng" => 16.3378535,
                //         "zip" => "1070",
                //         "city" => "Wien",
                //         "state" => "Wien",
                //         "country" => "Austria",
                //         "formattedAddress" => "Urban-Loritz-Platz, 1070 Wien, Austria"
                //     ]
                // ],
                "paymentGateway" => [
                    [
                        "name" => "stripe",
                        "title" => "Stripe"
                    ]
                ],
                "currencyOptions" => [
                    "formation" => "en-US",
                    "fractions" => 2
                ],
                "isProductReview" => false,
                "useEnableGateway" => true,
                "minimumOrderAmount" => null,
                "useMustVerifyEmail" => false,
                "maximumQuestionLimit" => 5,
                "currencyToWalletRatio" => 3,
                "defaultPaymentGateway" => "YooKassa",
                "StripeCardOnly" => true,
                // "guestCheckout" => true,
                ...$this->getSmsEmailEvents(),
                "server_info" => server_environment_info(),
                "useAi"         => false,
                "defaultAi" => "openai",
                // "maxShopDistance" => 1000,
            ]),
            "language" => "en",
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now(),
        ]);
    }

    /**
     * The function returns an array of SMS and email events with their corresponding recipients and
     * event types.
     * 
     * @return array An array containing events for SMS and email notifications for different user
     * roles (admin, vendor, and customer) related to order status changes, refunds, payments, creating
     * questions, creating reviews, and answering questions.
     */
    private function getSmsEmailEvents(): array
    {
        return [
            "smsEvent" => [
                "admin" => [
                    "statusChangeOrder" => true,
                    "refundOrder" => true,
                    "paymentOrder" => true
                ],
                "vendor" => [
                    "statusChangeOrder" => true,
                    "paymentOrder" => true,
                    "refundOrder" => true
                ],
                "customer" => [
                    "statusChangeOrder" => true,
                    "refundOrder" => true,
                    "paymentOrder" => true
                ]
            ],
            "emailEvent" => [
                "admin" => [
                    "statusChangeOrder" => true,
                    "refundOrder" => true,
                    "paymentOrder" => true
                ],
                "vendor" => [
                    "createQuestion" => true,
                    "statusChangeOrder" => true,
                    "refundOrder" => true,
                    "paymentOrder" => true,
                    "createReview" => true
                ],
                "customer" => [
                    "statusChangeOrder" => true,
                    "refundOrder" => true,
                    "paymentOrder" => true,
                    "answerQuestion" => true
                ]
            ],
        ];
    }
}
