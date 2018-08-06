# En-Marche Mailer Symfony bundle

A Symfony bundle to share email related tools between En-Marche applications.

The intention is too delegate sending emails to other applications or micro services, using external API like Mailjet.

The bundle provides all the tools needed, and all the tests for it. So no need to worry too much about that at the
application level.

It eases the creation of instances to consume the traffic, while keeping an organized database for mail "requests" sent
from applications to deliver through any API.
The consequence is that many SAAS can be used in parallel, easing migration too.
Mail requests  are "abstracted" of the application "sending" them and the API that will "consume" them.

## Requirements

 * PHP 7.1
 * Symfony 3.4 or 4.0 minimum
 * Composer
 
### For Mail Posts (Applications posting mails)

 * OldSoundRabbitMQBundle is required
 
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

### For Aggregators (Workers app transforming mails to mail requests)

 * DoctrineBundle is required to persist mail requests
 * OldSoundRabbitMQBundle is required to consume mails

### For API Proxies (Micro service sending mail requests to external services)

 * CSAGuzzleBundle is required to send mail requests to SAAS
 * DoctrineBundle is required to update mail requests
 * OldSoundRabbitMQBundle is required to consume mail requests id

## Installation

```bash
$ composer require en-marche/mailer-bundle
```

## Mail Posts (creating mails)

 You can see the bundle as a mailing post office here for your app that will "address" your mail.
 Putting it in the queue with a routing key, to go the central dispatch.
 
 It consist of creating mail classes extending either `TransactionalMail` or `CampaignMail` (when there are many
 recipients).
 Then use a `MailPostInterface` (that can be let configured by default), to pass it the mail class, the
 `RecipientInterface` instance(s), also the common vars and reply-to if any.
 CC and BCC can be set by MailPostInterface thanks to the configuration of th bundle, see below.
 The mail class name acts as a default template ID. i.e UserActivationMail will output "user_activation_em_dpt_api",
 removing the suffix "Mail" and appending the `app_name` configured by the application.
 The `Mail::getTemplateName` is also the only method that can be overridden to return any id.

To declare an app as such, tou can use the following configuration:

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    amqp_connexion:
        # the key will be used to set the OldSound connexion, refer to its bundle config
        url: '%env(EN_MARCHE_MAILER_AMQP_DSN)%'
        #lazy: true
    # or simply
    # amqp_connexion: { name: default }
    # if one is already configured

    mail_post:
        app_name: em_data_api
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
<?php

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

It requires a mail class and one instance or an array of `RecipientInterface` instances, then optionally another 
RecipientInterface` as reply-to and an array of template vars.

```php
<?php
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
<?php

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
        EventInvitationMail::createReplyToFor($this->getUser()),
        EventInvitationMail::createTemplateVarsFor($event, $event->getHost())
    );
}
```

Of course, instead of static methods you can use whatever way you want to build the needed arguments.
Consider using the above method, or implement some kind of MailVarsFactory if you really need a service.

Also, it is a good idea to add some trait for specific users, like:

```php
<?php

namespace App\Mail;

use App\Entity\Adherent;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;

trait AdherentMailTrait
{
    public static function createRecipientFromUser(Adherent $adherent, array $templateVars = []): RecipientInterface
    {
        return new Recipient($adherent->getEmail(), $adherent->getFullName(), $templateVars);
    }
}
```

Then a mail above could be simplified as:

```php
<?php
// ...

class EventInvitationMail extends CampaignMail
{
    use AdherentMailTrait;

    /**
     * @param Adherent[]
     *
     * @return RecipientInterface[]
     */
    public static function createRecipientForInvitees(array $invitees): array
    {
        return \array_map(function (Adherent $invitee) {
            return self::createRecipientFrom($invitee), [
                'is_animator' => $invitee->isAnimator(),
            ]);
        }, $invitees);
    }
    
    public static function createReplyToFor(?Adherent $adherent): ?RecipientInterface
    {
        return $adherent ? self::createRecipientFrom($adherent) : null;
    }
    
    // ...
```

### Lazy mails

Sometimes there is so much recipients to set in the mail, even if we try to chunk, using that much memory tends to break
the process. In those case use the `LazyMailPostInterface`, it's almost the same as the previous one.
But instead of passing one or more recipients (that you often build from a factory method), just pass the DQL query
string responsible for getting the recipients and the factory method name.
A template will then be saved in database and processed later by batch, in front of the actual chunk processing.
It means an email addressed to 100 000 recipients could be batched in lot of 500 recipients by mail that will be sent
using their own chunk system defaulting to 50, giving in total 200 lots times 10 chunks, equivalent to 2000 chunks
as they still share the same chunk id generated before batching.

For every `MailPost` configured you can activate a `LazyMailPost` that will be created with the same `MailFactoryInterface`:

 * `en_marche_mailer.lazy_mail_post.default` will use the same `en_marche_mailer.mail_factory.default` as the
   `en_marche_mailer.mail_post.default` service
 * `EnMarche\MailerBundle\MailPost\LazyMailPostInterface` is an alias for `en_marche_mailer.lazy_mail_post.default`
 
```yaml
# config/packages/en_marche_mailer.yaml
en_marche_mailer:
    mail_post:
        app_name: en_marche
        lazy: true
        # ...
```

Then your mail can look just the same as classic campaign as above, but instead of "addressing" it, we want to "prepare"
it. Here how the same event action as above looks like lazily:

```php
<?php

// ...

public function action(Request $request, Event $event, LazyMailPostInterface $lazyAdminMailPost)
{
    // ...
    // suppose you get your invitees by using
    $invitees = $eventRepository->findInvitessForEvent($event);
    // which inside calls $this->...->getQuery()->getResult();
    // add a new method your repo to return the query instead of the result, so you can do as follow
    // that will call ->getQuery()->getDql() instead

    $lazyAdminMailPost->prepare(
        EventInvitationMail::class,
        $eventRepository->getInvitessForEventQuery($event),
        'EventInvitationMail::createRecipientForInvitee', // factory will be used with each result as argument
        EventInvitationMail::createReplyToFor($this->getUser()),
        EventInvitationMail::createTemplateVarsFor($event, $event->getHost())
    );
}
``` 

## Mail Aggregator (processing mails, to persist requests in database)

 The application needs the database to aggregate mails into mail requests persisted in a way to optimize fragments
 of campaign without duplication of data (global template vars, addresses), ready to be scheduled.
 
 Basically, it triggers the `MailConsumer`, that will do all the work.

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

The app is responsible for running the `MailRequestConsumer`, that will actually make HTTP call using the
`MailClientInterface`.
Each `MailClient` can be configured to decorate a `GuzzleClient` using the right API, and uses a specific
`PayloadFactoryInterface` that will transform that data of the `MailRequestInterface` to the required format to send to
the service.
The only SAAS support for now is Mailjet with the `MailjetPayloadFactory`.

### Configuration

```yaml
# app/config/config.yml for Symfony 3.4
# config/packages/en_marche_mailer.yaml for Symfony 4.x
en_marche_mailer:
    database_connexion: { name: default }
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

## Tests

The only tests that should be considered when using the bundle is using functional ones, to check whether or not an
email was sent, how many, to who, eventually with what vars, when some logic is involved to compute them.
First, the transporter can be tweaked for dev or test environment using:

```yaml
# config/packages/test/en_marche_mailer.yaml
en_marche_mailer:
    mail_post:
        transporter: { type: 'null' } # must be a string
```

Also, when `kernel.debug` parameter is true the `DebugMailPost` class is used instead of the real one. It will still use
the configured transport, but keep mail in memory by mail class. Providing many useful methods to perform assertions.
Take a look at the Behat `MailContext` and the `MailTestCaseTrait`.

## Side Note

The configuration allows to let one perform all the tasks or wto of them, all scenarii are possible.
Ideally each one should be independent, in practice all web applications are only using mail post config, and a micro
service is used to do the "worker" part, configuring both the aggregator and the api proxy.
But there is scalability at all level, especially when filtering routes by apps or mail request type (campaign or
transactional).
