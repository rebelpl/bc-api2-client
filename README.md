# Business Central API2 for PHP
This library includes client and base models to use [Business Central API (v2.0)](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/api-reference/v2.0/) in PHP.

## Installation
To install, use composer:

```
composer require rebelpl/bc-api2-client
```

To use standard resources provided by Microsoft:
```
composer require rebelpl/bc-api2-common
```

## Usage

### Setup
To use the client, you need to obtain a valid Access Token.
Use an OAuth client to obtain it (for example [rebelpl/oauth2-businesscentral](https://github.com/rebelpl/oauth2-businesscentral)).

```php
$provider = new Rebel\OAuth2\Client\Provider\BusinessCentral([
    // Required
    'tenantId'                  => 'mydomain.com',
    'clientId'                  => 'xxxxx-yyyy-zzzz-xxxx-yyyyyyyyyyyy',
    'clientSecret'              => '*************************',
]);

$token = $provider->getAccessToken('client_credentials', [
    'scope' => Rebel\OAuth2\Client\Provider\BusinessCentral::CLIENT_CREDENTIALS_SCOPE
]);

$client = new Rebel\BCApi2\Client(
    accessToken: $token->getToken(),
    environment: 'sandbox',
    companyId: '1234567890',
);
```

### Get Companies
```php
foreach ($client->getCompanies() as $company) {
    echo " - {$company->name}:\t{$company->id}\n";
}
```

### Get Company Resources
```php
// v2.0/companies(1234567890)/items?$top=3
$uri = $client->buildUri('items?$top=3');
$response = $client->get($uri);
$data = json_decode($response->getBody(), true);
foreach ($data['value'] as $item) {
    echo " - {$item['number']}:\t{$item['displayName']}\n";
}
```
### Use Repository / Entity helpers

```php
$repository = new Rebel\BCApi2\Entity\Repository($client, 'salesOrders');
$results = $repository->findBy([ 'customerNumber' => 'CU-0123' ], 'orderDate DESC', 5);
foreach ($results as $salesOrder) {

    # use rebelpl/bc-api2-common or generate your own models for easier access to properties
    echo " - {$salesOrder->get('number')}:\t{$salesOrder->get('totalAmountIncludingTax')} {$salesOrder->get('currencyCode')}\n";
}
```

### Generate Entity models for your API
```php
$apiRoute = '/mycompany/myapi/v1.0';

# fetch Metadata from the server
$contents = $client->fetchMetadata($apiRoute);

# or from the local file
$contents = file_get_contents('files/metadata.xml');

$metadata = Rebel\BCApi2\Metadata\Factory::fromString($contents);
$generator = new Rebel\BCApi2\Entity\Generator($metadata, $apiRoute, 'app/Models/', 'App\\Models\\');
$generator->generateAll(true);
```

# Tests
```
./vendor/bin/phpunit
```