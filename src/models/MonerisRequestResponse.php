<?php

namespace allomambo\CommerceMoneris\models;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\Transaction;
use allomambo\CommerceMoneris\helpers\MonerisResponseMessage;

/**
 * Moneris Request Response
 */
class MonerisRequestResponse implements RequestResponseInterface
{
    /**
     * @var object Moneris response object
     */
    protected object $response;

    /**
     * @var Transaction The transaction
     */
    protected Transaction $transaction;

    /**
     * @var string The Moneris order_id that was sent with this request (e.g. "{order->number}-{suffix}").
     *             Stored in getData() so capture and refund can retrieve it from the parent transaction's
     *             response without needing to reconstruct or rely on transaction->id.
     */
    protected string $monerisOrderId;

    /**
     * Constructor
     */
    public function __construct(object $response, Transaction $transaction, string $monerisOrderId = '')
    {
        $this->response = $response;
        $this->transaction = $transaction;
        $this->monerisOrderId = $monerisOrderId;
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        // Check if response object is valid
        if (!is_object($this->response) || !method_exists($this->response, 'getResponseCode')) {
            return false;
        }

        $responseCode = $this->getResponseCode();

        // Response code 027 indicates success in Moneris
        // Also check for empty/null response code which indicates failure
        if (empty($responseCode) || $responseCode === 'null') {
            return false;
        }

        return $responseCode === '027' || $responseCode === '001';
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        $reference = $this->response->getTxnNumber() ?? '';

        if (empty($reference)) {
            $reference = $this->response->getReceiptId() ?? '';
        }

        return (string)$reference;
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->getResponseCode();
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        if (!is_object($this->response)) {
            return Craft::t('moneris-gateway', 'Invalid response from payment gateway');
        }

        // getTimedOut() returns the string "true"/"false", not a boolean
        if (method_exists($this->response, 'getTimedOut') && $this->response->getTimedOut() === 'true') {
            return Craft::t('moneris-gateway', 'Payment timed out. Please try again.');
        }

        $iso  = method_exists($this->response, 'getISO') ? ((string)($this->response->getISO() ?? '')) : '';
        $code = $this->getResponseCode();
        $raw  = method_exists($this->response, 'getMessage') ? ((string)($this->response->getMessage() ?? '')) : '';

        return MonerisResponseMessage::resolve($iso, $code, $raw);
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        return [
            'response_code' => $this->getResponseCode(),
            'message' => $this->getMessage(),
            'transaction_number' => $this->getTransactionReference(),
            'receipt_id' => $this->response->getReceiptId() ?? '',
            'iso_code' => $this->response->getISO() ?? '',
            'auth_code' => $this->response->getAuthCode() ?? '',
            'card_type' => $this->response->getCardType() ?? '',
            'trans_date' => $this->response->getTransDate() ?? '',
            'trans_time' => $this->response->getTransTime() ?? '',
            'moneris_order_id' => $this->monerisOrderId,
        ];
    }

    /**
     * @inheritdoc
     */
    public function redirect(): void
    {
        // Not needed for Moneris
    }

    /**
     * Get the response code from Moneris
     */
    protected function getResponseCode(): string
    {
        if (!is_object($this->response)) {
            return '';
        }

        $code = '';
        if (method_exists($this->response, 'getResponseCode')) {
            $code = $this->response->getResponseCode() ?? '';
        }

        if (empty($code) && method_exists($this->response, 'getCode')) {
            $code = $this->response->getCode() ?? '';
        }

        return (string)$code;
    }
}

