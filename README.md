
# About
Merchant API

This is the API supports all the merchant functions including:
- Devices
- Customers
- Transactions
- Locations
- Merchants


# Local Development

Running the following commands to get this repo up & running:

- Install Composer Dependencies

```
composer update
```

- Make sure you have the database credentials in .env file. Once you do, run migrations

```
php artisan migrate
```

- Check for Plaid API keys in .env file.
    - PLAID_CLIENT_ID
    - PLAID_SECRET
    - PLAID_TEMPLATE_ID
    - PLAID_API_ENDPOINT

- Check for Stripe API keys and env variables in .env file.

    - STRIPE_SECRET
    - STRIPE_PUBLIC
    - STRIPE_MANUAL_PROCESSING_RATE
    - STRIPE_MANUAL_PROCESSING_CENTS
    - PLATFORM_MANUAL_PROCESSING_RATE
    - PLATFORM_MANUAL_PROCESSING_CENTS

- Check for Algolia API keys in .env file.
    - SCOUT_DRIVER
    - ALGOLIA_APP_ID
    - ALGOLIA_SECRET

- Check for Gateway Secret in .env file.





