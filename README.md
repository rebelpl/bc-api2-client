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
    companyId: '123456',
);
```

### Get Companies
```php
foreach ($client->getCompanies() as $company) {
    echo " - {$company->name}:\t{$company->id}\n";
}
```

### Get Resources
```php
$response = $client->get('companies(123456)/items?$top=3');
$data = json_decode($response->getBody(), true);
foreach ($data['value'] as $item) {
    echo " - {$item['number']}:\t{$item['displayName']}\n";
}
```

### Use Request helper
```php
$request = new Rebel\BCApi2\Request('PATCH', 'companies(123456)/items(32d80403)',
    body: json_encode([
        'displayName' => 'Updated Item Name',
        'unitPrice' => 99.95,
     ]), etag: 'W/"JzE5OzIxMzk2MzA0ODM0ODgyMTU4MDgxOzAwOyc="');
$response = $client->call($request);
```


### Use Repository / Entity helpers

```php
$repository = new Rebel\BCApi2\Entity\Repository($client, entitySetName: 'salesOrders');
$results = $repository->findBy([ 'customerNumber' => 'CU-0123' ], 'orderDate DESC', 5);
foreach ($results as $salesOrder) {

    # use rebelpl/bc-api2-common or generate your own models for easier access to properties
    echo " - {$salesOrder->get('number')}:\t{$salesOrder->get('totalAmountIncludingTax')} {$salesOrder->get('currencyCode')}\n";
}

# create new salesOrder
$salesOrder = new \Rebel\BCApi2\Entity([
    'customerNumber' => 'CU-0123',
    'externalDocumentNumber' => 'TEST/123',
    'salesOrderLines' => [
        [
            "sequence" => 10000,
            "lineType" => "Item",
            "lineObjectNumber" => "1900-A",
            "quantity" => 5
        ],
        [
            "sequence" => 20000,
            "lineType" => "Item",
            "lineObjectNumber" => "1928-S",
            "quantity" => 20
        ],
    ],
]);

$repository->create($salesOrder);
echo " - {$salesOrder->get('number')}:\t{$salesOrder->get('totalAmountIncludingTax')} {$salesOrder->get('currencyCode')}\n";
```

### Generate Entity models for your API
```php
# fetch Metadata from BC...
$metadata = new Rebel\BCApi2\Client(
    accessToken: $token->getToken(),
    environment: 'sandbox',
    apiRoute: '/mycompany/myapi/v1.5'
)->getMetadata();

# ... or from the local file
$metadata = Rebel\BCApi2\Metadata\Factory::fromString(file_get_contents('files/metadata.xml'));

# then generate the files
$generator = new Rebel\BCApi2\Entity\Generator($metadata, namespacePrefix: 'App\\Models\\');
$generator->saveAllFilesTo('app/Models', overwrite: true);
```

# Tests
```
./vendor/bin/phpunit
```