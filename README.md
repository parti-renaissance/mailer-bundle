# En-Marche Mailer Symfony bundle

A Symfony bundle to share email related tools between En-Marche applications.

## Requirements

 * PHP 7.1
 * Symfony 3.4 or 4.0 minimum
 * Composer
 
### For producers (Applications posting mails)

 * RabbitMQ is the only transporter type provided for now
 
 >Note: We can add support for an "http" transport type by creating a MailClientTransport for the mailer.
 An application could then send directly its message to the sass.
 To implement it just create the transport class, then inject the MailClient and the MailRequestFactory.
 It is possible to log or persist the mail request in the process, and it may depend on different transport
 implementation.
 To support it globally from the bundle the config should be modified accordingly to inject the proper transport
 automatically. 
 Otherwise a simple alias is enough to configure it from the application itself.
 We could also use a "database" transport type to use the MailRequestFactoryInterface directly in the transport to
 persist requests without queuing mails. Such transport would still need the mail requests producer to queue ids.

### For consumers (Workers app transforming mails to mail requests)

 * RabbitMQ is the only consumer type provided for now

### For senders (Micro service send mail requests to SAAS)

 * RabbitMQ is the only consumer type provided for now
 * A Guzzle HTTP client is required

## Installation

```bash
$ composer require en-marche/mailer-bundle
```

## Mail Post (creating emails)

A Mail Post is an app responsible for creating new Mail classes to send message through a common mailer.
To declare an app as such is done using the following configuration:

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    amqp_connexion:
        # the key will be used to set the OldSound connexion, refer to its bundle config
        url: '%env(EN_MARCHE_MAILER_AMQP_DSN)%'
        #lazy: true
    mail_post:
        app_name: data_api
        transport:
            type: amqp # default
            chunk_size: 100 # overrides the default 50

        mail_posts:
            admin: # name
                cc:
                    - [cc1@email.com, 'CC 1']
                    - cc2@email.com
                bcc:
                    - '%env(MAILER_SOME_DEBUG_CC_ADDRESS)%'
        #default_mail_post: admin
```

With the above, many services are created:

 * `en_marche_mailer.mailer.default`, defaults to `EnMarche\MailerBundle\Mailer\Mailer`.
   Can be autowired thanks to the `EnMarche\MailerBundle\Mailer\MailerInterface`
 * `en_marche_mailer.mail_factory.default`, defaults to `EnMarche\MailerBundle\Factory\MailFactory`.
   Can be autowired thanks to the `EnMarche\MailerBundle\Factory\MailFactoryInterface`
 * `en_marche_mailer.mail_factory.admin`, another factory instance, configured with cc and bcc, it will be used by
   the sender (see below).

However, they should not be used. Instead, you should rely on the following:

 * `en_marche_mailer.mail_post.default`, sends a custom mail class for the given model recipients and context. No cc or bcc.
   Can be autowired with `EnMarche\MailerBundle\MailPost\MailPostInterface`.
 * `en_marche_mailer.mail_post.admin`, same a the previous one, but uses the configured factory to add cc and bcc.
   Can be bind by the id. To autowire it by the interface, change the `default_mail_post` config key.

### Usage

#### Create a custom Mail class

The convention is to put mail classes under the `App\Mail` namespace, but you must suffix them by `Mail` and make them
extend either `EnMarche\MailerBundle\Mail\TransactionalMail` or `EnMarche\MailerBundle\Mail\CampaignMail`:
```php
namespace App\Mail;

// ... other use statements
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mail\TransactionalMail;

class AdherentResetPasswordMail extends TransactionalMail
{
    public static function createRecipientFor(Adherent $adherent, string $resetUrl): RecipientInterface
    {
        return new Recipient(
            $adherent->getEmail(),
            $adherent->getFullName(),
            [
                'reset_password_url' => $resetUrl,
            ]
        );
    }
}
```

Adding static methods to build recipients and template vars is a good way to keep application code clean.

Then in a controller, listener or command, or whatever service needing to send that email, use the `MailPostInterface`.

The method signature is:
```php
public function address(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void;
```

It requires a mail class and an array of RecipientInterface, then optionally a Recipient to reply to and an array of
template vars.

```php
// ...
use App\Mail\AdherentResetPasswordMail;
use EnMarche\MailerBundle\MailPost\MailPostInterface;

public function action(Request $request, Adherent $adherent, MailPostInterface $mailPost)
{
    // ...
    
    $mailPost->address(
        AdherentResetPasswordMail::class,
        [
            AdherentResetPasswordMail::createRecipientFor(
                $adherent,
                $this->>urlGenerator->generate('app_adherent_reset_password', ['token' => $resetPasswordToken])
            ),
        ]
    ]);
}
```

Example with a campaign message:
```php
namespace App\Mail;

// ... other use statements
use EnMarche\MailerBundle\Mail\CampaignMail;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;

class EventInvitationMail extends CampaignMail
{
    /**
     * @param Adherent[]
     *
     * @return RecipientInterface[]
     */
    public static function createRecipientForInvitees(array $invitees): array
    {
        return \array_map(function (Adherent $invitee) {
            return new Recipient($invitee->getEmail(), $invitee->getFullName(), [
                'is_animator' => $invitee->isAnimator(),
            ]);
        }, $invitees);
    }
    
    public static function createReplyToFor(?Adherent $adherent): ?RecipientInterface
    {
        if ($adherent) {
            return new Recipient($adherent->getEmail(), $adherent->getFullName());
        }
        
        return null;
    }

    // Campaign mails can set global vars
    public static function createTemplateVarsFor(Event $event, Adherent $host): array
    {
        return [
            'host_name' => $host->getFullName(),
            'event_name' => $event->getName(),
            'event_address' => $event->getAddress(),
            // ...
        ];
    }
}
```

Given you bound the custom `MailPostInterface` using:
```yaml
services:
    _defaults:
        # ...
        bind:
            # ...
            $adminMailPost: '@en_marche_mailer.mail_post.admin'
```

You are then able to do:
```php
public function action(Request $request, Event $event, MailPostInterface $adminMailPost)
{
    // ...
    
    $adminMailPost->address(
        EventInvitationMail::class,
        EventInvitationMail::createRecipientForInvitees($invitees),
        EventInvitationMail::createReplyToFor($this->>getUser()),
        EventInvitationMail::createTemplateVarsFor($event, $event->getHost())
    );
}
```

Of course, instead of static methods you can use whatever way you want to build the needed arguments.
Consider using the above method, or implement some kind of MailVarsFactory if you really need a service.

## Mail Aggregator (processing mails, to persist requests in database)

A consumer is an app responsible for processing pending emails.

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    database_connexion: 
        url: '%env(EN_MARCHE_MAILER_DATABASE_URL)%'
        # or
        # name: default
        # can use same doctrine connexions settings and will be prepend
    amqp_connexion:
        url: '%env(EN_MARCHE_MAILER_AMQP_DSN)%'
        # or
        # name: default
        # can use same old sound connexions settings and will be prepend
    mail_aggregator:
        routing_keys:
            - 'em_mails_*' # default
            # or
            #- 'em_mails_campaign_*
            #- 'em_mails_transactional_*
            #- 'em_mails_transactional_app_name
```

## Mail API Proxy (actual scheduling of email requests)

An API proxy is an app responsible for actually scheduling mails to an API. This is done via HTTP client by default.
The sender is a kind of internal En-Marche proxy for the API (i.e Mailjet that can be changed easily).

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    database_connexion: { url: '%env(EN_MARCHE_MAILER_DATABASE_URL)%' }
    amqp_connexion: { url: '%env(EN_MARCHE_MAILER_AMQP_DSN)%' }
    mail_api_proxy:
        http_clients:
            campaign: # name the client and must match a mail request type
                api_type: 'mailjet' # default, the only provided for now
                public_api_key: '' # required
                private_api_key: '' # required
                options: [] # will be passed to the guzzle client config (creating auth from keys)
                # will also preset Mailjet base uri and required header that can be overridden from here
        routing_keys:
            # same as the aggregator but this time to consume mail requests
            - 'em_mail_requests_*' # default
            # same patterns as above em_mail_requests_{type}_{app_name}
```
