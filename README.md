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
To use the client, you need OAuth authentication flow to be set up for your app:
https://github.com/rebelpl/oauth2-businesscentral?tab=readme-ov-file#pre-requisites.

### Create client
To create a client, you need a valid Access Token. You can use an OAuth library to obtain it, then:

```php
$client = new Rebel\BCApi2\Client(
    accessToken: $accessToken,
    environment: 'sandbox',
    companyId: '123456',
);
```

or use `Client\Factory` helper (requires [`rebelpl/oauth2-businesscentral`](https://github.com/rebelpl/oauth2-businesscentral)
or any other implementation of `League\OAuth2\Client\Provider\AbstractProvider`):
```php
// service-to-service
$client = Rebel\BCApi2\Client\Factory::useClientCredentials(
    new Rebel\OAuth2\Client\Provider\BusinessCentral([
        'tenantId' => 'mydomain.com',
        'clientId' => 'xxxxx-yyyy-zzzz-xxxx-yyyyyyyyyyyy',
        'clientSecret' => '*************************',
    ]),
    environment: 'sandbox',
    companyId: '123456');
```

```php
// login-as
$client = Rebel\BCApi2\Client\Factory::useAuthorizationCode(
    new Rebel\OAuth2\Client\Provider\BusinessCentral([
        'tenantId' => 'mydomain.com',
        'clientId' => 'xxxxx-yyyy-zzzz-xxxx-yyyyyyyyyyyy',
        'clientSecret' => '*************************',
        'redirectUri' => 'https://localhost',
    ]),
    environment: 'sandbox',
    companyId: '123456',
    tokenFilename: 'tmp/token.json');
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
# find a single customer
$repository = new Rebel\BCApi2\Entity\Repository($client, entitySetName: 'customers');
if ($customer = $repository->findOneBy([ 'number' => 'CU-TEST' ])) {
    echo " - {$customer->get('number')}:\t{$customer->get('displayName')} @ {$customer->get('country')} ({$customer->get('id')})\n";
}

# find sales orders based on given criteria
$repository = new Rebel\BCApi2\Entity\Repository($client, entitySetName: 'salesOrders');
$repository->setExpandedByDefault([ 'salesOrderLines' ]);
$results = $repository->findBy([
    'customerNumber' => [ 'CU-TEST', 'CU-0123' ]
    'customerPriceGroup' => 'GOLD'
], 'orderDate DESC', size: 5);
foreach ($results as $salesOrder) {

    # use rebelpl/bc-api2-common or generate your own models for easier access to properties
    echo " - {$salesOrder->get('number')}:\t{$salesOrder->get('totalAmountIncludingTax')} {$salesOrder->get('currencyCode')}\n";
    foreach ($salesOrder->getAsCollection('salesOrderLines') as $line) {
        echo " --- {$line->get('sequence')}:\t{$line->get('lineObjectNumber')} x {$line->get('quantity')}";
    }
}

# create new salesOrder
$salesOrder = new Rebel\BCApi2\Entity([
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

# filter sales orders and sales lines at the same time
$results = $repository->findBy([ 'sellToCountry' => ['PL', 'UK'] ], top: 10, expanded: [ 
    'salesOrderLines' => [ 'lineType' => 'Item', Rebel\BCApi2\Request\Expression::greaterThan('quantity', 5) ],
]);
echo count($results) . " sales orders found, only lines with quantity > 5 included.\n";
```

### Working with binary data streams (BLOB)

If the field in BC is stored as BLOB, it's accessible through API as Edm.Stream type. In order to access (read or write)
its contents, you need to make additional call to the URL listed as @odata.mediaReadLink / @odata.mediaEditLink.

```php
$repository = new Rebel\BCApi2\Entity\Repository($client, 'items');
$item = $repository->findOneBy([ 'number' => '100000' ], [ 'picture' ]);
$picture = $item->getAsRelation('picture');

# download to a file 
if ($picture->get('contentType')) {
    file_put_contents('path/to/file.png', $picture->get('pictureContent')->downloadWith($client));
}

# upload from a file
$picture->get('pictureContent')->uploadWith($client, file_get_contents('path/to/file.png'), $picture->getETag());

# download a stream without expanding the record
$repository = new Rebel\BCApi2\Entity\Repository($client, 'salesInvoices');
$invoices = $repository->findBy([ 'isClosed' => false, 'dueDate le 2025-09-19' ]);
foreach ($invoices as $invoice) {
    file_put_contents('path/to/' . $invoice->get('number') . '.pdf', $invoice->fetchAsStream('pdfDocument/pdfDocumentContent'));
}
```

### Deep update with expanded properties

Business Central does not support deep update and mixed insert/update operations.
The Entity\Repository class provides a custom save() method that handles this limitation
by using batchUpdate() to create / update the nested properties.

```php
// Create a SalesOrder repository
$repository = new Rebel\BCApi2\Entity\SalesOrder\Repository($client);

// Get a sales order by ID
$salesOrder = $repository->get('abc-123', expanded: [ 'salesOrderLines' ]);

// Update properties of the sales order
$salesOrder->externalDocumentNumber = 'TEST';

// Update existing line
$salesOrder->salesOrderLines[0]->quantity = 10;

// Add new line
$salesOrder->salesOrderLines[] = new Rebel\BCApi2\Entity\SalesOrderLine\Record([
    'itemId' => '12345',
    'quantity' => 5
]);

// Save all changes in one operation
$repository->save($salesOrder);
```

### Call bound action
```php
// Create a SalesOrder repository
$repository = new Rebel\BCApi2\Entity\SalesOrder\Repository($client);
$salesOrder = $repository->get('abc-123');
$salesOrder->doAction('shipAndInvoice', $client);
```

## Download metadata for your API
```shell
curl -X GET "https://api.businesscentral.dynamics.com/v2.0/<environment>/api/<api_publisher>/<api_group>/<api_version>/$metadata" \
  -H "Authorization: Bearer <access_token>" \
  -H "Accept: application/xml" \
  -o files/metadata.xml
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

# Known Limitations
Currently read-only properties on otherwise editable entities (like customerName on salesOrder)
are not hinted as read-only in metadata, so the Generator still generates a property setter hook,
even if it's useless.