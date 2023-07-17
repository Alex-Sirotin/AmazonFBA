# AmazonFBA
Send a command to Amazon's fulfillment network (FBA) to fulfill seller order using
seller inventory in Amazon's fulfillment network (FBA) and return the tracking number as string for
this order

# Create docker container
docker compose up

# Install dependencies
cd app
composer install

# Test
cd /app/tests
../vendor/bin/phpunit -v --testdox 

root@ca4c1f05cca9:/app/tests# ../vendor/bin/phpunit -v --testdox
PHPUnit 9.6.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 7.4.33
Configuration: /app/tests/phpunit.xml

Shipping Service (unit\ShippingService)
 ✔ Parse address  108 ms
 ✔ Parse items  4 ms
 ✔ Ship  36 ms
 ✔ Ship exception  16 ms
 ✔ Ship error  4 ms

Time: 00:00.178, Memory: 4.00 MB

OK (5 tests, 10 assertions)
