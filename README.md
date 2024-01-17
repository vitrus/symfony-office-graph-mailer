# Symfony Mailer Transport for Microsoft Office365 Graph API

Provides integration between the `Symfony Mailer` and `Office365 Graph API`.

- Tested on `Symony 6.4` and `php 8.1` should work with other setups as well
  - Feel free to report issues with other setups
- Does not require Microsoft Graph API Client (speaks to Graph API directly)
- No Guzzle or PSR libraries needed, uses Symfony HTTP Client

## Installation steps

#### 1 add via composer
```bash
composer require vitrus/symfony-office-graph-mailer
```

#### 2 configure mailer to use the `office-graph-api` scheme in `.env` (or `.env.local`)
```dotenv
MAILER_DSN=office-graph-api://{CLIENT_ID}:{CLIENT_SECRET}/{TENANT}
```
The tenant you use here should have permissions to send e-mail, and have access 
to the user you will configure as `sender` in your e-mails!

#### 3 Tag the transport factory in your `services.yaml`
We might change this packages to be a bundle so this is no longer needed in the future
```yaml
 Vitrus\Transport\GraphApiTransportFactory:
    tags: ['mailer.transport_factory']
```

## Feature: Store in sent items
Messages are automatically stored in Office365 `Sent Items` folder, you can disable this with a custom header:

```php
$message = (new Email())->subject($subject);

// add (falsy) text header to your Email
$message->getHeaders()->addTextHeader('X-Save-To-Sent-Items', 'false');
```

