<?php

namespace allomambo\CommerceMoneris\models;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\Transaction;
use allomambo\CommerceMoneris\helpers\MonerisResponseCode;
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
        if (!is_object($this->response) || !method_exists($this->response, 'getResponseCode')) {
            return false;
        }

        return MonerisResponseCode::isApproved($this->getResponseCode());
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
            'raw_message' => $this->responseString('getMessage'),
            'transaction_number' => $this->getTransactionReference(),
            'receipt_id' => $this->responseString('getReceiptId'),
            'iso_code' => $this->responseString('getISO'),
            'auth_code' => $this->responseString('getAuthCode'),
            'card_type' => $this->responseString('getCardType'),
            'complete' => $this->responseString('getComplete'),
            'timed_out' => $this->responseString('getTimedOut'),
            'cvd_result' => $this->responseString('getCvdResultCode'),
            'avs_result' => $this->responseString('getAvsResultCode'),
            'trans_date' => $this->responseString('getTransDate'),
            'trans_time' => $this->responseString('getTransTime'),
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
     * Get the response code from Moneris.
     *
     * Must not use empty() — PHP empty("0") is true, and integer 0 would be discarded.
     */
    protected function getResponseCode(): string
    {
        $code = $this->responseString('getResponseCode');

        if (($code === '' || strtolower($code) === 'null') && method_exists($this->response, 'getCode')) {
            $code = $this->responseString('getCode');
        }

        return $code;
    }

    /**
     * Read a string field from the Moneris response object when the getter exists.
     */
    protected function responseString(string $method): string
    {
        if (!is_object($this->response) || !method_exists($this->response, $method)) {
            return '';
        }

        $value = $this->response->{$method}();

        return $value === null ? '' : (string) $value;
    }
}

