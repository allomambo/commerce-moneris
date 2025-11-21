<?php

namespace allomambo\CommerceMoneris\models;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\Transaction;

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
     * Constructor
     */
    public function __construct(object $response, Transaction $transaction)
    {
        $this->response = $response;
        $this->transaction = $transaction;
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
            return 'Invalid response from payment gateway';
        }

        $message = '';
        if (method_exists($this->response, 'getMessage')) {
            $message = $this->response->getMessage() ?? '';
        }

        if (empty($message)) {
            // Check for timeout
            if (method_exists($this->response, 'getTimedOut') && $this->response->getTimedOut()) {
                return 'Transaction timed out';
            }

            // Check for ISO code (error code)
            if (method_exists($this->response, 'getISO')) {
                $iso = $this->response->getISO() ?? '';
                if (!empty($iso) && $iso !== 'null') {
                    return 'ISO Error Code: ' . $iso;
                }
            }

            $responseCode = $this->getResponseCode();
            if (!empty($responseCode) && $responseCode !== 'null') {
                return 'Response code: ' . $responseCode;
            } else {
                return 'Could not process transaction';
            }
        }

        return (string)$message;
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

