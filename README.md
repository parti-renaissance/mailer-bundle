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
    amqp_dsn: ~
    amqp_mail_routing_key: ~
    producer:
        app_name: data-api
        default_campaign_chunk_size: 50 # default
        transport:
            type: amqp # default
            chunk_size: 100 # overrides the default

        totos:
            turlututu: # name
                cc:
                    - [cc1@email.com, 'CC 1']
                bcc:
                    - ['%env(MAILER_SOME_DEBUG_CC_ADDRESS)%']
        #default_toto: turlututu
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

Then in a controller, listener or command, or whatever service needing to send that email, use the `TotoInterface`.
It requires a mail class and an array of RecipientInterface, then optionally a Recipient to reply to and a array of
template vars.

```php
// ...
use App\Mail\AdherentResetPasswordMail;
use EnMarche\MailerBundle\Toto\TotoInterface;

public function action(Request $request, Adherent $adherent, TotoInterface $toto)
{
    // ...
    
    $toto->heah(
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

The method signature is:

```php
public function heah(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void;
```

Example with a campaign message:

```yaml
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

```php
public function action(Request $request, Event $event, TotoInterface $turlututuToto)
{
    // ...
    
    $turlututuToto->heah(
        EventInvitationMail::class,
        EventInvitationMail::createRecipientForInvitees($invitees),
        EventInvitationMail::createReplyToFor($this->>getUser()),
        EventInvitationMail::createTemplateVarsFor($event, $event->getHost())
    );
}
```

Of course, instead of static methods you can use whatever way you want to build the needed arguments.
Consider using the above method, or implement some kind of MailVarsFactory if you really need a service.

## Consumers (processing emails)

A consumer is an app responsible for processing pending emails.

### Configuration
            
```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    databse_url: ~
    amqp_dsn: ~
    amqp_mail_routing_key: ~
    amqp_request_routing_key: ~
    consumer:
        transport:
            type: amqp # default
        forward: ~ # same options as transport
```

## Senders (actual scheduling of emails)
          
A sender is an app responsible for actually scheduling mails to a SAAS. This is done via HTTP client by default.
The sender is a kind of internal En-Marche proxy for the SAAS.

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    databse_url: ~
    amqp_dsn: ~
    amqp_request_routing_key: ~
    # For producers (creating emails)
    sender:
        transport:
            type: amqp # default
```
