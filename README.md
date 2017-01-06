# Sage Pay Integration PSR-7 Message REST API Library

[![Build Status](https://travis-ci.org/academe/SagePay-Integration.svg?branch=PSR-7)](https://travis-ci.org/academe/SagePay-Integration)

This package provides the data models and business logic for the [Sage Pay Integration](https://test.sagepay.com/documentation/) payment gateway
(sometimes called the `Pi` or `REST` API).
It does not provide the transport mechanism (at thisstage), so you can use what you like for that,
for example Guzzle, curl or a PSR-7 library.

You can use this library as a PSR-7 message generator/consumer, or go a level down and handle all the
data through arrays - both are supported.

## Package Development

The Sage Pay Integration payment gateway is a RESTful API run by by [Sage Pay](https://sagepay.com/).
You can [apply for an account here](https://applications.sagepay.com/apply/3F7A4119-8671-464F-A091-9E59EB47B80C) (my partner link).

This `master` branch contains a lot of reorganisation and renaming of classes compared to the previous `PSR7` branch.
The new class names should hopefully link more closely to the RESTful nature of the API.
The `PSR7` branch is now in maintenance mode only, and won't have any major changes - just bugfixes if they are reported.
The aim is to release on the master branch as soon as a demo (and some units tests) are up and running.

The aim is for this package to support ALL functionality that the gateway supports, keeping up with changes quickly.

## Want to Help?

Issues, comments, suggestions and PRs are all welcome. So far as I know, this is the first API for the
Sage Pay Integration REST API, so do get involved, as there is a lot of work to do.

Tests need to be written. I can extend tests, but have not got to the stage where I can set up a test
framework from scratch.

More examples of how to handle errors is also needed. Exceptions can be raised in many places.
Some exceptions are issues at the remote end, some fatal authentication errors, and some just relate
to validation errors on the payment form, needing the user to fix their details. Temporary tokens
expire over a period and after a small number of uses, so those all need to be caught and the
user taken back to the relevant place in the protocal without losing anything they have entered
so far (that has not expired).

## Overview; How to use

Note that this example code deals only with using the gateway from the back-end.
There is a JavaScript front-end too, with hooks to deal with expired
session keys and card tokens.
This library does provide support for the front end though, and this is noted where relevant.

### Installation

The default development branch is `PSR-7`.

Get the development branch through composer:

    composer.phar require academe/sagepaymsg:dev-master

or the latest release:

    composer.phar require academe/sagepaymsg

Until this library has been released to packagist, include the VCS in `composer.json`:

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/academe/SagePay-Integration.git"
        }
    ]

### Create a Session Key

The `CreateSessionKey` message has had PSR-7 support added, and can be used like this:

```php
// require "guzzlehttp/guzzle:~6.0"
// This will bring in guzzle/psr7 too, which is also needed.
// require "zendframework/zend-diactoros": "~1.3" if you prefer to use that instead.
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
// or use any other client that can handle PSR-7 messages.

use Academe\SagePay\Psr7\Request;
use Academe\SagePay\Psr7\Response;
use Academe\SagePay\Psr7\Model;
use Academe\SagePay\Psr7\Factory;

// Set up authentication details object.

$auth = new Model\Auth('vendor-name', 'your-key', 'your-password');

// Also the endpoint.
// This one is set as the test API endpoint.

$endpoint = new Model\Endpoint(Model\Endpoint::MODE_TEST);

// Request object to construct the session key message.

$key_request = new Request\CreateSessionKey($endpoint, $auth);

// HTTP client to send this message.
// Tip: make sure the client does not raise an exception on a non-2xx response.
// That way the errors are all collected here consistently (the ResponseFactory
// can return an error collection when it detects errors).
// e.g. new Client(['http_errors' => false])

$client = new Client();

// You should turn HTTP error exceptions off so that this package can handle all HTTP return codes.

$client = new Client(['http_errors' => false]);

// Send the PSR-7 message. Note *everything* needed is in this message.
// The message will be generated by guzzle/psr7 or zendframework/zend-diactoros, with discovery
// on which is installed. You can explictly create the PSR-7 factory instead and pass that in
// as a third parameter when creating Request\CreateSessionKey.

$psr7_response = $client->send($key_request->message());

// Capture the result in our local response model.
// Use the ResponseFactory to automatically choose the correct message class.

$session_key = Factory\ResponseFactory::fromHttpResponse($psr7_response);

// If an error is indicated, then you will be returned an ErrorCollection instead
// of the session key. Look into that to diagnose the problem.

if ($session_key->isError()) {
    // $session_key will be Response\ErrorCollection
    var_dump($session_key->first());
    exit; // (Obviously just a test script!)
}

// The result we want:

echo "Session key is: " . $session_key->getMerchantSessionKey();
```

### Get a Card Identifier

The Card Identifier (a temporary, tokenised card detail) can be created using the
equally temporary session key.

```php
use Academe\SagePay\Psr7\Request;

// Create a card indentifier on the API.
// Note the MMYY order is most often used for GB gateways like Sage Pay. Many European
// gateways tend to go MSN first, i.e. YYMM, but not here.
// $endpoint, $auth and $session_key from before:

$card_identifier_request = new Request\CreateCardIdentifier(
    $endpoint, $auth, $session_key,
    'Fred', '4929000000006', '1220', '123' // name, card, MMYY, CVV
);

// Send the PSR-7 message.
// The same error handling as shown earlier can be used.

$psr7_response = $client->send($card_identifier_request->message());

// Grab the result as a local model.
// If all is well, we will have a Resposne\CardIdentifier that will be valid for use
// for the next 400 seconds.

$card_identifier = Factory\ResponseFactory::fromHttpResponse($psr7_response);

// Again, an ErrorCollection will be returned in the event of an error:

if ($card_identifier->isError()) {
    // $session_key will be Response\ErrorCollection
    var_dump($card_identifier->first());
    exit; // Don't do this in production.
}

echo "Card identifier = " . $card_identifier->getCardIdentifier();
echo "Card type = " . $card_identifier->getCardType(); // e.g. Visa

// This card identifier will expire at the given time. Do note that this
// will be the timestamp at the Sage Pay server, not locally. You may be
// better off just starting your own 400 second timer here.

var_dump($card_identifier->expiry()); // DateTime object.
```

At this point the card details are *sane* and have been saved in the remote
API. Nothing has been checked against the bank, so we have no idea yet if these
details will be authenticated or not.

### Submit a Transaction

A transaction can be initiated using the card identifier.

```php
use Academe\SagePay\Psr7\Money;
use Academe\SagePay\Psr7\PaymentMethod;

// We need a billing address.
// Sage Pay has many mandatory fields that many gateways leave as optional.
// Sage Pay also has strict validation on these fields, so at the front end
// they must be presented to the user so they can modify the details if
// submission fails validation.

$billing_address = Model\Address::fromData([
    'address1' => 'address one',
    'postalCode' => 'NE26',
    'city' => 'Whitley',
    'state' => 'AL',
    'country' => 'US',
]);

// We have a customer to bill.

$customer = new Model\Person(
    'Bill Firstname',
    'Bill Lastname',
    'billing@example.com',
    '+44 191 12345678'
);

// We have an amount to bill.
// This example is £9.99 (999 pennies).

$amount = Money\Amount::GBP()->withMinorUnit(999);

// We have a card to charge (we get the session key and captured the card identifier earlier).
// See below for details of the various card request objects.

$card = new Request\Model\SingleUseCard($session_key, $card_identifier);

// If you want the card to be reusable, then set its "save" flag:

$card = $card->withSave();

// Put it all together into a payment transaction.

$payment = new Request\CreatePayment(
    $endpoint,
    $auth,
    $card,
    'MyVendorTxCode-' . rand(10000000, 99999999), // This will be your local unique transaction ID.
    $amount,
    'My Purchase Description',
    $billing_address,
    $customer,
    null, // Optional shipping address
    null, // Optional shipping recipient
    [
        // Don't use 3DSecure this time.
        'Apply3DSecure' => Request\CreatePayment::APPLY_3D_SECURE_DISABLE,
        // There are other options available.
        'ApplyAvsCvcCheck' => APPLY_AVS_CVC_CHECK_FORCE
    ]
);

// Send it to Sage Pay.

$psr7_response = $client->send($payment->message());

// Assuming we got no exceptions, extract the response details.

$payment_response = Factory\ResponseFactory::fromHttpResponse($psr7_response);

// Again, an ErrorCollection will be returned in the event of an error.
if ($payment_response->isError()) {
    // $payment_response will be Response\ErrorCollection
    var_dump($payment_response->first());
    exit;
}

if ($payment_response->isRedirect()) {
    // If the result is "3dAuth" then we will need to send the user off to do their 3D Secure
    // authorisation (more about that process in a bit).
    // A status of "Ok" means the transaction was successful.
    // A number of validation errors can be captured and linked to specific submitted
    // fields (more about that in a bit too).
    // In future gateway releases there may be other reasons to redirect, such as PayPal
    // authorisation.
    // ...
}

// Statuses are listed in `AbstractTransaction` and can be obtained as an array using the static
// helper method:
// AbstractTransaction::constantList('STATUS')

echo "Final status is " . $payment_response->getStatus();

if ($payment_response->isSuccess()) {
    // Payment is successfully authorised.
    // Store everything, then tell the user they have paid.
}
```

### Fetch a Transaction Result Again

Given the TransactionId, you can fetch the transaction details.
If the transaction was successful, then it will be available immediately.
If a 3D Secure action was needed, then the 3D Secure results need to be sent
to Sage Pay before you can fetch the transaction.
Either way, this is how you do it:

```php
// Prepare the message.

$transaction_result = new Request\FetchTransaction(
    $endpoint,
    $auth,
    $transaction_response->getTransactionId() // From earlier
);

// Send it to Sage Pay.

$response = $client->send($transaction_result->message());

// Assuming no exceptions, this gives you the payment or repeat payment record.
// But do check for errors in the usual way (i.e. you could get an error collection here).

$fetched_transaction = Factory\ResponseFactory::fromHttpResponse($response);
```

### Repeat Payments

A previous transaction can be used as a base for a repeat payment.
You can amend the shipping details and the amount (with no limit)
but not the payee details or address.

```php
$repeat_payment = new Request\CreateRepeatPayment(
    $endpoint,
    $auth,
    $previous_transaction_id, // The previous payment to take card details from.
    'MyVendorTxCode-' . rand(10000000, 99999999), // This will be your local unique transaction ID.
    $amount, // Not limited by the original amount.
    'My Repeat Purchase Description',
    null, // Optional shipping address
    null // Optional shipping recipient
);
```

All other options remain the same as for the original transaction
(though it does appear that giftAid can now be set in the API).

### Using 3D Secure

Now, if you want to use 3D Secure (and you really should) then we have a callback to deal with.

To turn on 3D Secure, use the appropriate option when sending the payment:

```php
$payment = new Request\CreatePayment(
    ...
    [
        // Also available: APPLY_3D_SECURE_USEMSPSETTING and APPLY_3D_SECURE_FORCEIGNORINGRULES
        'Apply3DSecure' => Request\CreatePayment::APPLY_3D_SECURE_FORCE,
    ]
);
```

### 3D Secure Redirect

The result of the transaction, assuming all is otherwise fine, will be a `Secure3DRedirect` object.
This message will return true for `isRedirect()`.
Given this, a POST redirection is needed.
Note also that even if the card details were invalid, a 3D Secure redirect may still be returned.
It is not clear why the banks do this, but you just have to go with with it.

This minimal form will demonstrate how the redirect is done:

```php
// $transaction_response is the message we get back after sending the payment request.

if ($transaction_response->isRedirect()) {
    // This is the bank URL that Sage Pay wants us to send the user to.

    $url = $transaction_response->getAcsUrl();

    // This is where the bank will return the user when they are finished there.
    // It needs to be an SSL URL to avoid browser errors. That is a consequence of
    // the way the banks do the redirect back to the merchant siteusing POST and not GET,
    // and something we cannot control.

    $termUrl = 'https://example.com/your-3dsecure-result-handler-post-path/';

    // $md is optional and is usually a key to help find the transaction in storage.
    // For demo, we will just send the vendorTxCode here, but you should avoid exposing
    // that value in a real site. You could leave it unused and just store the vendorTxCode
    // in the session, since it will always only be used when the user session is available
    // (i.e. all callbacks are done through the user's browser).

    $md = $transaction_response->getTransactionId();

    // Based on the 3D Secure redirect message, our callback URL and our optional MD,
    // we can now get all the POST fields to perform the redirect:

    $paRequestFields = $transaction_response->getPaRequestFields($termUrl, $md);

    // All these fields will normally be hidden form items and the form would auto-submit
    // using JavaScript. In this example we display the fields and don't auto-submit, so
    // you can se what is happening:

    echo "<p>Do 3DSecure</p>";
    echo "<form method='post' action='$url'>";
    foreach($paRequestFields as $field_name => $field_value) {
        echo "<p>$field_name <input type='text' name='$field_name' value='$field_value' /></p>";
    }
    echo "<button type='submit'>Click here if not redirected in five seconds</button>";
    echo "</form>";

    // Obviously don't exit if you are using a framework and views.
    exit;
}
```

The above example does not take into account how you would show the 3D Secure form in an iframe instead
of inline. That is out of scope for this simple description, for now at least.
Two main things need to be considered when using an iframe: 1) the above form must `target` the iframe
by name; and 2) on return to the $termUrl, the page must break itself out of the iframe. That's the absolute
essentials.

This form will then take the user off to the 3D Secure password page. For Sage Pay testing, use the code
`password` to get a successful response when you reach the test 3D Secure form.

Now you need to handle the return from the bank. Using Diactoros (and now Guzzle) you can catch the return
message as a PSR-7 ServerRequest like this:

```php
use Academe\SagePay\Psr7\ServerRequest;

$psr7_ServerRequest = \Zend\Diactoros\ServerRequestFactory::fromGlobals();
// or guzzlehttp/psr7 from v1.3
$psr7_ServerRequest = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
// or if using a framework that supplies a PSR-7 server request, just use that.

// isRequest() is just a sanity check before diving in with assumptions about the
// incoming request.

if (ServerRequest\Secure3DAcs::isRequest($psr7_ServerRequest->getBody()))
    // Yeah, we got a 3d Secure server request coming at us. Process it here.
    $secure3d_server_request = new ServerRequest\Secure3DAcs($psr7_ServerRequest);
    ...
}
```

or

```php
use Academe\SagePay\Psr7\ServerRequest;

if (ServerRequest\Secure3DAcs::isRequest($_POST)) {
    $secure3d_server_request = ServerRequest\Secure3DAcs::fromData($_POST);
    ...
}
```

Both will work fine, but it's just about what works best for your framework and application.

Handling the 3D Secure result involves two steps:

1. Passing the result to Sage Pay to get the 3D Secure state (CAUTION: see note below).
2. Fetching the final transaction result from Sage Pay.

```php
    $request = new Request\CreateSecure3D(
        $endpoint,
        $auth,
        $secure3d_server_request,
        // Include the transaction ID.
        // For this demo we sent that as `MD` data rather than storing it in the session.
        // The transaction ID will generally be in the session; putting it in MD exposes it
        // to the end user, so don't do this unless use a nonce!
        $secure3d_server_request->getMD()
    );

    // Send to Sage Pay and get the final 3D Secure result.

    $response = $client->send($request->message());
    $secure3d_response = Factory\ResponseFactory::fromHttpResponse($response);

    // This will be the result. We are looking for `Authenticated` or similar.
    //
    // NOTE: the result of the 3D Secure verification here is NOT safe to act on.
    // I have found that on live, it is possible for the card to totally fail
    // authentication, while the 3D Secure result returns `Authenticated` here.
    // This is a decision the bank mnakes. They may skip the 3D Secure and mark
    // it as "Authenticated" at their own risk. Just log this information.
    // Instead, you MUST fetch the remote transaction from the gateway to find
    // the real state of both the 3D Secure check and the card authentication
    // checks.

    echo $secure3d_response->getStatus();
```

### Final Transaction After 3D Secure

Whether 3D Secure passed or not, get the transaction. However - *do not get it too soon*.
The test instance of Sage Pay has a slight delay between getting the 3D Secure result and
being able to fetch the transaction.
It is safer just to sleep for one second at this time, which is an arbitrary period but
seems to work for now.
A better method would be to try immediately, then if you get a 404, back off for a short
time and try again, and maybe once more if necessary.
This is supposed to have been fixed in the gateway several times, but still gets occasionally
reported as still being an issue.

```php
    // Give the gateway some time to get its syncs in order.

    sleep(1);

    // Fetch the transaction with full details.

    $transaction_result = new Request\FetchTransaction(
        $endpoint,
        $auth,
        // transaction ID would normally be in the session, as described above, but we put it
        // into the MD for this demo.
        $secure3d_server_request->getMD()
    );

    // Send the request for the transaction to Sage Pay.

    $response = $client->send($transaction_result->message());

    // We should now have the payment, repeat payment, or an error collection.

    $transaction_fetch = Factory\ResponseFactory::fromHttpResponse($response);

    // We should now have the final results.
    // The transaction data is all [described in the docs](https://test.sagepay.com/documentation/#transactions).

    echo json_encode($transaction_fetch);
```

## Payment Methods

At this time, Sage Pay Pi supports just `card` payment types. However, there are three
different types of card object:

1. `Request\Model\SingleUseCard` - The fist time a card is used. It has been tokenised and will
    be held against the merchant session key for 400 seconds before being discarded.
2. `Request\Model\ReusableCard` - A card that has been saved and so is reusable. Use this for
    non-interaractive payments when no CVV is being used.
3. `Request\Model\ReusableCvvCard` - A card that has been saved and so is reusable, and has
    been linked to a CVV and merchant session. Use this for interactive reuse of a card, where
    the user is being asked to supply their CVV for additional security, but otherwise do not
    need to reenter all their card details.
    The CVV is (normally) linked to the card and the merchant session on the client side,
    and so will remain active for a limited time (400 seconds).

The `ReusableCard` does not need a merchant session key. `ReusableCvvCard` does require a
merchant session key and a call to link the session key + card identifier + CVV together
(preferably on the client side, but can be done server-side if appropriately PCI accredited
or while testing).

A CVV can be linked to a reusable card with the `LinkSecurityCode` message:

```php
$security_code = new Request\LinkSecurityCode(
    $endpoint,
    $auth,
    $session_key,
    $cardIdentifier,
    '123' // The CVV obtained from the user.
);

// Send the message to create the link.
// The result will be a `Response\NoContent` if all is well.

$security_code_response = Factory\ResponseFactory::fromHttpResponse(
    $client->send($security_code->message())
);

// Should check for errors here:

if ($security_code_response->isError()) {...}
```

To save a reusable card, take the `PaymentMethod` from a successful payment.
Note: it is not possible at this time to set up a reusable card without making a payment.
That is a restriction of the gateway. Some gateways will allow you to create zero-amount
payments just to authenticate and set up a reusable card, but not here.

```php
...

// Get the transaction response.

$transaction_response = Factory\ResponseFactory::fromHttpResponse($response);

// Get the card. Only cards are supported as Payment Method at this time,
// though that is likely to change when PayPal support is rolled out.

$card = $transaction_response->getPaymentMethod();

// If it is reusable, then it can be serialised for storage:

if ($card->isReusable()) {
    // Also can use getData() if you want the data without being serialised.
    $serialised_card = json_encode($card);
}

// In a later payment, the card can be reused:

$card = Request\Model\ReusableCard::fromData(json_decode($serialised_card));

// Or more explicitly:

$card = new Request\Model\ReusableCard($cardIdentifier);

// Or if being linked to a freshly-entered CVV:

$card = new Request\Model\ReusableCard($merchantSessionKey, $cardIdentifier);
```
