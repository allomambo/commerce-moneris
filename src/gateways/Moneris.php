<?php

namespace allomambo\CommerceMoneris\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\models\PaymentSource;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use yii\base\NotSupportedException;

/**
 * Moneris Payment Gateway
 *
 * @property-read null|string $settingsHtml
 * 
 * @phpstan-type MonerisTransaction \mpgTransaction
 * @phpstan-type MonerisRequest \mpgRequest
 * @phpstan-type MonerisHttpsPost \mpgHttpsPost
 */
class Moneris extends Gateway
{
    /**
     * @var string|null Store ID from Moneris
     */
    public ?string $storeId = null;

    /**
     * @var string|null API Token from Moneris
     */
    public ?string $apiToken = null;

    /**
     * @var string Environment: 'production' or 'staging'
     */
    public string $environment = 'staging';

    /**
     * @var bool Enable AVS (Address Verification System)
     */
    public bool $enableAvs = false;

    /**
     * @var bool Enable CVD (Card Verification Digit)
     */
    public bool $enableCvd = false;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('moneris-gateway', 'Moneris');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        
        $html = $view->renderTemplate('moneris-gateway/gateways/settings', [
            'gateway' => $this,
        ]);
        
        $view->setTemplateMode($oldMode);
        
        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): string
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
            'order' => $params['order'] ?? null,
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('moneris-gateway/gateways/paymentForm', $params);
        $view->setTemplateMode($oldMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new \allomambo\CommerceMoneris\models\MonerisPaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        $response = Craft::$app->getResponse();
        $response->statusCode = 400;
        $response->data = ['error' => 'Webhooks are not supported by Moneris gateway.'];
        return $response;
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createRequestResponse($transaction, $form, 'preauth');
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        $this->includeMonerisLibrary();
        $moneris = $this->getMonerisInstance();

        // Format amount: Craft Commerce stores amounts in cents, Moneris expects cents as string with 2 decimals
        $compAmount = number_format($transaction->paymentAmount, 2, '.', '');

        $params = [
            'type' => 'completion',
            'txn_number' => $reference,
            'comp_amount' => $compAmount,
            'order_id' => $transaction->order->number,
        ];

        /** @var object $mpgTxn */
        $mpgTxn = new \mpgTransaction($params);
        /** @var object $mpgRequest */
        $mpgRequest = new \mpgRequest($mpgTxn);
        // Set test mode based on environment
        $mpgRequest->setTestMode($moneris->isTestMode());
        /** @var object $mpgHttpPost */
        $mpgHttpPost = new \mpgHttpsPost($moneris->getStoreId(), $moneris->getApiToken(), $mpgRequest);
        $mpgResponse = $mpgHttpPost->getMpgResponse();

        return new \allomambo\CommerceMoneris\models\MonerisRequestResponse($mpgResponse, $transaction);
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createRequestResponse($transaction, $form, 'purchase');
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $this->includeMonerisLibrary();
        $moneris = $this->getMonerisInstance();

        // Get the parent transaction reference (the original successful transaction)
        $parentTransaction = null;
        if ($transaction->parentId) {
            $parentTransaction = \craft\commerce\Plugin::getInstance()->getTransactions()->getTransactionById($transaction->parentId);
        }

        // If no parent, try to find the original successful transaction
        if (!$parentTransaction || !$parentTransaction->reference) {
            $order = $transaction->order;
            $transactions = $order->getTransactions();
            foreach ($transactions as $txn) {
                if (
                    in_array($txn->type, [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE])
                    && $txn->status === TransactionRecord::STATUS_SUCCESS
                    && !empty($txn->reference)
                ) {
                    $parentTransaction = $txn;
                    break;
                }
            }
        }

        if (!$parentTransaction || empty($parentTransaction->reference)) {
            throw new \Exception('Cannot process refund: Original transaction reference not found.');
        }

        // Format amount: Craft Commerce stores amounts in cents, Moneris expects cents as string with 2 decimals
        $amount = number_format($transaction->paymentAmount, 2, '.', '');

        // Use the original transaction's order_id (required by Moneris for refunds)
        $originalOrderId = $parentTransaction->order->number ?? $transaction->order->number;

        $params = [
            'type' => 'refund',
            'txn_number' => $parentTransaction->reference,
            'order_id' => $originalOrderId,
            'amount' => $amount,
            'crypt_type' => '7', // SSL merchant (required for refunds)
        ];

        Craft::info('Processing refund: ' . json_encode($params), __METHOD__);

        try {
            /** @var object $mpgTxn */
            $mpgTxn = new \mpgTransaction($params);
            /** @var object $mpgRequest */
            $mpgRequest = new \mpgRequest($mpgTxn);
            // Set test mode based on environment
            $mpgRequest->setTestMode($moneris->isTestMode());
            /** @var object $mpgHttpPost */
            $mpgHttpPost = new \mpgHttpsPost($moneris->getStoreId(), $moneris->getApiToken(), $mpgRequest);
            $mpgResponse = $mpgHttpPost->getMpgResponse();

            if (!$mpgResponse) {
                Craft::error('Moneris refund returned null response', __METHOD__);
                throw new \Exception('Moneris API returned null response');
            }

            // Log full response for debugging
            $responseCode = $mpgResponse->getResponseCode() ?? 'null';
            $responseMessage = $mpgResponse->getMessage() ?? 'null';
            $isoCode = method_exists($mpgResponse, 'getISO') ? ($mpgResponse->getISO() ?? 'null') : 'null';
            $timedOut = method_exists($mpgResponse, 'getTimedOut') ? ($mpgResponse->getTimedOut() ? 'true' : 'false') : 'null';
            $complete = method_exists($mpgResponse, 'getComplete') ? ($mpgResponse->getComplete() ?? 'null') : 'null';

            Craft::info("Moneris refund response - Code: {$responseCode}, Message: {$responseMessage}, ISO: {$isoCode}, TimedOut: {$timedOut}, Complete: {$complete}", __METHOD__);

            // If response code is null, log more details
            if (empty($responseCode) || $responseCode === 'null') {
                // Try to get any available error information
                $receiptId = method_exists($mpgResponse, 'getReceiptId') ? ($mpgResponse->getReceiptId() ?? 'null') : 'null';
                Craft::error("Moneris refund failed - ReceiptId: {$receiptId}, Full response may indicate API error or invalid parameters", __METHOD__);
            }

            return new \allomambo\CommerceMoneris\models\MonerisRequestResponse($mpgResponse, $transaction);
        } catch (\Exception $e) {
            Craft::error('Moneris refund error: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException('Complete Authorize is not supported by Moneris gateway.');
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException('Complete Purchase is not supported by Moneris gateway.');
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        throw new NotSupportedException('Payment sources are not supported by Moneris gateway.');
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    /**
     * Creates a Moneris request response for purchase or authorize
     */
    protected function createRequestResponse(Transaction $transaction, BasePaymentForm $form, string $type): RequestResponseInterface
    {
        $this->includeMonerisLibrary();
        $moneris = $this->getMonerisInstance();

        /** @var \allomambo\CommerceMoneris\models\MonerisPaymentForm $form */
        $order = $transaction->order;
        $billingAddress = $order->getBillingAddress();

        // Format amount: Craft Commerce stores amounts in cents, Moneris expects cents as string with 2 decimals
        $amount = number_format($transaction->paymentAmount, 2, '.', '');

        $params = [
            'type' => $type,
            'order_id' => $order->number,
            'cust_id' => $order->email,
            'amount' => $amount,
            'pan' => $form->number,
            'expdate' => $form->expiry,
            'crypt_type' => '7', // SSL merchant (most common encryption type)
        ];

        // Add CVD if provided
        if (!empty($form->cvd)) {
            $params['cvd'] = $form->cvd;
        }

        /** @var object $mpgTxn */
        $mpgTxn = new \mpgTransaction($params);

        // Add AVS if enabled - always send AVS data when enabled, even if minimal
        if ($this->enableAvs) {
            $avsStreetNumber = '';
            $avsStreetName = '';
            $avsZipcode = '';

            if ($billingAddress) {
                $avsStreetNumber = $this->extractStreetNumber($billingAddress->address1 ?? '');
                $avsStreetName = $this->extractStreetName($billingAddress->address1 ?? '');
                $avsZipcode = $billingAddress->zipCode ?? '';
            }

            // Always set AVS when enabled, even with empty values (Moneris will perform the check)
            $avsTemplate = [
                'avs_street_number' => $avsStreetNumber,
                'avs_street_name' => $avsStreetName,
                'avs_zipcode' => $avsZipcode,
            ];
            /** @var object $mpgAvsInfo */
            $mpgAvsInfo = new \mpgAvsInfo($avsTemplate);
            $mpgTxn->setAvsInfo($mpgAvsInfo);
        }
        /** @var object $mpgRequest */
        $mpgRequest = new \mpgRequest($mpgTxn);
        // Set test mode based on environment
        $mpgRequest->setTestMode($moneris->isTestMode());
        /** @var object $mpgHttpPost */
        $mpgHttpPost = new \mpgHttpsPost($moneris->getStoreId(), $moneris->getApiToken(), $mpgRequest);
        $mpgResponse = $mpgHttpPost->getMpgResponse();

        return new \allomambo\CommerceMoneris\models\MonerisRequestResponse($mpgResponse, $transaction);
    }

    /**
     * Get the actual Store ID (from ENV var or stored value)
     * Uses Craft's App::parseEnv() to handle environment variable references
     */
    public function getStoreId(): ?string
    {
        if (empty($this->storeId)) {
            return null;
        }

        // Use Craft's parseEnv to resolve environment variables (handles $VAR_NAME format)
        return App::parseEnv($this->storeId) ?: $this->storeId;
    }

    /**
     * Get the actual API Token (from ENV var or stored value)
     * Uses Craft's App::parseEnv() to handle environment variable references
     */
    public function getApiToken(): ?string
    {
        if (empty($this->apiToken)) {
            return null;
        }

        // Use Craft's parseEnv to resolve environment variables (handles $VAR_NAME format)
        return App::parseEnv($this->apiToken) ?: $this->apiToken;
    }

    /**
     * Get Moneris instance with configuration
     */
    protected function getMonerisInstance(): object
    {
        $storeId = $this->getStoreId();
        $apiToken = $this->getApiToken();

        if (empty($storeId) || empty($apiToken)) {
            throw new \Exception('Moneris Store ID and API Token must be configured. Check your gateway settings or environment variables.');
        }

        return new class($storeId, $apiToken, $this->environment) {
            private string $storeId;
            private string $apiToken;
            private bool $isTestMode;

            public function __construct(string $storeId, string $apiToken, string $environment)
            {
                $this->storeId = $storeId;
                $this->apiToken = $apiToken;
                $this->isTestMode = $environment !== 'production';
            }

            public function getStoreId(): string
            {
                return $this->storeId;
            }

            public function getApiToken(): string
            {
                return $this->apiToken;
            }

            public function isTestMode(): bool
            {
                return $this->isTestMode;
            }
        };
    }

    /**
     * Extract street number from address
     */
    protected function extractStreetNumber(?string $address): string
    {
        if (!$address) {
            return '';
        }

        if (preg_match('/^(\d+)/', $address, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extract street name from address
     */
    protected function extractStreetName(?string $address): string
    {
        if (!$address) {
            return '';
        }

        // Remove street number
        $streetName = preg_replace('/^\d+\s+/', '', $address);

        return $streetName ?: '';
    }

    /**
     * Include the Moneris library if it exists
     */
    protected function includeMonerisLibrary(): void
    {
        static $included = false;

        if ($included) {
            return;
        }

        // Try vendor directory first (Composer installation)
        // Use CRAFT_VENDOR_PATH constant if available, otherwise use @root alias
        if (defined('CRAFT_VENDOR_PATH')) {
            $vendorPath = CRAFT_VENDOR_PATH . '/allomambo/moneris-gateway-api-php/mpgClasses.php';
        } else {
            $vendorPath = Craft::getAlias('@root/vendor/allomambo/moneris-gateway-api-php/mpgClasses.php');
        }

        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            $included = true;
            return;
        }

        Craft::error('Moneris library not found. Checked: ' . $vendorPath, __METHOD__);
        throw new \Exception('Moneris library not found. Please ensure "allomambo/moneris-gateway-api-php" is installed via Composer.');
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();
        // Store ID and API Token are not required in the form (can use ENV vars)
        // But we validate that at least one source (stored or ENV) is available
        $rules[] = [['environment'], 'in', 'range' => ['staging', 'production']];
        $rules[] = [['enableAvs', 'enableCvd'], 'boolean'];
        $rules[] = [['storeId'], 'validateStoreId'];
        $rules[] = [['apiToken'], 'validateApiToken'];

        return $rules;
    }

    /**
     * Validate that Store ID is available (either stored or from ENV)
     */
    public function validateStoreId(): void
    {
        $storeId = $this->getStoreId();
        if (empty($storeId)) {
            $this->addError('storeId', Craft::t('moneris-gateway', 'Store ID is required. Please enter a value or use an environment variable.'));
        }
    }

    /**
     * Validate that API Token is available (either stored or from ENV)
     */
    public function validateApiToken(): void
    {
        $apiToken = $this->getApiToken();
        if (empty($apiToken)) {
            $this->addError('apiToken', Craft::t('moneris-gateway', 'API Token is required. Please enter a value or use an environment variable.'));
        }
    }
}

