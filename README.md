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
