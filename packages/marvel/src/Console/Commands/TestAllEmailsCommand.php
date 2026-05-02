<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Services\EmailService;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Mail\ContactAdmin;
use Marvel\Mail\CustomEmail;
use Marvel\Mail\TestEmail;
use Marvel\Mail\AdminCommissionRateUpdate;
use Marvel\Mail\VendorCommissionRateUpdate;
use Illuminate\Support\Facades\Mail;

class TestAllEmailsCommand extends Command
{
    protected $signature = 'email:test-all 
                            {--admin-email= : Admin email to send test emails to}
                            {--order-id= : Order ID for testing order emails}
                            {--product-id= : Product ID for testing product emails}';

    protected $description = 'Test all email methods and templates by sending them to admin email';

    protected $emailService;
    protected $adminEmail;
    protected $results = [];

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  📧 Testing All Email Methods and Templates');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Get admin email
        $this->adminEmail = $this->option('admin-email') ?: config('shop.admin_email');
        
        if (!$this->adminEmail) {
            $this->error('❌ Admin email not configured. Set SHOP_ADMIN_EMAIL in .env or use --admin-email option.');
            return 1;
        }

        $this->info("📬 Sending test emails to: {$this->adminEmail}");
        $this->newLine();

        try {
            // Test 1: Contact Admin
            $this->testContactAdmin();

            // Test 2: Test Email
            $this->testTestEmail();

            // Test 3: Custom Email
            $this->testCustomEmail();

            // Test 4: Order Placed Successfully
            $this->testOrderPlacedSuccessfully();

            // Test 5: New Order Received
            $this->testNewOrderReceived();

            // Test 6: Order Cancelled
            $this->testOrderCancelled();

            // Test 7: Order Delivered
            $this->testOrderDelivered();

            // Test 8: Order Status Changed
            $this->testOrderStatusChanged();

            // Test 9: Payment Successful
            $this->testPaymentSuccessful();

            // Test 10: Payment Failed
            $this->testPaymentFailed();

            // Test 11: Product Approved
            $this->testProductApproved();

            // Test 12: Product Rejected
            $this->testProductRejected();

            // Test 13: New Review Created
            $this->testNewReviewCreated();

            // Test 14: Question Answered
            $this->testQuestionAnswered();

            // Test 15: Refund Requested
            $this->testRefundRequested();

            // Test 16: Refund Update
            $this->testRefundUpdate();

            // Test 17: Store Notice
            $this->testStoreNotice();

            // Test 18: Product Under Review
            $this->testProductUnderReview();

            // Test 19: Admin Commission Rate Update
            $this->testAdminCommissionRateUpdate();

            // Test 20: Vendor Commission Rate Update
            $this->testVendorCommissionRateUpdate();

            // Test 21: Shop Approved
            $this->testShopApproved();

            // Test 22: Staff Added
            $this->testStaffAdded();

            // Display results
            $this->displayResults();

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    protected function testContactAdmin()
    {
        $this->info('📧 Testing: Contact Admin Email');
        try {
            $details = [
                'subject' => 'Test Contact Form',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'description' => 'This is a test contact form submission.'
            ];
            
            Mail::to($this->adminEmail)->send(new ContactAdmin($details));
            $this->recordResult('Contact Admin', true);
        } catch (\Exception $e) {
            $this->recordResult('Contact Admin', false, $e->getMessage());
        }
    }

    protected function testTestEmail()
    {
        $this->info('📧 Testing: Test Email');
        try {
            Mail::to($this->adminEmail)->send(new TestEmail());
            $this->recordResult('Test Email', true);
        } catch (\Exception $e) {
            $this->recordResult('Test Email', false, $e->getMessage());
        }
    }

    protected function testCustomEmail()
    {
        $this->info('📧 Testing: Custom Email');
        try {
            $user = User::first();
            if ($user) {
                Mail::to($this->adminEmail)->send(new CustomEmail(
                    $user,
                    'Test Custom Subject',
                    'This is a test custom email message.',
                    'emails.custom'
                ));
                $this->recordResult('Custom Email', true);
            } else {
                $this->recordResult('Custom Email', false, 'No users found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Custom Email', false, $e->getMessage());
        }
    }

    protected function testOrderPlacedSuccessfully()
    {
        $this->info('📧 Testing: Order Placed Successfully');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\OrderPlacedSuccessfully([
                    'order' => $order,
                    'language' => $order->language ?? 'ru'
                ]));
                $this->recordResult('Order Placed Successfully', true);
            } else {
                $this->recordResult('Order Placed Successfully', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Order Placed Successfully', false, $e->getMessage());
        }
    }

    protected function testNewOrderReceived()
    {
        $this->info('📧 Testing: New Order Received (Admin)');
        try {
            $order = $this->getTestOrder();
            if ($order) {
                $admin = User::whereHas('permissions', function($q) {
                    $q->where('name', 'super_admin');
                })->first();
                
                if ($admin) {
                    $admin->notify(new \Marvel\Notifications\NewOrderReceived($order, 'admin'));
                    $this->recordResult('New Order Received (Admin)', true);
                } else {
                    $this->recordResult('New Order Received (Admin)', false, 'No admin users found');
                }
            } else {
                $this->recordResult('New Order Received (Admin)', false, 'No orders found');
            }
        } catch (\Exception $e) {
            $this->recordResult('New Order Received (Admin)', false, $e->getMessage());
        }
    }

    protected function testOrderCancelled()
    {
        $this->info('📧 Testing: Order Cancelled');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\OrderCancelledNotification($order));
                $this->recordResult('Order Cancelled', true);
            } else {
                $this->recordResult('Order Cancelled', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Order Cancelled', false, $e->getMessage());
        }
    }

    protected function testOrderDelivered()
    {
        $this->info('📧 Testing: Order Delivered');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\OrderDeliveredNotification($order));
                $this->recordResult('Order Delivered', true);
            } else {
                $this->recordResult('Order Delivered', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Order Delivered', false, $e->getMessage());
        }
    }

    protected function testOrderStatusChanged()
    {
        $this->info('📧 Testing: Order Status Changed');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\OrderStatusChangedNotification($order));
                $this->recordResult('Order Status Changed', true);
            } else {
                $this->recordResult('Order Status Changed', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Order Status Changed', false, $e->getMessage());
        }
    }

    protected function testPaymentSuccessful()
    {
        $this->info('📧 Testing: Payment Successful');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\PaymentSuccessfulNotification($order));
                $this->recordResult('Payment Successful', true);
            } else {
                $this->recordResult('Payment Successful', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Payment Successful', false, $e->getMessage());
        }
    }

    protected function testPaymentFailed()
    {
        $this->info('📧 Testing: Payment Failed');
        try {
            $order = $this->getTestOrder();
            if ($order && $order->customer) {
                $order->customer->notify(new \Marvel\Notifications\PaymentFailedNotification($order));
                $this->recordResult('Payment Failed', true);
            } else {
                $this->recordResult('Payment Failed', false, 'No orders with customers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Payment Failed', false, $e->getMessage());
        }
    }

    protected function testProductApproved()
    {
        $this->info('📧 Testing: Product Approved');
        try {
            $product = $this->getTestProduct();
            if ($product && $product->shop && $product->shop->owner) {
                $product->shop->owner->notify(new \Marvel\Notifications\ProductApprovedNotification($product));
                $this->recordResult('Product Approved', true);
            } else {
                $this->recordResult('Product Approved', false, 'No products with shop owners found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Product Approved', false, $e->getMessage());
        }
    }

    protected function testProductRejected()
    {
        $this->info('📧 Testing: Product Rejected');
        try {
            $product = $this->getTestProduct();
            if ($product && $product->shop && $product->shop->owner) {
                $product->shop->owner->notify(new \Marvel\Notifications\ProductRejectedNotification($product));
                $this->recordResult('Product Rejected', true);
            } else {
                $this->recordResult('Product Rejected', false, 'No products with shop owners found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Product Rejected', false, $e->getMessage());
        }
    }

    protected function testNewReviewCreated()
    {
        $this->info('📧 Testing: New Review Created');
        try {
            $review = \Marvel\Database\Models\Review::with(['shop.owner', 'customer', 'product'])->first();
            
            if ($review) {
                // Тест для владельца магазина
                if ($review->shop && $review->shop->owner) {
                    $review->shop->owner->notify(new \App\Notifications\NewReviewCreated($review));
                }
                
                // Тест для админа
                $admins = \Marvel\Database\Models\User::whereHas('permissions', function($query) {
                    $query->where('name', 'super_admin');
                })->get();
                
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\NewReviewCreated($review));
                }
                
                $this->recordResult('New Review Created', true);
            } else {
                $this->recordResult('New Review Created', false, 'No reviews found');
            }
        } catch (\Exception $e) {
            $this->recordResult('New Review Created', false, $e->getMessage());
        }
    }

    protected function testQuestionAnswered()
    {
        $this->info('📧 Testing: Question Answered');
        try {
            $question = \Marvel\Database\Models\Question::with(['customer', 'product'])->whereNotNull('answer')->first();
            if ($question && $question->customer) {
                $question->customer->notify(new \App\Notifications\NotifyQuestionAnswered($question));
                $this->recordResult('Question Answered', true);
            } else {
                $this->recordResult('Question Answered', false, 'No questions with answers found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Question Answered', false, $e->getMessage());
        }
    }

    protected function testRefundRequested()
    {
        $this->info('📧 Testing: Refund Requested');
        try {
            $this->recordResult('Refund Requested', false, 'Not implemented in this test');
        } catch (\Exception $e) {
            $this->recordResult('Refund Requested', false, $e->getMessage());
        }
    }

    protected function testRefundUpdate()
    {
        $this->info('📧 Testing: Refund Update');
        try {
            $this->recordResult('Refund Update', false, 'Not implemented in this test');
        } catch (\Exception $e) {
            $this->recordResult('Refund Update', false, $e->getMessage());
        }
    }

    protected function testStoreNotice()
    {
        $this->info('📧 Testing: Store Notice');
        try {
            $this->recordResult('Store Notice', false, 'Not implemented in this test');
        } catch (\Exception $e) {
            $this->recordResult('Store Notice', false, $e->getMessage());
        }
    }

    protected function testProductUnderReview()
    {
        $this->info('📧 Testing: Product Under Review');
        try {
            $product = $this->getTestProduct();
            if ($product) {
                $admins = \Marvel\Database\Models\User::whereHas('permissions', function($query) {
                    $query->where('name', 'super_admin');
                })->get();

                foreach ($admins as $admin) {
                    $admin->notify(new \Marvel\Notifications\ProductUnderReviewNotification($product));
                }

                $this->recordResult('Product Under Review', true);
            } else {
                $this->recordResult('Product Under Review', false, 'No products found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Product Under Review', false, $e->getMessage());
        }
    }

    protected function testAdminCommissionRateUpdate()
    {
        $this->info('📧 Testing: Admin Commission Rate Update');
        try {
            $shop = Shop::first();
            $balance = \Marvel\Database\Models\Balance::first();
            if ($shop && $balance) {
                Mail::to($this->adminEmail)->send(new AdminCommissionRateUpdate($shop, $balance));
                $this->recordResult('Admin Commission Rate Update', true);
            } else {
                $this->recordResult('Admin Commission Rate Update', false, 'No shops or balances found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Admin Commission Rate Update', false, $e->getMessage());
        }
    }

    protected function testVendorCommissionRateUpdate()
    {
        $this->info('📧 Testing: Vendor Commission Rate Update');
        try {
            $shop = Shop::with('owner')->first();
            $balance = \Marvel\Database\Models\Balance::first();
            if ($shop && $shop->owner && $balance) {
                Mail::to($this->adminEmail)->send(new VendorCommissionRateUpdate($shop, $balance));
                $this->recordResult('Vendor Commission Rate Update', true);
            } else {
                $this->recordResult('Vendor Commission Rate Update', false, 'No shops with owners or balances found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Vendor Commission Rate Update', false, $e->getMessage());
        }
    }

    protected function testShopApproved()
    {
        $this->info('📧 Testing: Shop Approved');
        try {
            $shop = Shop::with('owner')->first();
            if ($shop && $shop->owner) {
                $shop->owner->notify(new \Marvel\Notifications\ShopApprovedNotification($shop));
                $this->recordResult('Shop Approved', true);
            } else {
                $this->recordResult('Shop Approved', false, 'No shops with owners found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Shop Approved', false, $e->getMessage());
        }
    }

    protected function testStaffAdded()
    {
        $this->info('📧 Testing: Staff Added');
        try {
            $shop = Shop::with('owner')->first();
            $staff = User::where('shop_id', '!=', null)->first();
            $admin = User::whereHas('permissions', function($query) {
                $query->where('name', 'super_admin');
            })->first();
            
            if ($shop && $shop->owner && $staff) {
                $shop->owner->notify(new \Marvel\Notifications\StaffAddedNotification($shop, $staff, $admin));
                $this->recordResult('Staff Added', true);
            } else {
                $this->recordResult('Staff Added', false, 'No shops with staff found');
            }
        } catch (\Exception $e) {
            $this->recordResult('Staff Added', false, $e->getMessage());
        }
    }

    protected function getTestOrder()
    {
        $orderId = $this->option('order-id');
        
        if ($orderId) {
            return Order::with(['customer', 'shop.owner'])->find($orderId);
        }
        
        return Order::with(['customer', 'shop.owner'])->first();
    }

    protected function getTestProduct()
    {
        $productId = $this->option('product-id');
        
        if ($productId) {
            return Product::with(['shop.owner'])->find($productId);
        }
        
        return Product::with(['shop.owner'])->first();
    }

    protected function recordResult($name, $success, $error = null)
    {
        $this->results[] = [
            'name' => $name,
            'success' => $success,
            'error' => $error
        ];
        
        if ($success) {
            $this->line("   ✅ {$name}");
        } else {
            $this->line("   ❌ {$name}" . ($error ? " - {$error}" : ''));
        }
        $this->newLine();
    }

    protected function displayResults()
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  📊 Test Results Summary');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($this->results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $total = count($this->results);
        $successRate = $total > 0 ? round(($successCount / $total) * 100, 1) : 0;

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tests', $total],
                ['Success', $successCount],
                ['Failed', $failCount],
                ['Success Rate', "{$successRate}%"]
            ]
        );

        $this->newLine();
        
        if ($failCount > 0) {
            $this->warn('⚠️  Failed Tests:');
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    $this->line("   ❌ {$result['name']}" . ($result['error'] ? " - {$result['error']}" : ''));
                }
            }
        }

        $this->newLine();
        $this->info("📬 Check your inbox at: {$this->adminEmail}");
        $this->info("📧 Total emails sent: {$successCount}");
        
        if ($successCount === $total && $total > 0) {
            $this->info('✅ All tests passed successfully!');
        } elseif ($successCount > 0) {
            $this->warn("⚠️  {$successCount} tests passed, {$failCount} failed");
        } else {
            $this->error("❌ All tests failed");
        }
    }
}
