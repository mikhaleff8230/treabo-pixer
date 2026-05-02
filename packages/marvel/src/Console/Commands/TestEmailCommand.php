<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Services\EmailService;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test 
                            {--type=config : Test type (config, order, product, payment, bulk)}
                            {--email= : Email address to send test to}
                            {--order-id= : Order ID for order tests}
                            {--product-id= : Product ID for product tests}
                            {--user-ids= : Comma-separated user IDs for bulk test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email system functionality';

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $email = $this->option('email');

        $this->info("Testing email system: {$type}");

        switch ($type) {
            case 'config':
                $this->testEmailConfiguration();
                break;
            case 'order':
                $this->testOrderEmails();
                break;
            case 'product':
                $this->testProductEmails();
                break;
            case 'payment':
                $this->testPaymentEmails();
                break;
            case 'bulk':
                $this->testBulkEmail();
                break;
            default:
                $this->error('Invalid test type. Available: config, order, product, payment, bulk');
                return 1;
        }

        return 0;
    }

    protected function testEmailConfiguration()
    {
        $this->info('Testing email configuration...');
        
        $config = $this->emailService->isEmailConfigurationValid();
        
        if ($config['is_valid']) {
            $this->info('✓ Email configuration is valid');
            $this->table(['Setting', 'Value'], [
                ['From Address', $config['from_address']],
                ['From Name', $config['from_name']],
                ['Admin Email', $config['admin_email']],
                ['Mail Driver', $config['mail_driver']],
            ]);
        } else {
            $this->error('✗ Email configuration is invalid');
            $this->table(['Setting', 'Value', 'Status'], [
                ['From Address', $config['from_address'], $config['from_address'] ? '✓' : '✗'],
                ['From Name', $config['from_name'], $config['from_name'] ? '✓' : '✗'],
                ['Admin Email', $config['admin_email'], $config['admin_email'] ? '✓' : '✗'],
                ['Mail Driver', $config['mail_driver'], $config['mail_driver'] ? '✓' : '✗'],
            ]);
        }

        // Test sending actual email
        $testEmail = $this->option('email') ?: $config['admin_email'];
        if ($testEmail) {
            $this->info("Sending test email to: {$testEmail}");
            if ($this->emailService->sendTestEmail($testEmail)) {
                $this->info('✓ Test email sent successfully');
            } else {
                $this->error('✗ Failed to send test email');
            }
        }
    }

    protected function testOrderEmails()
    {
        $orderId = $this->option('order-id');
        
        if (!$orderId) {
            $this->error('Order ID is required for order email tests');
            return;
        }

        $order = Order::with(['customer', 'shop.owner'])->find($orderId);
        if (!$order) {
            $this->error("Order with ID {$orderId} not found");
            return;
        }

        $this->info("Testing order emails for order #{$order->tracking_number}");

        // Test customer notification
        if ($order->customer) {
            $this->info('Sending order notification to customer...');
            $result = $this->emailService->sendOrderNotificationToCustomer($order, 'created');
            $this->line($result ? '✓ Customer notification sent' : '✗ Failed to send customer notification');
        } else {
            $this->warn('Order has no customer, skipping customer notification');
        }

        // Test store owner notification
        if ($order->shop && $order->shop->owner) {
            $this->info('Sending order notification to store owner...');
            $result = $this->emailService->sendOrderNotificationToStoreOwner($order, 'created');
            $this->line($result ? '✓ Store owner notification sent' : '✗ Failed to send store owner notification');
        } else {
            $this->warn('Order has no shop owner, skipping store owner notification');
        }

        // Test admin notification
        $this->info('Sending order notification to admin...');
        $result = $this->emailService->sendOrderNotificationToAdmin($order, 'created');
        $this->line($result ? '✓ Admin notification sent' : '✗ Failed to send admin notification');
    }

    protected function testProductEmails()
    {
        $productId = $this->option('product-id');
        
        if (!$productId) {
            $this->error('Product ID is required for product email tests');
            return;
        }

        $product = Product::with(['shop.owner'])->find($productId);
        if (!$product) {
            $this->error("Product with ID {$productId} not found");
            return;
        }

        $this->info("Testing product emails for product: {$product->name}");

        // Test vendor notification
        if ($product->shop && $product->shop->owner) {
            $this->info('Sending product notification to vendor...');
            $result = $this->emailService->sendProductNotification($product, 'approved', 'vendor');
            $this->line($result ? '✓ Vendor notification sent' : '✗ Failed to send vendor notification');
        } else {
            $this->warn('Product has no vendor, skipping vendor notification');
        }

        // Test admin notification
        $this->info('Sending product notification to admin...');
        $result = $this->emailService->sendProductNotification($product, 'approved', 'admin');
        $this->line($result ? '✓ Admin notification sent' : '✗ Failed to send admin notification');
    }

    protected function testPaymentEmails()
    {
        $orderId = $this->option('order-id');
        
        if (!$orderId) {
            $this->error('Order ID is required for payment email tests');
            return;
        }

        $order = Order::with(['customer', 'shop.owner'])->find($orderId);
        if (!$order) {
            $this->error("Order with ID {$orderId} not found");
            return;
        }

        $this->info("Testing payment emails for order #{$order->tracking_number}");

        // Test customer notification
        if ($order->customer) {
            $this->info('Sending payment notification to customer...');
            $result = $this->emailService->sendPaymentNotification($order, 'successful', 'customer');
            $this->line($result ? '✓ Customer notification sent' : '✗ Failed to send customer notification');
        }

        // Test store owner notification
        if ($order->shop && $order->shop->owner) {
            $this->info('Sending payment notification to store owner...');
            $result = $this->emailService->sendPaymentNotification($order, 'successful', 'vendor');
            $this->line($result ? '✓ Store owner notification sent' : '✗ Failed to send store owner notification');
        }

        // Test admin notification
        $this->info('Sending payment notification to admin...');
        $result = $this->emailService->sendPaymentNotification($order, 'successful', 'admin');
        $this->line($result ? '✓ Admin notification sent' : '✗ Failed to send admin notification');
    }

    protected function testBulkEmail()
    {
        $userIds = $this->option('user-ids');
        
        if (!$userIds) {
            $this->error('User IDs are required for bulk email tests');
            return;
        }

        $userIds = explode(',', $userIds);
        $this->info("Testing bulk email to " . count($userIds) . " users...");

        $result = $this->emailService->sendBulkEmail(
            $userIds,
            'Test Bulk Email',
            'This is a test bulk email message.',
            'emails.custom'
        );

        $this->line("✓ Bulk email sent to {$result} users");
    }
}


