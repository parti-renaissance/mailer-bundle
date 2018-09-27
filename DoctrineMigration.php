<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class DoctrineMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE mailer_mail_requests (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL, 
                vars_id INT UNSIGNED NOT NULL, 
                campaign CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', 
                request_payload JSON DEFAULT NULL COMMENT '(DC2Type:json_array)', 
                response_payload JSON DEFAULT NULL COMMENT '(DC2Type:json_array)', 
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', 
                INDEX IDX_E6A08DE7B2E4466F (vars_id), 
                INDEX chunk_campaign_idx (campaign), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql(<<<SQL
            CREATE TABLE mailer_recipient_vars (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL, 
                address_id INT UNSIGNED NOT NULL, 
                mail_request_id INT UNSIGNED DEFAULT NULL, 
                template_vars JSON NOT NULL COMMENT '(DC2Type:json_array)', 
                INDEX IDX_5BC905CEF5B7AF75 (address_id), 
                INDEX IDX_5BC905CE96BD5259 (mail_request_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql(<<<SQL
            CREATE TABLE mailer_addresses (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL, 
                name VARCHAR(255) DEFAULT NULL, 
                email VARCHAR(255) NOT NULL, 
                canonical_email VARCHAR(255) NOT NULL, 
                UNIQUE INDEX unique_email_name (canonical_email, name), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql(<<<SQL
            CREATE TABLE mailer_mail_vars (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL, 
                reply_to_id INT UNSIGNED DEFAULT NULL, 
                sender_name VARCHAR(255) DEFAULT NULL, 
                sender_email VARCHAR(255) DEFAULT NULL,
                app VARCHAR(255) NOT NULL, 
                type VARCHAR(255) NOT NULL, 
                template_name VARCHAR(255) NOT NULL, 
                template_vars JSON NOT NULL COMMENT '(DC2Type:json_array)', 
                campaign CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', 
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
                subject VARCHAR(255) DEFAULT NULL, 
                UNIQUE INDEX UNIQ_766519DD1F1512DD (campaign), 
                INDEX IDX_766519DDFFDF7169 (reply_to_id), 
                INDEX app_idx (app), 
                INDEX type_idx (type), 
                INDEX mail_campaign_idx (campaign), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql(<<<SQL
            CREATE TABLE mailer_mails_cc (
                mail_vars_id INT UNSIGNED NOT NULL, 
                address_id INT UNSIGNED NOT NULL, 
                INDEX IDX_33CC128C6859C700 (mail_vars_id), 
                INDEX IDX_33CC128CF5B7AF75 (address_id), 
                PRIMARY KEY(mail_vars_id, address_id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql(<<<SQL
            CREATE TABLE mailer_mails_bcc (
                mail_vars_id INT UNSIGNED NOT NULL, 
                address_id INT UNSIGNED NOT NULL, 
                INDEX IDX_E346B6416859C700 (mail_vars_id), 
                INDEX IDX_E346B641F5B7AF75 (address_id), 
                PRIMARY KEY(mail_vars_id, address_id)
            ) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB
SQL
        );

        $this->addSql('ALTER TABLE mailer_mail_requests ADD CONSTRAINT FK_E6A08DE7B2E4466F FOREIGN KEY (vars_id) REFERENCES mailer_mail_vars (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mailer_recipient_vars ADD CONSTRAINT FK_5BC905CEF5B7AF75 FOREIGN KEY (address_id) REFERENCES mailer_addresses (id)');
        $this->addSql('ALTER TABLE mailer_recipient_vars ADD CONSTRAINT FK_5BC905CE96BD5259 FOREIGN KEY (mail_request_id) REFERENCES mailer_mail_requests (id)');
        $this->addSql('ALTER TABLE mailer_mail_vars ADD CONSTRAINT FK_766519DDFFDF7169 FOREIGN KEY (reply_to_id) REFERENCES mailer_addresses (id)');
        $this->addSql('ALTER TABLE mailer_mails_cc ADD CONSTRAINT FK_33CC128C6859C700 FOREIGN KEY (mail_vars_id) REFERENCES mailer_mail_vars (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mailer_mails_cc ADD CONSTRAINT FK_33CC128CF5B7AF75 FOREIGN KEY (address_id) REFERENCES mailer_addresses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mailer_mails_bcc ADD CONSTRAINT FK_E346B6416859C700 FOREIGN KEY (mail_vars_id) REFERENCES mailer_mail_vars (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mailer_mails_bcc ADD CONSTRAINT FK_E346B641F5B7AF75 FOREIGN KEY (address_id) REFERENCES mailer_addresses (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailer_recipient_vars DROP FOREIGN KEY FK_5BC905CE96BD5259');
        $this->addSql('ALTER TABLE mailer_recipient_vars DROP FOREIGN KEY FK_5BC905CEF5B7AF75');
        $this->addSql('ALTER TABLE mailer_mail_vars DROP FOREIGN KEY FK_766519DDFFDF7169');
        $this->addSql('ALTER TABLE mailer_mails_cc DROP FOREIGN KEY FK_33CC128CF5B7AF75');
        $this->addSql('ALTER TABLE mailer_mails_bcc DROP FOREIGN KEY FK_E346B641F5B7AF75');
        $this->addSql('ALTER TABLE mailer_mail_requests DROP FOREIGN KEY FK_E6A08DE7B2E4466F');
        $this->addSql('ALTER TABLE mailer_mails_cc DROP FOREIGN KEY FK_33CC128C6859C700');
        $this->addSql('ALTER TABLE mailer_mails_bcc DROP FOREIGN KEY FK_E346B6416859C700');
        $this->addSql('DROP TABLE mailer_mail_requests');
        $this->addSql('DROP TABLE mailer_recipient_vars');
        $this->addSql('DROP TABLE mailer_addresses');
        $this->addSql('DROP TABLE mailer_mail_vars');
        $this->addSql('DROP TABLE mailer_mails_cc');
        $this->addSql('DROP TABLE mailer_mails_bcc');
    }
}
