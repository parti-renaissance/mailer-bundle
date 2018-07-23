# En-Marche Mailer Symfony bundle

A Symfony bundle to share email related tools between En-Marche applications.

## Requirements

 * PHP 7.1
 * Symfony 3.4 or 4.0 minimum
 * Composer
 
### For producers (creating/scheduling emails)
 
 * RabbitMQ is the only transporter type provided for now

### For consumers (treating/scheduling emails)

 * RabbitMQ is the only consumer type provided for now
 
### For senders

 * An HTTP client is required
 
## Installation

```bash
$ composer require en-marche/mailer-bundle
```

## Producers (creating emails)

A producer is an app responsible for creating new Mail classes to send message through a common mailer.
To declare an app as such is done using the following configuration:

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    producer:
        app_name: data-api
        default_campaign_chunk_size: 50 # default
        transport:
            type: amqp # default
            chunk_size: 100 # overrides the default
            # specific keys for type
            dsn: 
            routing_key: ~
            # todo other types would require other attributes than "dsn" and "routing_key"

        totos:
            turlututu: # name
                cc:
                    - [cc1@email.com, 'CC 1']
                bcc:
                    - ['%env(MAILER_SOME_DEBUG_CC_ADDRESS)%']
        #default_sender: turlututu
```

With the above, many services are created:

 * `en_marche_mailer.mailer.default`, defaults to `EnMarche\MailerBundle\Mailer\Mailer` unless "default_sender" is used.
   Can be autowired thanks to the `EnMarche\MailerBundle\Mailer\MailerInterface`
 * `en_marche_mailer.mail_factory.default`, defaults to `EnMarche\MailerBundle\Factory\MailFactory`.
   Can be autowired thanks to the `EnMarche\MailerBundle\Factory\MailFactoryInterface`
 * `en_marche_mailer.mail_factory.turlututu`, another factory instance, configured with cc and bcc, it will be used by
   the sender (see below).
   
However, they should not be used. Instead, you shoud rely on the following:

 * `en_marche_mailer.toto.default`, sends a custom mail class for the given model recipients and context. No cc or bcc.
   Can be autowired with `EnMarche\MailerBundle\TotoInterface`.
 * `en_marche_mailer.toto.turlututu`, same a the previous one, but uses the configured factory to add cc and bcc.
   Can be bind by the id or autowired to the interface by changing the `default_sender` config key.

### Usage

#### Create a custom Mail class

The convention is to put mail classes under the `App\Mail` namespace, but you must suffix them by `Mail` and make them
extend either `EnMarche\MailerBundle\Mail\TransactionalMail` or `EnMarche\MailerBundle\Mail\CampaignMail`:

```yaml
namespace App\Mail;

// ... other use statements
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mail\TransactionalMail;

class AdherentResetPasswordMail extends TransactionalMail
{
    public static function createRecipientForAdherent(Adherent $adherent, string $resetUrl): RecipientInterface
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

Internally the following is called:

```php
public static function createRecipient($recipient, array $context): RecipientInterface
```

Just name your method by starting with "createRecipientFor..." to override it with your own type hint :).
"..." is even optional, naming it "createRecipientFor" is enough!
The context will be passed unpacked to your custom method, allowing you to type hint each entry of the it.
It also works for `createReplyTo(array $replyTo)`

Then in a controller, listener or command, or whatever service needing to send that email, use the `TotoInterface`:

```php
// ...
use App\Mail\AdherentResetPasswordMail;
use EnMarche\MailerBundle\Toto\TotoInterface;

public function action(Request $request, Adherent $adherent, TotoInterface $toto)
{
    // ...
    
    $toto->heah(AdherentResetPasswordMail::class, $adherent, [
        $this->>urlGenerator->generate('app_adherent_reset_password', ['token' => $resetPasswordToken]),
    ]);
}
```

The method signature is:

```php
public function heah(string $mailClass, array $to, array $context, $replyTo = null): void;
```

 * Each entry of `$to` will be passed to `createRecipient` with `$context`
 * `$context`  will be passed to `createTemplateVars` too for campaign messages
 * `$replyTo` will be passed to `createReplyTo`

Example with a campaign message:

```yaml
namespace App\Mail;

// ... other use statements
use EnMarche\MailerBundle\Mail\CampaignMail;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;

class EventInvitationMail extends CampaignMail
{
    public static function createRecipientForInvitee(Adherent $invitee, Event $event, Adherent $host): RecipientInterface
    {
        return new Recipient($invitee->getEmail(), $invitee->getFullName());
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

    public static function createReplyToFor(Adherent $sender): RecipientInterface
    {
        return new Recipient($sender->getEmail(), $sender->getFullName());
    }
}
```

```php
public function action(Request $request, Event $event, Adherent $invitee, TotoInterface $turlututuToto)
{
    // ...
    
    $turlututuToto->heah(
        EventInvitationMail::class,
        $invitee,
        [
            $event,
            $event->getHost()
        ],
        $this->>getUser()
    );
}
```
   
## Consumers (processing emails)
            
A consumer is an app responsible for processing pending emails.
            
### Configuration
            
```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    consumer:
        databse_url: ~ # todo other processing type ?
        transport:
            type: amqp # default
            dsn: ~
            routing_key: ~
            # todo other types would require other attributes than "dsn" and "routing_key"
        forward: ~ # same as transport
```

## Senders (actual scheduling of emails)
          
A sender is an app responsible for actually scheduling mails to a SAAS. This is done via HTTP client by default.
The sender is a kind of internal En-Marche proxy for the SAAS.

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
  # For producers (creating emails)
  sender:
      databse_url: ~
      transport:
          type: http # default
          client: ~
          # todo other types would require other attributes than "client"
      #default_sender: turlututu
```
