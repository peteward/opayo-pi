<?php namespace Academe\SagePayJs\Money;

/**
 * Defines a currency.
 * Only supports currencies that SagePay supports.
 */

use Exception;
use UnexpectedValueException;

class Currency
{
    // ISO 4217 currency code.
    protected $code;

    /**
     * Currencies supported by SagePay.
     * 'digits' are the number of digits after the decimal point.
     * Some currencies only allow minor units of a certain size, but
     * none of these yet.
     */
    protected static $currencies = [
        'GBP' => ['digits' => 2, 'symbol' => '£', 'name' => 'Pound sterling'],
        'EUR' => ['digits' => 2, 'symbol' => '€', 'name' => 'Euro'],
        'USD' => ['digits' => 2, 'symbol' => '€', 'name' => 'US dollar'],
    ];

    public function __construct($code)
    {
        if (isset($this->currencies[$code])) {
            $this->code = $code;
        } else {
            throw new UnexpectedValueException(sprintf('Unknown currency code "%s"', $code));
        }
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getDigits()
    {
        return $this->currencies[$this->code]['digits'];
    }

    // getName and getSymbol are handy for display and logging, but not essential.

    public function getName()
    {
        return $this->currencies[$this->code]['name'];
    }

    /**
     * The symbols will be one or more UTF-8 characters.
     */
    public function getSymbol()
    {
        return static::currencies[$this->code]['symbol'];
    }

    public static function supportedCurrencies()
    {
        return static::$currencies;
    }
}