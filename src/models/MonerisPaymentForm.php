<?php

namespace allomambo\CommerceMoneris\models;

use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\base\Model;

/**
 * Moneris Payment Form model
 */
class MonerisPaymentForm extends BasePaymentForm
{
    /**
     * @var string Card number
     */
    public string $number = '';

    /**
     * @var string Card expiry date (MMYY format)
     */
    public string $expiry = '';

    /**
     * @var string|null Card Verification Digit (CVD)
     */
    public ?string $cvd = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['number', 'expiry'], 'required'];
        $rules[] = [['number'], 'string', 'length' => [13, 19]];
        $rules[] = [['expiry'], 'string', 'length' => 4];
        $rules[] = [['cvd'], 'string', 'length' => [3, 4]];
        $rules[] = [['number'], 'match', 'pattern' => '/^\d+$/', 'message' => 'Card number must contain only digits'];
        $rules[] = [['expiry'], 'match', 'pattern' => '/^\d{4}$/', 'message' => 'Expiry must be in MMYY format'];
        $rules[] = [['cvd'], 'match', 'pattern' => '/^\d+$/', 'message' => 'CVD must contain only digits', 'skipOnEmpty' => true];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function populateFromPaymentForm(array $formData): void
    {
        $this->number = $formData['number'] ?? '';
        $this->expiry = $formData['expiry'] ?? '';
        $this->cvd = $formData['cvd'] ?? null;
    }
}

